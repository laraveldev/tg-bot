<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\DB;
use App\Models\UserManagement;
use App\Models\WorkShift;
use App\Models\LunchSchedule;
use App\Models\LunchBreak;
use App\Services\LunchManagement\LunchScheduleService;
use Carbon\Carbon;

class Handler extends WebhookHandler
{
    private function updateUserData(array $userData = null): void
    {
        if (!$userData) {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userData = $message['from'] ?? null;
        }

        if ($userData) {
            DB::table('telegraph_chats')
                ->where('id', $this->chat->id)
                ->update([
                    'first_name' => $userData['first_name'] ?? null,
                    'last_name' => $userData['last_name'] ?? null,
                    'username' => $userData['username'] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    private function getUserData(): array
    {
        $data = DB::table('telegraph_chats')
            ->where('id', $this->chat->id)
            ->first();

        return [
            'first_name' => $data->first_name ?? null,
            'last_name' => $data->last_name ?? null,
            'username' => $data->username ?? null,
            'phone_number' => $data->phone_number ?? null,
        ];
    }

    public function start(): void
    {
        // Update user data
        $this->updateUserData();

        // Send welcome message
        $this->reply("👋 Salom! Botga xush kelibsiz!");

        // Create contact request button
        $contactButton = ReplyButton::make('📱 Raqam yuborish')->requestContact();
        
        // Create keyboard with the contact button
        $keyboard = ReplyKeyboard::make()
            ->row([$contactButton])
            ->resize()
            ->oneTime();

        // Send message with keyboard
        $this->chat
            ->message("📞 Iltimos, telefon raqamingizni yuboring:")
            ->replyKeyboard($keyboard)
            ->send();
    }

    public function info(): void
    {
        $userData = $this->getUserData();
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $chatId = $this->chat->chat_id;
        
        // Get user from management system
        $user = $this->getOrCreateUser();
        
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

        $this->reply(implode("\n", $data));
    }

    public function about(): void
    {
        $this->reply("ℹ️ Bu bot Laravel 12 + Telegraph paketi yordamida yaratilgan.\n"
            . "U o'rganish va amaliyot uchun mo'ljallangan.");
    }

    public function contact(): void
    {
        $this->reply("📞 Biz bilan bog'lanish:\n"
            . "📱 Telefon: +998-33-505-20-05\n"
            . "📧 Email: elnurbeck1899@gmail.com\n"
            . "✈️ Telegram: @admin_username");
    }

    public function help(): void
    {
        try {
            \Log::info('Help command started');
            
            // Start with basic commands that are available to everyone
            $message = "🆘 Yordam - Mavjud buyruqlar:\n\n";
            $message .= "📋 Asosiy buyruqlar:\n";
            $message .= "/start - Botni boshlash\n";
            $message .= "/info - Sizning ma'lumotlaringiz\n";
            $message .= "/about - Bot haqida ma'lumot\n";
            $message .= "/contact - Bog'lanish ma'lumotlari\n";
            $message .= "/help - Yordam\n\n";
            
            try {
                // Get user data to determine role
                $user = $this->getOrCreateUser();
                \Log::info('User retrieved successfully', ['role' => $user->role]);
                
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
                
                if ($user->role === 'user') {
                    $message .= "\n\n💡 Qo'shimcha imkoniyatlar uchun administrator bilan bog'laning.";
                }
            } catch (\Exception $userError) {
                \Log::warning('Could not get user data for help command', [
                    'error' => $userError->getMessage()
                ]);
                
                $message .= "💡 Qo'shimcha imkoniyatlar uchun administrator bilan bog'laning.";
            }
            
            \Log::info('Sending help response', [
                'chat_id' => $this->chat->chat_id,
                'chat_type' => $this->chat->chat_id < 0 ? 'group' : 'private',
                'message_length' => strlen($message)
            ]);
            
            try {
                // Use direct Telegram API to ensure message delivery
                $botToken = $this->bot->token;
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                
                $data = [
                    'chat_id' => $this->chat->chat_id,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ];
                
                \Log::info('Sending help via direct API', [
                    'chat_id' => $this->chat->chat_id,
                    'url' => $url
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
                
                \Log::info('Direct API response', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if ($result && $result['ok']) {
                        \Log::info('Help response sent successfully via direct API');
                    } else {
                        \Log::error('Telegram API returned error', ['result' => $result]);
                    }
                } else {
                    \Log::error('HTTP error when sending help message', [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                }
                
            } catch (\Exception $sendError) {
                \Log::error('Failed to send help response', [
                    'error' => $sendError->getMessage(),
                    'trace' => $sendError->getTraceAsString(),
                    'chat_id' => $this->chat->chat_id
                ]);
                
                // Fallback to Telegraph methods
                try {
                    if ($this->chat->chat_id < 0) {
                        $this->chat->message($message)->send();
                    } else {
                        $this->reply($message);
                    }
                } catch (\Exception $fallbackError) {
                    \Log::error('Fallback send also failed', [
                        'error' => $fallbackError->getMessage()
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Help command error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $this->chat->chat_id ?? 'unknown'
            ]);
            
            try {
                $this->reply("❌ Xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.");
            } catch (\Exception $replyError) {
                \Log::error('Could not send error reply', [
                    'error' => $replyError->getMessage()
                ]);
            }
        }
    }

    public function onContactReceived(array $contact): void
    {
        // Update user data including phone number
        $message = request()->input('message') ?? request()->input('edited_message');
        $from = $message['from'] ?? null;

        $updateData = [
            'phone_number' => $contact['phone_number'],
            'updated_at' => now(),
        ];

        // Also update other user data if available
        if ($from) {
            $updateData = array_merge($updateData, [
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'username' => $from['username'] ?? null,
            ]);
        }

        DB::table('telegraph_chats')
            ->where('id', $this->chat->id)
            ->update($updateData);

        // Send confirmation message
        $this->reply("✅ Raqamingiz saqlandi: " . $contact['phone_number']);
        
        // Remove the keyboard
        $this->chat->message("Siz endi barcha buyruqlardan foydalanishingiz mumkin.")->removeReplyKeyboard()->send();
    }

    protected function handleChatMessage(Stringable $text): void
    {
        // Update user data from every message
        $this->updateUserData();
        
        // Check if user is registered in management system
        $user = $this->getOrCreateUser();
        
        if ($user->role === 'user') {
            $this->reply("❗ Xabaringizni oldim. Quyidagi buyruqlarni sinab ko'ring:\n\n"
                . "/start - Botni boshlash\n"
                . "/info - Ma'lumotlaringiz\n"
                . "/about - Bot haqida\n"
                . "/contact - Bog'lanish\n"
                . "/help - Yordam");
        } else {
            $this->showAvailableCommands($user);
        }
    }

    protected function handleUnknownCommand(Stringable $text): void
    {
        // Check if it's the contact command and handle it explicitly
        if ($text->toString() === 'contact') {
            //Log::info('Handling contact command through handleUnknownCommand');
            $this->contact();
            return;
        }
        
        $user = $this->getOrCreateUser();
        
        if ($user->isSupervisor() || $user->isOperator()) {
            $this->showAvailableCommands($user);
        } else {
            $this->reply("❓ Noma'lum buyruq: {$text}\n\n"
                . "Mavjud buyruqlar:\n"
                . "/start - Botni boshlash\n"
                . "/info - Ma'lumotlaringiz\n"
                . "/about - Bot haqida\n"
                . "/contact - Bog'lanish\n"
                . "/help - Yordam");
        }
    }
    
    // ============= LUNCH MANAGEMENT METHODS =============
    
    /**
     * Check if user is admin in any group and store their admin status
     */
    private function checkAndStoreUserAdminStatus($userId = null): bool
    {
        try {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $userId ?? ($message['from']['id'] ?? null);
            $chatId = $this->chat->chat_id;
            
            if (!$userId) {
                return false;
            }
            
            // If this is a group chat, check admin status
            if ($chatId < 0) {
                $isAdmin = $this->checkTelegramGroupAdmin($chatId, $userId);
                
                if ($isAdmin) {
                    // Store or update user as supervisor using their personal user ID
                    $this->storeUserAsSupervisor($userId, $message['from']);
                    return true;
                }
            } else {
                // This is a private chat, check if user is stored as supervisor
                return $this->isStoredSupervisor($userId);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if user is admin in Telegram group using direct API call
     */
    private function checkTelegramGroupAdmin($chatId, $userId): bool
    {
        try {
            $botToken = $this->bot->token;
            $url = "https://api.telegram.org/bot{$botToken}/getChatMember";
            
            $data = [
                'chat_id' => $chatId,
                'user_id' => $userId
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                
                if ($result && $result['ok'] && isset($result['result']['status'])) {
                    $status = $result['result']['status'];
                    return in_array($status, ['creator', 'administrator']);
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Store user as supervisor using their personal user ID
     */
    private function storeUserAsSupervisor($userId, $userInfo): void
    {
        UserManagement::updateOrCreate(
            ['telegram_user_id' => $userId],
            [
                'telegram_chat_id' => $userId, // Use user ID as chat ID for private chats
                'first_name' => $userInfo['first_name'] ?? null,
                'last_name' => $userInfo['last_name'] ?? null,
                'username' => $userInfo['username'] ?? null,
                'role' => UserManagement::ROLE_SUPERVISOR,
                'status' => UserManagement::STATUS_ACTIVE,
            ]
        );
    }
    
    /**
     * Check if user is stored as supervisor
     */
    private function isStoredSupervisor($userId): bool
    {
        return UserManagement::where('telegram_user_id', $userId)
            ->where('role', UserManagement::ROLE_SUPERVISOR)
            ->exists();
    }
    
    /**
     * Get or create user in management system
     */
    private function getOrCreateUser(): UserManagement
    {
        $chatId = $this->chat->chat_id;
        $userData = $this->getUserData();
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        // Check admin status and update if needed
        $isAdmin = $this->checkAndStoreUserAdminStatus($userId);
        
        \Log::info('User lookup details', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'is_admin' => $isAdmin,
            'chat_type' => $chatId < 0 ? 'group' : 'private'
        ]);
        
        // Find existing user by user_id or chat_id
        $user = null;
        if ($userId) {
            // Always prioritize user_id lookup first
            $user = UserManagement::where('telegram_user_id', $userId)->first();
            \Log::info('User lookup by user_id', [
                'user_id' => $userId,
                'found' => $user ? true : false,
                'role' => $user ? $user->role : null
            ]);
        }
        
        // If no user found by user_id, and this is a private chat, try chat_id lookup
        if (!$user && $chatId > 0) {
            $user = UserManagement::where('telegram_chat_id', $chatId)->first();
            \Log::info('User lookup by chat_id (private)', [
                'chat_id' => $chatId,
                'found' => $user ? true : false,
                'role' => $user ? $user->role : null
            ]);
        }
        
        // For group chats, if we found a user by user_id but they don't have group chat access,
        // we still want to use their role from private chat data
        if ($chatId < 0 && $user && $user->telegram_chat_id != $chatId) {
            // User exists but might not have group chat record
            // Use their existing role but don't update chat_id to group chat
            \Log::info('Group chat: Using existing user data', [
                'user_chat_id' => $user->telegram_chat_id,
                'group_chat_id' => $chatId,
                'role' => $user->role
            ]);
            return $user;
        }
        
        if ($user) {
            // Update existing user
            $needsUpdate = false;
            
            // Update chat_id if different - but check for duplicates first
            if ($user->telegram_chat_id != $chatId) {
                // Check if another user already has this chat_id
                $existingUser = UserManagement::where('telegram_chat_id', $chatId)
                    ->where('id', '!=', $user->id)
                    ->first();
                
                if (!$existingUser) {
                    $user->telegram_chat_id = $chatId;
                    $needsUpdate = true;
                }
            }
            
            // Update user_id if not set
            if (!$user->telegram_user_id && $userId) {
                $user->telegram_user_id = $userId;
                $needsUpdate = true;
            }
            
            // Update role based on admin status
            if ($isAdmin && $user->role !== UserManagement::ROLE_SUPERVISOR) {
                $user->role = UserManagement::ROLE_SUPERVISOR;
                $needsUpdate = true;
            } elseif (!$isAdmin && $chatId < 0 && $user->role === UserManagement::ROLE_SUPERVISOR) {
                // If user is no longer admin in group, but only if this is from group chat
                // Don't downgrade if this check is from private chat
                // $user->role = UserManagement::ROLE_USER;
                // $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $user->save();
            }
            
            return $user;
        }
        
        // Create new user
        $defaultRole = UserManagement::ROLE_USER;
        
        if ($isAdmin) {
            $defaultRole = UserManagement::ROLE_SUPERVISOR;
        }
        
        $user = UserManagement::create([
            'telegram_chat_id' => $chatId,
            'telegram_user_id' => $userId,
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'username' => $userData['username'],
            'phone_number' => $userData['phone_number'],
            'role' => $defaultRole,
            'status' => UserManagement::STATUS_ACTIVE,
        ]);
        
        return $user;
    }
    
    /**
     * Show available commands based on user role
     */
    private function showAvailableCommands(UserManagement $user): void
    {
        if ($user->isSupervisor()) {
            $this->showSupervisorCommands();
        } elseif ($user->isOperator()) {
            $this->showOperatorCommands();
        } else {
            $this->help();
        }
    }
    
    /**
     * Show supervisor commands
     */
    private function showSupervisorCommands(): void
    {
        $this->reply("👨‍💼 Supervisor buyruqlari:\n\n"
            . "📊 /lunch_status - Tushlik holati\n"
            . "📋 /lunch_schedule - Bugungi jadval\n"
            . "⚙️ /lunch_settings - Sozlamalar\n"
            . "👥 /operators - Operatorlar ro'yxati\n"
            . "🔄 /reorder_queue - Navbatni qayta tuzish\n"
            . "➡️ /next_group - Keyingi guruhga o'tish\n\n"
            . "Oddiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/help - Yordam");
    }
    
    /**
     * Show operator commands
     */
    private function showOperatorCommands(): void
    {
        $this->reply("👨‍💻 Operator buyruqlari:\n\n"
            . "🍽️ /my_lunch - Mening tushlik vaqtim\n"
            . "📅 /lunch_queue - Tushlik navbati\n"
            . "✅ /lunch_start - Tushlikka chiqdim\n"
            . "🔙 /lunch_end - Tushlikdan qaytdim\n\n"
            . "Oddiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/help - Yordam");
    }
    
    // ============= SUPERVISOR COMMANDS =============
    
    /**
     * Show lunch status
     */
    public function lunch_status(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->reply("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $scheduleService = new LunchScheduleService();
        $schedules = $scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            $this->reply("📊 Bugun uchun tushlik jadvali mavjud emas.\n\n"
                . "Jadval yaratish uchun /lunch_schedule buyrug'ini ishlating.");
            return;
        }
        
        $message = "📊 Bugungi tushlik holati:\n\n";
        
        foreach ($schedules as $schedule) {
            $stats = $scheduleService->getScheduleStats($schedule);
            
            $currentOperators = $scheduleService->getCurrentGroupOperators($schedule);
            $onLunchBreak = LunchBreak::today()->active()->count();
            
            $message .= "🏢 {$stats['work_shift']}:\n";
            $message .= "👥 Jami operatorlar: {$stats['total_operators']}\n";
            $message .= "📍 Hozirgi guruh: {$stats['current_group_number']}/{$stats['total_groups']}\n";
            $message .= "🍽️ Tushlikda: {$onLunchBreak} nafar\n";
            
            if ($currentOperators->isNotEmpty()) {
                $message .= "\n👤 Hozirgi guruh:\n";
                foreach ($currentOperators as $operator) {
                    $status = $operator->isOnLunchBreak() ? "🍽️" : "💻";
                    $message .= "  {$status} {$operator->full_name}\n";
                }
            }
            
            $message .= "\n";
        }
        
        $this->reply($message);
    }
    
    /**
     * Show today's lunch schedule
     */
    public function lunch_schedule(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->reply("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $shifts = WorkShift::active()->get();
        
        if ($shifts->isEmpty()) {
            $this->reply("❌ Faol ish smenalari mavjud emas.");
            return;
        }
        
        $scheduleService = new LunchScheduleService();
        
        $message = "📋 Bugungi tushlik jadvali:\n\n";
        
        foreach ($shifts as $shift) {
            $schedule = $scheduleService->createDailySchedule($shift);
            $operators = $scheduleService->getCurrentGroupOperators($schedule);
            
            $message .= "🏢 {$shift->name} ({$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')}):\n";
            $message .= "🍽️ Tushlik vaqti: {$shift->lunch_start_time->format('H:i')} - {$shift->lunch_end_time->format('H:i')}\n";
            $message .= "👥 Har guruhda: {$shift->max_lunch_operators} nafar\n\n";
            
            if ($operators->isNotEmpty()) {
                $message .= "Hozirgi guruh:\n";
                foreach ($operators as $operator) {
                    $message .= "👤 {$operator->full_name}\n";
                }
            } else {
                $message .= "❌ Hozirgi guruhda operatorlar yo'q\n";
            }
            
            $message .= "\n";
        }
        
        $this->reply($message);
    }
    
    /**
     * Move to next group
     */
    public function next_group(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->sendDirectMessage("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $scheduleService = new LunchScheduleService();
        $schedules = $scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            $this->sendDirectMessage("❌ Bugun uchun jadval mavjud emas.");
            return;
        }
        
        $moved = false;
        foreach ($schedules as $schedule) {
            if ($scheduleService->moveToNextGroup($schedule)) {
                $moved = true;
                $nextOperators = $scheduleService->getCurrentGroupOperators($schedule);
                
                $message = "✅ {$schedule->workShift->name} uchun keyingi guruhga o'tildi!\n\n";
                
                if ($nextOperators->isNotEmpty()) {
                    $message .= "👥 Keyingi guruh:\n";
                    foreach ($nextOperators as $operator) {
                        $message .= "👤 {$operator->full_name}\n";
                    }
                } else {
                    $message .= "❌ Keyingi guruhda operatorlar yo'q";
                }
                
                $this->sendDirectMessage($message);
            }
        }
        
        if (!$moved) {
            $this->sendDirectMessage("ℹ️ Barcha guruhlar tugagan yoki keyingi guruh mavjud emas.");
        }
    }
    
    /**
     * Show lunch settings
     */
    public function lunch_settings(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->sendDirectMessage("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $shifts = WorkShift::active()->get();
        
        if ($shifts->isEmpty()) {
            $this->sendDirectMessage("❌ Faol ish smenalari mavjud emas.");
            return;
        }
        
        $message = "⚙️ Tushlik sozlamalari:\n\n";
        
        foreach ($shifts as $shift) {
            $message .= "🏢 {$shift->name}:\n";
            $message .= "📅 Ish vaqti: {$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')}\n";
            $message .= "🍽️ Tushlik vaqti: {$shift->lunch_start_time->format('H:i')} - {$shift->lunch_end_time->format('H:i')}\n";
            $message .= "👥 Maksimal operatorlar: {$shift->max_lunch_operators}\n";
            $message .= "⏱️ Tushlik davomiyligi: {$shift->lunch_duration_minutes} daqiqa\n";
            $message .= "📊 Status: " . ($shift->is_active ? '✅ Faol' : '❌ Nofaol') . "\n\n";
        }
        
        $message .= "ℹ️ Sozlamalarni o'zgartirish uchun admin panel dan foydalaning.";
        
        $this->sendDirectMessage($message);
    }
    
    /**
     * Show operators list
     */
    public function operators(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->sendDirectMessage("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $operators = UserManagement::operators()->get();
        $supervisors = UserManagement::supervisors()->get();
        
        $message = "👥 Foydalanuvchilar ro'yxati:\n\n";
        
        if ($supervisors->isNotEmpty()) {
            $message .= "👨‍💼 Supervisorlar:\n";
            foreach ($supervisors as $supervisor) {
                $status = $supervisor->status === 'active' ? '✅' : '❌';
                $message .= "  {$status} {$supervisor->full_name}";
                if ($supervisor->username) {
                    $message .= " (@{$supervisor->username})";
                }
                $message .= "\n";
            }
            $message .= "\n";
        }
        
        if ($operators->isNotEmpty()) {
            $message .= "👨‍💻 Operatorlar ({$operators->count()} nafar):\n";
            foreach ($operators as $operator) {
                $status = $operator->status === 'active' ? '✅' : '❌';
                $lunchStatus = $operator->isOnLunchBreak() ? '🍽️' : '💻';
                $message .= "  {$status}{$lunchStatus} {$operator->full_name}";
                if ($operator->username) {
                    $message .= " (@{$operator->username})";
                }
                if ($operator->work_shift_id) {
                    $shift = WorkShift::find($operator->work_shift_id);
                    if ($shift) {
                        $message .= " - {$shift->name}";
                    }
                }
                $message .= "\n";
            }
        } else {
            $message .= "❌ Hozircha operatorlar ro'yxatga olinmagan.\n";
        }
        
        $message .= "\n📝 Yangi operator qo'shish uchun ularni botga /start yuborishlarini so'rang.";
        
        $this->sendDirectMessage($message);
    }
    
    /**
     * Reorder lunch queue
     */
    public function reorder_queue(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isSupervisor()) {
            $this->sendDirectMessage("❌ Bu buyruq faqat supervisor uchun!");
            return;
        }
        
        $scheduleService = new LunchScheduleService();
        $schedules = $scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            $this->sendDirectMessage("❌ Bugun uchun jadval mavjud emas.\n\n"
                . "Jadval yaratish uchun /lunch_schedule buyrug'ini ishlating.");
            return;
        }
        
        $reordered = false;
        $message = "🔄 Navbat qayta tartibga solinmoqda...\n\n";
        
        foreach ($schedules as $schedule) {
            try {
                // Reset queue to beginning
                $schedule->current_group_position = 1;
                $schedule->save();
                
                // Get operators for this schedule
                $operators = $scheduleService->getCurrentGroupOperators($schedule);
                
                $message .= "✅ {$schedule->workShift->name} navbati qayta boshlandi\n";
                $message .= "📍 Hozirgi pozitsiya: {$schedule->current_group_position}\n";
                
                if ($operators->isNotEmpty()) {
                    $message .= "👥 Hozirgi guruh:\n";
                    foreach ($operators as $operator) {
                        $message .= "  👤 {$operator->full_name}\n";
                    }
                } else {
                    $message .= "❌ Hozirgi guruhda operatorlar yo'q\n";
                }
                
                $message .= "\n";
                $reordered = true;
                
            } catch (\Exception $e) {
                \Log::error('Error reordering queue', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage()
                ]);
                $message .= "❌ {$schedule->workShift->name} navbatini qayta tartibga solishda xatolik\n\n";
            }
        }
        
        if (!$reordered) {
            $message = "❌ Navbatni qayta tartibga solishda xatolik yuz berdi.";
        } else {
            $message .= "ℹ️ Barcha navbatlar 1-pozitsiyaga qaytarildi.";
        }
        
        $this->sendDirectMessage($message);
    }
    
    // ============= OPERATOR COMMANDS =============
    
    /**
     * Show operator's lunch time
     */
    public function my_lunch(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isOperator()) {
            $this->reply("❌ Bu buyruq faqat operatorlar uchun!");
            return;
        }
        
        $todayBreak = $user->lunchBreaks()->today()->first();
        
        if (!$todayBreak) {
            $this->reply("📅 Bugun sizga tushlik vaqti belgilanmagan.\n\n"
                . "Supervisor bilan bog'laning.");
            return;
        }
        
        $message = "🍽️ Sizning tushlik vaqtingiz:\n\n";
        $message .= "📅 Sana: " . $todayBreak->scheduled_start_time->format('d.m.Y') . "\n";
        $message .= "🕐 Vaqt: " . $todayBreak->scheduled_start_time->format('H:i') . " - " . $todayBreak->scheduled_end_time->format('H:i') . "\n";
        $message .= "⏱️ Davomiyligi: " . $todayBreak->getScheduledDurationInMinutes() . " daqiqa\n";
        $message .= "📊 Holati: " . $this->getStatusEmoji($todayBreak->status) . " " . $this->getStatusText($todayBreak->status) . "\n";
        
        if ($todayBreak->status === LunchBreak::STATUS_SCHEDULED && $todayBreak->scheduled_start_time->diffInMinutes(Carbon::now()) <= 5) {
            $message .= "\n⚠️ Tushlik vaqtingiz yaqinlashdi!";
            
            $keyboard = Keyboard::make()
                ->button('✅ Tushlikka chiqdim')->action('lunch_start')
                ->button('❌ Bekor qilish')->action('cancel_lunch');
                
            $this->chat->message($message)->keyboard($keyboard)->send();
            return;
        }
        
        if ($todayBreak->status === LunchBreak::STATUS_STARTED) {
            $keyboard = Keyboard::make()
                ->button('🔙 Tushlikdan qaytdim')->action('lunch_end');
                
            $this->chat->message($message)->keyboard($keyboard)->send();
            return;
        }
        
        $this->reply($message);
    }
    
    /**
     * Start lunch break
     */
    public function lunch_start(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isOperator()) {
            $this->reply("❌ Bu buyruq faqat operatorlar uchun!");
            return;
        }
        
        $activeLunch = $user->getCurrentLunchBreak();
        if ($activeLunch) {
            $this->reply("⚠️ Siz allaqachon tushlik tanaffusida ekansiz!");
            return;
        }
        
        $todayBreak = $user->lunchBreaks()->today()
            ->where('status', LunchBreak::STATUS_SCHEDULED)
            ->orWhere('status', LunchBreak::STATUS_REMINDED)
            ->first();
            
        if (!$todayBreak) {
            $this->reply("❌ Bugun sizga tushlik vaqti belgilanmagan.");
            return;
        }
        
        $todayBreak->startBreak();
        
        $message = "✅ Tushlik tanaffusi boshlandi!\n\n";
        $message .= "🕐 Boshlanish vaqti: " . Carbon::now()->format('H:i') . "\n";
        $message .= "⏰ Qaytish vaqti: " . $todayBreak->scheduled_end_time->format('H:i') . "\n";
        $message .= "\n🍽️ Yaxshi ishtaha!";
        
        $this->reply($message);
        
        // Notify supervisors
        $this->notifySupervisors("🍽️ {$user->full_name} tushlik tanaffusiga chiqdi.");
    }
    
    /**
     * End lunch break
     */
    public function lunch_end(): void
    {
        $user = $this->getOrCreateUser();
        
        if (!$user->isOperator()) {
            $this->reply("❌ Bu buyruq faqat operatorlar uchun!");
            return;
        }
        
        $activeLunch = $user->getCurrentLunchBreak();
        if (!$activeLunch) {
            $this->reply("❌ Siz hozir tushlik tanaffusida emassiz!");
            return;
        }
        
        $activeLunch->endBreak();
        
        $duration = $activeLunch->getDurationInMinutes();
        $message = "✅ Tushlik tanaffusi yakunlandi!\n\n";
        $message .= "🕐 Davomiyligi: {$duration} daqiqa\n";
        $message .= "💻 Ish jarayonini davom ettiring!";
        
        $this->reply($message);
        
        // Notify supervisors
        $this->notifySupervisors("💻 {$user->full_name} tushlik tanaffusidan qaytdi. ({$duration} daqiqa)");
    }
    
    // ============= HELPER METHODS =============
    
    private function getStatusEmoji(string $status): string
    {
        return match($status) {
            LunchBreak::STATUS_SCHEDULED => '📅',
            LunchBreak::STATUS_REMINDED => '⏰',
            LunchBreak::STATUS_STARTED => '🍽️',
            LunchBreak::STATUS_COMPLETED => '✅',
            LunchBreak::STATUS_MISSED => '❌',
            default => '❓'
        };
    }
    
    private function getStatusText(string $status): string
    {
        return match($status) {
            LunchBreak::STATUS_SCHEDULED => 'Rejalashtirilgan',
            LunchBreak::STATUS_REMINDED => 'Eslatma yuborilgan',
            LunchBreak::STATUS_STARTED => 'Tushlikda',
            LunchBreak::STATUS_COMPLETED => 'Yakunlangan',
            LunchBreak::STATUS_MISSED => 'O\'tkazib yuborilgan',
            default => 'Noma\'lum'
        };
    }
    
    private function notifySupervisors(string $message): void
    {
        $supervisors = UserManagement::supervisors()->get();
        
        foreach ($supervisors as $supervisor) {
            try {
                $this->bot->chat($supervisor->telegram_chat_id)->message($message)->send();
            } catch (\Exception $e) {
                // Log error but continue
            }
        }
    }
    
    /**
     * Send message directly using Telegram API
     */
    private function sendDirectMessage(string $message): void
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
            
            \Log::info('Sending message via direct API', [
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
            
            \Log::info('Direct API response', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200) // Log first 200 chars
            ]);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result && $result['ok']) {
                    \Log::info('Message sent successfully via direct API');
                } else {
                    \Log::error('Telegram API returned error', ['result' => $result]);
                }
            } else {
                \Log::error('HTTP error when sending message', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }
            
        } catch (\Exception $sendError) {
            \Log::error('Failed to send message via direct API', [
                'error' => $sendError->getMessage(),
                'chat_id' => $this->chat->chat_id
            ]);
            
            // Fallback to Telegraph methods
            try {
                if ($this->chat->chat_id < 0) {
                    $this->chat->message($message)->send();
                } else {
                    $this->reply($message);
                }
            } catch (\Exception $fallbackError) {
                \Log::error('Fallback send also failed', [
                    'error' => $fallbackError->getMessage()
                ]);
            }
        }
    }
}
