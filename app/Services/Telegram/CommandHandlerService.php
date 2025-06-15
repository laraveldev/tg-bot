<?php

namespace App\Services\Telegram;

use App\Models\UserManagement;
use DefStudio\Telegraph\Handlers\WebhookHandler;

class CommandHandlerService
{
    /**
     * Show available commands based on user role
     */
    public function showAvailableCommands(UserManagement $user, WebhookHandler $handler): void
    {
        if ($user->isSupervisor()) {
            $this->showSupervisorCommands($handler);
        } elseif ($user->isOperator()) {
            $this->showOperatorCommands($handler);
        } else {
            $handler->help();
        }
    }
    
    /**
     * Show supervisor commands
     */
    private function showSupervisorCommands(WebhookHandler $handler): void
    {
        $message = "👨‍💼 Supervisor buyruqlari:\n\n"
            . "📊 /lunch_status - Tushlik holati\n"
            . "📋 /lunch_schedule - Bugungi jadval\n"
            . "⚙️ /lunch_settings - Sozlamalar\n"
            . "👥 /operators - Operatorlar ro'yxati\n"
            . "🔄 /reorder_queue - Navbatni qayta tuzish\n"
            . "➡️ /next_group - Keyingi guruhga o'tish\n\n"
            . "Oddiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/help - Yordam";
            
        // Use chat message instead of protected reply method
        $handler->chat->message($message)->send();
    }
    
    /**
     * Show operator commands
     */
    private function showOperatorCommands(WebhookHandler $handler): void
    {
        $message = "👨‍💻 Operator buyruqlari:\n\n"
            . "🍽️ /my_lunch - Mening tushlik vaqtim\n"
            . "📅 /lunch_queue - Tushlik navbati\n"
            . "✅ /lunch_start - Tushlikka chiqdim\n"
            . "🔙 /lunch_end - Tushlikdan qaytdim\n\n"
            . "Oddiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/help - Yordam";
            
        // Use chat message instead of protected reply method
        $handler->chat->message($message)->send();
    }
    
    /**
     * Handle unknown commands based on user role
     */
    public function handleUnknownCommand(string $command, UserManagement $user, WebhookHandler $handler): void
    {
        if ($user->isSupervisor() || $user->isOperator()) {
            $this->showAvailableCommands($user, $handler);
        } else {
            $message = "❓ Noma'lum buyruq: {$command}\n\n"
                . "Mavjud buyruqlar:\n"
                . "/start - Botni boshlash\n"
                . "/info - Ma'lumotlaringiz\n"
                . "/about - Bot haqida\n"
                . "/contact - Bog'lanish\n"
                . "/help - Yordam";
                
            // Use chat message instead of protected reply method
            $handler->chat->message($message)->send();
        }
    }
    
    /**
     * Handle chat messages based on user role
     */
    public function handleChatMessage(UserManagement $user, WebhookHandler $handler): void
    {
        if ($user->role === 'user') {
            $message = "❗ Xabaringizni oldim. Quyidagi buyruqlarni sinab ko'ring:\n\n"
                . "/start - Botni boshlash\n"
                . "/info - Ma'lumotlaringiz\n"
                . "/about - Bot haqida\n"
                . "/contact - Bog'lanish\n"
                . "/help - Yordam";
                
            // Reply with keyboard removal to ensure clean interface
            $handler->chat
                ->message($message)
                ->removeReplyKeyboard()
                ->send();
        } else {
            // For operators and supervisors, show their available commands
            $this->showAvailableCommands($user, $handler);
        }
    }
}

