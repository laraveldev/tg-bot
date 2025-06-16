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
use Illuminate\Support\Facades\Log;
use Exception;

class Handler extends WebhookHandler
{
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

    public function start(): void
    {
        $this->initializeServices();
        $this->startService->handleStart();
    }

    public function info(): void
    {
        $this->initializeServices();
        $this->infoService->handleInfo();
    }

    public function about(): void
    {
        $this->initializeServices();
        $this->infoService->handleAbout();
    }

    public function contact(): void
    {
        $this->initializeServices();
        $this->infoService->handleContact();
    }

    public function help(): void
    {
        $this->initializeServices();
        $this->helpService->handleHelp();
    }

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
