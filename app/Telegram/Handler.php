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
        try {
            $this->initializeServices();
            
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $message['from']['id'] ?? null;
            
            $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
            
            Log::info('Help command started', [
                'user_id' => $userId,
                'user_role' => $user->role,
                'is_supervisor' => $user->isSupervisor()
            ]);
            
            // Simple help message first
            $helpText = "🆘 Yordam - Mavjud buyruqlar:\n\n";
            $helpText .= "📋 Asosiy buyruqlar:\n";
            $helpText .= "/start - Botni boshlash\n";
            $helpText .= "/info - Ma'lumotlar\n";
            $helpText .= "/help - Yordam\n\n";
            
            if ($user->isSupervisor()) {
                $helpText .= "👨‍💼 Supervisor buyruqlari:\n";
                $helpText .= "/lunch_status - Tushlik holati\n";
                $helpText .= "/lunch_schedule - Jadval\n";
                $helpText .= "/operators - Operatorlar\n";
            }
            
            // First send simple text message
            $this->reply($helpText);
            
            // Then try to send keyboard
            if ($user->isSupervisor()) {
                $keyboard = $this->messageService->getSupervisorKeyboard();
                $this->chat->message("👨‍💼 Supervisor paneli:")->replyKeyboard($keyboard)->send();
            } else {
                $keyboard = $this->messageService->getOperatorKeyboard();
                $this->chat->message("👨‍💻 Operator paneli:")->replyKeyboard($keyboard)->send();
            }
            
            Log::info('Help command completed successfully');
            
        } catch (Exception $e) {
            Log::error('Help command failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            // Simple fallback
            $this->reply("🆘 Yordam:\n\n/start - Botni boshlash\n/info - Ma'lumotlar\n/help - Yordam");
        }
    }

    public function onContactReceived(array $contact): void
    {
        $this->initializeServices();
        
        // Update user data including phone number
        $message = request()->input('message') ?? request()->input('edited_message');
        $from = $message['from'] ?? null;
        $userId = $from['id'] ?? null;

        $this->userService->updateContact($this->chat->id, $contact, $from);
        
        // Get or create user to check their role
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);

        // Send confirmation message
        $confirmationMessage = "✅ Raqamingiz saqlandi: " . $contact['phone_number'] . "\n\n"
            . "🎉 Siz endi barcha buyruqlardan foydalanishingiz mumkin!\n\n"
            . "💡 /help buyrug'ini bosib barcha imkoniyatlarni ko'ring.";
            
        // First remove old keyboard
        $this->chat
            ->message($confirmationMessage)
            ->removeReplyKeyboard()
            ->send();
            
        // Wait a moment then show appropriate keyboard
        sleep(1);
        
        // Show role-based keyboard automatically
        if ($user->isSupervisor()) {
            $keyboard = $this->messageService->getSupervisorKeyboard();
            $helpMessage = "👨‍💼 Supervisor sifatida sizga quyidagi buyruqlar mavjud:";
        } else {
            $keyboard = $this->messageService->getOperatorKeyboard();
            $helpMessage = "👨‍💻 Operator sifatida sizga quyidagi buyruqlar mavjud:";
        }
        
        $this->chat
            ->message($helpMessage)
            ->replyKeyboard($keyboard)
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
        
        // Check if this is a button text that corresponds to a command
        $buttonText = trim($text->toString());
        Log::info('Button text received', [
            'text' => $buttonText, 
            'length' => strlen($buttonText),
            'bytes' => bin2hex($buttonText)
        ]);
        
        $commandMapping = [
            // Supervisor commands
            '📊 Tushlik Holati' => 'lunch_status',
            '📋 Jadval' => 'lunch_schedule',
            '⚙️ Sozlamalar' => 'lunch_settings',
            '👥 Operatorlar' => 'operators',
            '🔄 Navbat Tuzish' => 'reorder_queue',
            '➡️ Keyingi Guruh' => 'next_group',
            // Operator commands
            '🍽️ Mening Tushligim' => 'my_lunch',
            '📅 Tushlik Navbati' => 'lunch_queue',
            '✅ Tushlikka Chiqdim' => 'lunch_start',
            '🔙 Tushlikdan Qaytdim' => 'lunch_end',
            // Common commands
            'ℹ️ Ma\'lumot' => 'info',
            '❓ Yordam' => 'help',
            '📞 Aloqa' => 'contact',
            'ℹ️ Bot Haqida' => 'about'
        ];
        
        // Log all available mappings for debugging
        Log::info('Available command mappings', ['mappings' => array_keys($commandMapping)]);
        
        if (isset($commandMapping[$buttonText])) {
            $methodName = $commandMapping[$buttonText];
            Log::info('Button mapped to command', ['button' => $buttonText, 'command' => $methodName]);
            
            // Check permissions for supervisor commands
            $supervisorCommands = ['lunch_status', 'lunch_schedule', 'lunch_settings', 'operators', 'reorder_queue', 'next_group'];
            
            if (in_array($methodName, $supervisorCommands) && !$user->isSupervisor()) {
                $this->reply("❌ Bu buyruq faqat supervisor lar uchun!");
                return;
            }
            
            // Call the appropriate method
            if (method_exists($this, $methodName)) {
                $this->$methodName();
                return;
            }
        }
        
        // If no exact match, try partial matching for common words
        foreach ($commandMapping as $buttonKey => $command) {
            if (strpos($buttonText, 'Tushlik') !== false && strpos($buttonKey, 'Tushlik') !== false) {
                Log::info('Partial match found', ['button' => $buttonText, 'matched_key' => $buttonKey, 'command' => $command]);
                if (method_exists($this, $command)) {
                    $this->$command();
                    return;
                }
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
        
        // New lunch management commands
        if (str_starts_with($command, 'set_lunch_time')) {
            $this->handleSetLunchTime($command);
            return;
        }
        
        if (str_starts_with($command, 'set_lunch_duration')) {
            $this->handleSetLunchDuration($command);
            return;
        }
        
        if (str_starts_with($command, 'set_max_operators')) {
            $this->handleSetMaxOperators($command);
            return;
        }
        
        // Special command for testing - make user supervisor
        if ($command === 'make_me_supervisor') {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $message['from']['id'] ?? null;
            
            if ($userId) {
                // Update user to supervisor
                $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
                $user->role = 'supervisor';
                $user->save();
                
                $this->reply("✅ Siz supervisor sifatida belgilandi! /help ni bosib tekshiring.");
                return;
            }
        }
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        $this->commandService->handleUnknownCommand($text->toString(), $user, $this);
    }
    
    /**
     * Handle set lunch time command
     */
    private function handleSetLunchTime(string $command): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetLunchTime($user, $parts);
        $this->reply($response);
    }
    
    /**
     * Handle set lunch duration command
     */
    private function handleSetLunchDuration(string $command): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetLunchDuration($user, $parts);
        $this->reply($response);
    }
    
    /**
     * Handle set max operators command
     */
    private function handleSetMaxOperators(string $command): void
    {
        $this->initializeServices();
        
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->chat->id, $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetMaxOperators($user, $parts);
        $this->reply($response);
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
