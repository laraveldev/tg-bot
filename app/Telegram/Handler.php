<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use App\Services\Telegram\TelegramUserService;
use App\Services\Telegram\MessageService;
use App\Services\Telegram\CommandHandlerService;
use App\Services\Telegram\LunchCommandHandler;
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
            $this->messageService = new MessageService($this->bot, $this->chat);
            $this->commandService = new CommandHandlerService();
            $this->lunchHandler = new LunchCommandHandler($this->scheduleService, $this->messageService);
        }
    }

    public function start(): void
    {
        $this->initializeServices();
        
        // Update user data
        $this->userService->updateUserData($this->chat->id);

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
        $this->initializeServices();
        
        $userData = $this->userService->getUserData($this->chat->id);
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        // Get user from management system
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        $message = $this->messageService->getUserInfoMessage($userData, $userId, $user);
        $this->reply($message);
    }

    public function about(): void
    {
        $this->initializeServices();
        $message = $this->messageService->getAboutMessage();
        $this->reply($message);
    }

    public function contact(): void
    {
        $this->initializeServices();
        $message = $this->messageService->getContactMessage();
        $this->messageService->sendMessage($message);
    }

    public function help(): void
    {
        $this->initializeServices();
        
        try {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $message['from']['id'] ?? null;
            
            $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
            $helpMessage = $this->messageService->getHelpMessage($user);
            
            $this->messageService->sendMessage($helpMessage);
            
        } catch (Exception $e) {
            Log::error('Help command error', [
                'error' => $e->getMessage(),
                'chat_id' => $this->chat->chat_id ?? 'unknown'
            ]);
            
            $this->reply("❌ Xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.");
        }
    }

    public function onContactReceived(array $contact): void
    {
        $this->initializeServices();
        
        // Update user data including phone number
        $message = request()->input('message') ?? request()->input('edited_message');
        $from = $message['from'] ?? null;

        $this->userService->updateContact($this->chat->id, $contact, $from);

        // Send confirmation message and remove keyboard immediately
        $confirmationMessage = "✅ Raqamingiz saqlandi: " . $contact['phone_number'] . "\n\n"
            . "🎉 Siz endi barcha buyruqlardan foydalanishingiz mumkin!\n\n"
            . "📋 Asosiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/about - Bot haqida\n"
            . "/contact - Bog'lanish\n"
            . "/help - Yordam";
            
        // Use direct reply with keyboard removal
        $this->chat
            ->message($confirmationMessage)
            ->removeReplyKeyboard()
            ->send();
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $this->initializeServices();
        
        // Update user data from every message
        $this->userService->updateUserData($this->chat->id);
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        // Check if user is registered in management system
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        $response = $this->lunchHandler->handleReorderQueue($user);
        $this->messageService->sendMessage($response);
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        $result = $this->lunchHandler->handleMyLunch($user);
        
        if ($result['keyboard']) {
            $this->chat->message($result['message'])->keyboard($result['keyboard'])->send();
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
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
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        if (!$user->isOperator()) {
            $this->reply("❌ Bu buyruq faqat operatorlar uchun!");
            return;
        }
        
        // For now, redirect to lunch status or my_lunch
        $this->my_lunch();
    }

}
