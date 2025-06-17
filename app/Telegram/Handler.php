<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;
use App\Services\Telegram\TelegramUserService;
use App\Services\Telegram\MessageService;
use App\Services\Telegram\CommandHandlerService;
use App\Services\Telegram\LunchCommandHandler;
use App\Services\Telegram\StartService;
use App\Services\Telegram\ButtonCommandService;
use App\Services\Telegram\AdminCommandService;
use App\Services\Telegram\StatisticsService;
use App\Services\Telegram\SettingsCommandService;
use App\Services\Telegram\HelpService;
use App\Services\Telegram\InfoCommandService;
use App\Services\Telegram\GroupMembersService;
use App\Services\LunchManagement\LunchScheduleService;
use App\Models\UserManagement;
use Illuminate\Support\Facades\Log;
use Exception;

class Handler extends WebhookHandler
{
    use \App\Telegram\Commands\StartCommand;
    private TelegramUserService $userService;
    private MessageService $messageService;
    private CommandHandlerService $commandService;
    private LunchCommandHandler $lunchHandler;
    private LunchScheduleService $scheduleService;
    private StartService $startService;
    private ButtonCommandService $buttonService;
    private AdminCommandService $adminService;
    private StatisticsService $statisticsService;
    private SettingsCommandService $settingsService;
    private HelpService $helpService;
    private InfoCommandService $infoService;
    private GroupMembersService $groupMembersService;

    public function __construct()
    {
        $this->userService = new TelegramUserService();
        $this->scheduleService = new LunchScheduleService();
    }

    /**
     * Initialize services that require bot and chat instances
     */
    private function initializeServices(): void
    {
        if (!isset($this->messageService)) {
            // Get chat from request data since protected property can't be accessed
            $chatId = $this->getChatId();
            $chat = \DefStudio\Telegraph\Models\TelegraphChat::where('chat_id', $chatId)->first();
            
            $this->messageService = new MessageService($this->bot, $chat);
            $this->commandService = new CommandHandlerService();
            $this->lunchHandler = new LunchCommandHandler($this->scheduleService, $this->messageService);
            $this->startService = new StartService($this->userService, $this->messageService, $this);
            $this->buttonService = new ButtonCommandService($this->userService, $this);
            $this->adminService = new AdminCommandService($this->userService, $this);
            $this->statisticsService = new StatisticsService($this->userService, $this);
            $this->settingsService = new SettingsCommandService($this->userService, $this->lunchHandler, $this);
            $this->helpService = new HelpService($this->userService, $this->messageService, $this);
            $this->infoService = new InfoCommandService($this->userService, $this->messageService, $this->bot, $chat);
            $this->groupMembersService = new GroupMembersService();
        }
    }
    
