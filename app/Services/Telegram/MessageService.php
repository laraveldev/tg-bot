<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use App\Models\UserManagement;
use Illuminate\Support\Facades\Log;
use Exception;

class MessageService
{
    private TelegraphBot $bot;
    private TelegraphChat $chat;

    public function __construct(TelegraphBot $bot, TelegraphChat $chat)
    {
        $this->bot = $bot;
        $this->chat = $chat;
    }

    /**
     * Send message directly using Telegram API
     */
    public function sendMessage(string $message): bool
    {
        try {
            // Use direct Telegram API to ensure message delivery
            $botToken = $this->bot->token;
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            
            $data = [
                'chat_id' => $this->chat->chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            Log::info('Sending message via direct API', [
                'chat_id' => $this->chat->chat_id,
                'command' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            Log::info('Direct API response', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200) // Log first 200 chars
            ]);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result && $result['ok']) {
                    Log::info('Message sent successfully via direct API');
                    return true;
                } else {
                    Log::error('Telegram API returned error', ['result' => $result]);
                }
            } else {
                Log::error('HTTP error when sending message', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }
            
            return false;
            
        } catch (Exception $sendError) {
            Log::error('Failed to send message via direct API', [
                'error' => $sendError->getMessage(),
                'chat_id' => $this->chat->chat_id
            ]);
            
            return $this->fallbackSend($message);
        }
    }

    /**
     * Fallback to Telegraph methods
     */
    private function fallbackSend(string $message): bool
    {
        try {
            if ($this->chat->chat_id < 0) {
                $this->chat->message($message)->send();
            } else {
                // For private chats, we need to use reply method from handler context
                $this->chat->message($message)->send();
            }
            return true;
        } catch (Exception $fallbackError) {
            Log::error('Fallback send also failed', [
                'error' => $fallbackError->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Notify supervisors
     */
    public function notifySupervisors(string $message): void
    {
        $supervisors = UserManagement::supervisors()->get();
        
        foreach ($supervisors as $supervisor) {
            try {
                $this->bot->chat($supervisor->telegram_chat_id)->message($message)->send();
            } catch (Exception $e) {
                Log::error('Failed to notify supervisor', [
                    'supervisor_id' => $supervisor->id,
                    'error' => $e->getMessage()
                ]);
                // Continue to next supervisor
            }
        }
    }

    /**
     * Get user info message
     */
    public function getUserInfoMessage(array $userData, int $userId, UserManagement $user): string
    {
        $chatId = $this->chat->chat_id;
        
        $data = [
            "👤 Sizning ma'lumotlaringiz:",
            "🆔 User ID: {$userId}",
            "💬 Chat ID: {$chatId}",
            "📛 Ism: " . ($userData['first_name'] ?? "mavjud emas"),
            "📄 Familiya: " . ($userData['last_name'] ?? "mavjud emas"),
            "👨‍💻 Username: " . ($userData['username'] ? "@{$userData['username']}" : "mavjud emas"),
            "📱 Telefon raqam: " . ($userData['phone_number'] ?? "mavjud emas"),
            "",
            "🏢 Tizim ma'lumotlari:",
            "👤 Rol: " . match($user->role) {
                'supervisor' => '👨‍💼 Supervisor',
                'operator' => '👨‍💻 Operator',
                default => '👤 Oddiy foydalanuvchi'
            },
            "📊 Status: " . ($user->status === 'active' ? '✅ Faol' : '❌ Nofaol'),
        ];
        
        if ($chatId < 0) {
            $data[] = "";
            $data[] = "ℹ️ Bu group chat. Shaxsiy ma'lumotlar uchun botga private xabar yozing.";
        }

        return implode("\n", $data);
    }

    /**
     * Get help message based on user role
     */
    public function getHelpMessage(?UserManagement $user = null): string
    {
        $message = "🆘 Yordam - Mavjud buyruqlar:\n\n";
        $message .= "📋 Asosiy buyruqlar:\n";
        $message .= "/start - Botni boshlash\n";
        $message .= "/info - Sizning ma'lumotlaringiz\n";
        $message .= "/about - Bot haqida ma'lumot\n";
        $message .= "/contact - Bog'lanish ma'lumotlari\n";
        $message .= "/help - Yordam\n\n";
        
        if ($user) {
            // Add role-specific commands
            if ($user->isSupervisor()) {
                $message .= "👨‍💼 Supervisor buyruqlari:\n";
                $message .= "/lunch_status - Tushlik holati\n";
                $message .= "/lunch_schedule - Bugungi jadval\n";
                $message .= "/lunch_settings - Sozlamalar\n";
                $message .= "/operators - Operatorlar ro'yxati\n";
                $message .= "/reorder_queue - Navbatni qayta tuzish\n";
                $message .= "/next_group - Keyingi guruhga o'tish\n\n";
            }
            
            if ($user->isOperator()) {
                $message .= "👨‍💻 Operator buyruqlari:\n";
                $message .= "/my_lunch - Mening tushlik vaqtim\n";
                $message .= "/lunch_queue - Tushlik navbati\n";
                $message .= "/lunch_start - Tushlikka chiqdim\n";
                $message .= "/lunch_end - Tushlikdan qaytdim\n\n";
            }
            
            // Add note about role
            $roleText = match($user->role) {
                'supervisor' => 'Supervisor',
                'operator' => 'Operator', 
                default => 'Oddiy foydalanuvchi'
            };
            
            $message .= "👤 Sizning rolingiz: {$roleText}";
            
            // All users are either supervisor or operator
        } else {
            $message .= "💡 Qo'shimcha imkoniyatlar uchun administrator bilan bog'laning.";
        }
        
        return $message;
    }

    /**
     * Get about message
     */
    public function getAboutMessage(): string
    {
        return "ℹ️ Bu bot Laravel 12 + Telegraph paketi yordamida yaratilgan.\n"
            . "U o'rganish va amaliyot uchun mo'ljallangan.";
    }

    /**
     * Get contact message
     */
    public function getContactMessage(): string
    {
        return "📞 Bot yaratuvchisi bilan bog'lanish:\n\n"
            . "👤 Ism: Elnurbek\n"
            . "📱 Telefon: +998-33-505-20-05\n"
            . "✈️ Telegram: @elnurbek\n"
            . "📧 Email: elnurbeck1899@gmail.com\n\n"
            . "💼 Ushbu bot Laravel + Telegraph yordamida yaratilgan.\n"
            . "🚀 Yangi bot loyihalar uchun murojaat qiling!";
    }

    /**
     * Get supervisor keyboard
     */
    public function getSupervisorKeyboard()
    {
        return \DefStudio\Telegraph\Keyboard\ReplyKeyboard::make()
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('📊 Tushlik Holati'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('📋 Jadval')
            ])
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('⚙️ Sozlamalar'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('👥 Operatorlar')
            ])
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('🔄 Navbat Tuzish'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('➡️ Keyingi Guruh')
            ])
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('ℹ️ Ma\'lumot'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('❓ Yordam')
            ])
            ->resize();
    }

    /**
     * Get operator keyboard
     */
    public function getOperatorKeyboard()
    {
        return \DefStudio\Telegraph\Keyboard\ReplyKeyboard::make()
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('🍽️ Mening Tushligim'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('📅 Tushlik Navbati')
            ])
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('✅ Tushlikka Chiqdim'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('🔙 Tushlikdan Qaytdim')
            ])
            ->row([
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('ℹ️ Ma\'lumot'),
                \DefStudio\Telegraph\Keyboard\ReplyButton::make('❓ Yordam')
            ])
            ->resize();
    }
}

