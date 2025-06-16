<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use App\Models\UserManagement;

class StartService
{
    private TelegramUserService $userService;
    private MessageService $messageService;
    private WebhookHandler $handler;
    
    public function __construct(TelegramUserService $userService, MessageService $messageService, WebhookHandler $handler)
    {
        $this->userService = $userService;
        $this->messageService = $messageService;
        $this->handler = $handler;
    }
    
    /**
     * Handle start command
     */
    public function handleStart(): void
    {
        // Update user data
        $this->userService->updateUserData($this->handler->getChatId());

        // Send welcome message
        $this->handler->sendReply("👋 Salom! Botga xush kelibsiz!");

        // Create contact request button
        $contactButton = ReplyButton::make('📱 Raqam yuborish')->requestContact();
        
        // Create keyboard with the contact button
        $keyboard = ReplyKeyboard::make()
            ->row([$contactButton])
            ->resize()
            ->oneTime();

        // Send message with keyboard
        $this->messageService->sendMessageWithKeyboard(
            "📞 Iltimos, telefon raqamingizni yuboring:",
            $keyboard
        );
    }
    
    /**
     * Handle contact received
     */
    public function handleContactReceived(array $contact): void
    {
        // Update user data including phone number
        $message = request()->input('message') ?? request()->input('edited_message');
        $from = $message['from'] ?? null;
        $userId = $from['id'] ?? null;

        $this->userService->updateContact($this->handler->getChatId(), $contact, $from);
        
        // Get or create user to check their role
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);

        // Send confirmation message
        $confirmationMessage = "✅ Raqamingiz saqlandi: " . $contact['phone_number'] . "\n\n"
            . "🎉 Siz endi barcha buyruqlardan foydalanishingiz mumkin!\n\n"
            . "💡 /help buyrug'ini bosib barcha imkoniyatlarni ko'ring.";
            
        // Send confirmation message and role-based keyboard
        $this->messageService->sendMessage($confirmationMessage);
        
        // Show role-based keyboard automatically
        if ($user->isSupervisor()) {
            $keyboard = $this->messageService->getSupervisorKeyboard();
            $helpMessage = "👨‍💼 Supervisor sifatida sizga quyidagi buyruqlar mavjud:";
        } else {
            $keyboard = $this->messageService->getOperatorKeyboard();
            $helpMessage = "👨‍💻 Operator sifatida sizga quyidagi buyruqlar mavjud:";
        }
        
        $this->messageService->sendMessageWithKeyboard($helpMessage, $keyboard);
    }
}