    /**
     * Get chat ID from request
     */
    public function getChatId(): string
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        return (string) $message['chat']['id'];
    }
    
    /**
     * Public reply method for services
     */
    public function sendReply(string $message): void
    {
        $this->reply($message);
    }

    // public function start(): void
    // {
    //     $this->initializeServices();
    //     $this->startService->handleStart();
    // }

    // public function info(): void
    // {
    //     $this->initializeServices();
    //     $this->infoService->handleInfo();
    // }

    // public function about(): void
    // {
    //     $this->initializeServices();
    //     $this->infoService->handleAbout();
    // }

    // public function contact(): void
    // {
    //     $this->initializeServices();
    //     $this->infoService->handleContact();
    // }

    // public function help(): void
    // {
    //     $this->initializeServices();
    //     $this->helpService->handleHelp();
    // }

    public function onContactReceived(array $contact): void
    {
        $this->initializeServices();
        $this->startService->handleContactReceived($contact);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $this->initializeServices();
        
        // Update user data from every message
        $this->userService->updateUserData($this->getChatId());
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $chatId = $this->getChatId();
        
        // For group messages, automatically register any new users as operators
        if (intval($chatId) < 0 && $userId) {
            $this->autoRegisterGroupMember($userId, $message['from'] ?? []);
        }
        
        // Check if user is registered in management system
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        // Process button command through service
        $buttonText = trim($text->toString());
        $command = $this->buttonService->processButtonCommand($buttonText);
        
        if ($command) {
            // Check permissions
            if (!$this->buttonService->checkPermission($command, $user)) {
                return;
            }
            
            // Call the appropriate method
            if (method_exists($this, $command)) {
                $this->$command();
                return;
            }
        }
        
        // Handle chat message through service (this already replies)
        $this->commandService->handleChatMessage($user, $this);
    }
    
    /**
     * Auto-register group members as operators when they send messages
     */
    private function autoRegisterGroupMember(int $userId, array $userInfo): void
    {
        try {
            $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
            
            if (!$existingUser) {
                // Skip bots
                if ($userInfo['is_bot'] ?? false) {
                    return;
                }
                
                $firstName = $userInfo['first_name'] ?? null;
                $lastName = $userInfo['last_name'] ?? null;
                $username = $userInfo['username'] ?? null;
                
                // Ensure we have at least a first name
                if (!$firstName) {
                    if ($lastName) {
                        $firstName = $lastName;
                        $lastName = null;
                    } elseif ($username) {
                        $firstName = $username;
                    } else {
                        $firstName = 'User ' . $userId;
                    }
                }
                
                // Check if they're admin in this group
                $adminService = new \App\Services\Telegram\AdminService();
                $isAdmin = $adminService->checkAndStoreUserAdminStatus(intval($this->getChatId()), $userId);
                
                $role = $isAdmin ? UserManagement::ROLE_SUPERVISOR : UserManagement::ROLE_OPERATOR;
                
                UserManagement::create([
                    'telegram_chat_id' => $userId,
                    'telegram_user_id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'role' => $role,
                    'status' => UserManagement::STATUS_ACTIVE,
                    'is_available_for_lunch' => $role === UserManagement::ROLE_OPERATOR,
                ]);
                
                \Illuminate\Support\Facades\Log::info('Auto-registered group member from message', [
                    'user_id' => $userId,
                    'name' => trim($firstName . ' ' . ($lastName ?: '')),
                    'username' => $username,
                    'role' => $role,
                    'is_admin' => $isAdmin
                ]);
                
                // Send welcome message to new operators
                if ($role === UserManagement::ROLE_OPERATOR) {
                    try {
                        $bot = \DefStudio\Telegraph\Models\TelegraphBot::first();
                        if ($bot) {
                            $welcomeMessage = "🎉 Salom {$firstName}!\n\n";
                            $welcomeMessage .= "Siz avtomatik ravishda tushlik tizimiga operator sifatida qo'shildingiz.\n\n";
                            $welcomeMessage .= "🍽️ Tushlik buyruqlaridan foydalanish uchun botga shaxsan /start yuboring.";
                            
                            $bot->chat($userId)->message($welcomeMessage)->send();
                        }
                    } catch (\Exception $e) {
                        // Ignore if we can't send private message
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to auto-register group member', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle chat member status updates (admin promotion/demotion)
     */
    public function handleChatMemberUpdated(): void
    {
        $this->initializeServices();
        
        try {
            $update = request()->input('my_chat_member') ?? request()->input('chat_member');
            
            if (!$update) {
                return;
            }
            
            $chatId = $update['chat']['id'] ?? null;
            $userId = $update['from']['id'] ?? null;
            $newStatus = $update['new_chat_member']['status'] ?? null;
            $oldStatus = $update['old_chat_member']['status'] ?? null;
            
            if (!$chatId || !$userId || !$newStatus || !$oldStatus) {
                return;
            }
            
            // Only handle group chats
            if ($chatId >= 0) {
                return;
            }
            
            // Check if admin status changed
            $wasAdmin = in_array($oldStatus, ['creator', 'administrator']);
            $isAdmin = in_array($newStatus, ['creator', 'administrator']);
            
            if ($wasAdmin !== $isAdmin) {
                $userInfo = $update['new_chat_member']['user'] ?? [];
                
                // Skip bots
                if ($userInfo['is_bot'] ?? false) {
                    return;
                }
                
                $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
                
                if ($isAdmin && (!$existingUser || $existingUser->role !== UserManagement::ROLE_SUPERVISOR)) {
                    // Promote to supervisor
                    UserManagement::updateOrCreate(
                        ['telegram_user_id' => $userId],
                        [
                            'telegram_chat_id' => $userId,
                            'first_name' => $userInfo['first_name'] ?? null,
                            'last_name' => $userInfo['last_name'] ?? null,
                            'username' => $userInfo['username'] ?? null,
                            'role' => UserManagement::ROLE_SUPERVISOR,
                            'status' => UserManagement::STATUS_ACTIVE,
                        ]
                    );
                    
                    \Illuminate\Support\Facades\Log::info('User promoted to supervisor via webhook', [
                        'user_id' => $userId,
                        'chat_id' => $chatId,
                        'username' => $userInfo['username'] ?? 'N/A',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]);
                    
                } elseif (!$isAdmin && $existingUser && $existingUser->role === UserManagement::ROLE_SUPERVISOR) {
                    // Demote to operator
                    $existingUser->update([
                        'role' => UserManagement::ROLE_OPERATOR,
                        'first_name' => $userInfo['first_name'] ?? $existingUser->first_name,
                        'last_name' => $userInfo['last_name'] ?? $existingUser->last_name,
                        'username' => $userInfo['username'] ?? $existingUser->username,
                    ]);
                    
                    \Illuminate\Support\Facades\Log::info('User demoted to operator via webhook', [
                        'user_id' => $userId,
                        'chat_id' => $chatId,
                        'username' => $userInfo['username'] ?? 'N/A',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Chat member update handling failed', [
                'error' => $e->getMessage(),
                'request_data' => request()->all()
            ]);
        }
    }

    /**
     * Handle my_chat_member updates (when bot's status changes or when other members are promoted/demoted)
     */
    public function handleMyChatMemberUpdated(): void
    {
        $this->handleChatMemberUpdated();
    }
    
    /**
     * Handle regular chat_member updates
     */
    public function handleChatMember(): void 
    {
        $this->handleChatMemberUpdated();
    }
    
    protected function handleUnknownCommand(Stringable $text): void
    {
        $this->initializeServices();
        
        // Get command without slash
        $command = ltrim($text->toString(), '/');
        
        // Handle explicit commands that might not be registered
        if ($command === 'contact') {
            $this->contact();
            return;
        }
        
        if ($command === 'about') {
            $this->about();
            return;
        }
        
        // Check if it's a settings command
        if ($this->settingsService->handleSettingsCommand($command)) {
            return;
        }
        
        // Check if it's an admin command
        if ($this->adminService->handleAdminCommand($command)) {
            return;
        }
        
        // Handle unknown commands through service
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $this->commandService->handleUnknownCommand($text->toString(), $user, $this);
    }
    
    
    
    // ============= SUPERVISOR COMMANDS =============
    
    /**
     * Show lunch status
     */
    public function lunch_status(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleLunchStatus($user);
        $this->reply($response);
    }
    
    /**
     * Show today's lunch schedule
     */
    public function lunch_schedule(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleLunchSchedule($user);
        $this->reply($response);
    }
    
    /**
     * Move to next group
     */
    public function next_group(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleNextGroup($user);
        $this->messageService->sendMessage($response);
    }
    
    /**
     * Show lunch settings
     */
    public function lunch_settings(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleLunchSettings($user);
        $this->messageService->sendMessage($response);
    }
    
    /**
     * Show operators list
     */
    public function operators(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleOperators($user);
        $this->messageService->sendMessage($response);
    }
    
    /**
     * Reorder lunch queue
     */
    public function reorder_queue(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleReorderQueue($user);
        $this->messageService->sendMessage($response);
    }
    
    /**
     * Show group statistics
     */
    public function group_statistics(): void
    {
        $this->initializeServices();
        $this->statisticsService->handleGroupStatistics();
    }
    
    
    // ============= OPERATOR COMMANDS =============
    
    /**
     * Show operator's lunch time
     */
    public function my_lunch(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $result = $this->lunchHandler->handleMyLunch($user);
        
        if ($result['keyboard']) {
            $this->reply($result['message']); // Use reply method instead
        } else {
            $this->reply($result['message']);
        }
    }
    
    /**
     * Start lunch break
     */
    public function lunch_start(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleLunchStart($user);
        $this->reply($response);
    }
    
    /**
     * End lunch break
     */
    public function lunch_end(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        $response = $this->lunchHandler->handleLunchEnd($user);
        $this->reply($response);
    }
    
    /**
     * Show lunch queue (for operators)
     */
    public function lunch_queue(): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->getChatId(), $userId);
        
        if (!$user->isOperator()) {
            $this->reply("❌ Bu buyruq faqat operatorlar uchun!");
            return;
        }
        
        // For now, redirect to lunch status or my_lunch
        $this->my_lunch();
    }

}
