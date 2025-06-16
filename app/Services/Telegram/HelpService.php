<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\UserManagement;
use Illuminate\Support\Facades\Log;
use Exception;

class HelpService
{
    private TelegramUserService $userService;
    private MessageService $messageService;
    private WebhookHandler $handler;
    
    public function __construct(
        TelegramUserService $userService, 
        MessageService $messageService, 
        WebhookHandler $handler
    ) {
        $this->userService = $userService;
        $this->messageService = $messageService;
        $this->handler = $handler;
    }
    
    /**
     * Handle help command
     */
    public function handleHelp(): void
    {
        try {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $message['from']['id'] ?? null;
            
            // Get chat ID from request since chat property is protected
            $message = request()->input('message') ?? request()->input('edited_message');
            $chatId = (string) $message['chat']['id'];
            
            $user = $this->userService->getOrCreateUser($chatId, $userId);
            
            // Get help message from MessageService
            $helpText = $this->messageService->getHelpMessage($user);
            
            // Send help message using MessageService
            $this->messageService->sendMessage($helpText);
            
            // Send appropriate keyboard message
            if ($user->isSupervisor()) {
                $keyboard = $this->messageService->getSupervisorKeyboard();
                $this->messageService->sendMessageWithKeyboard("👨‍💼 Supervisor paneli:", $keyboard);
            } elseif ($user->isOperator()) {
                $keyboard = $this->messageService->getOperatorKeyboard();
                $this->messageService->sendMessageWithKeyboard("👨‍💻 Operator paneli:", $keyboard);
            }
            
            Log::info('Help command completed successfully', [
                'user_id' => $userId,
                'user_role' => $user->role
            ]);
            
        } catch (Exception $e) {
            Log::error('Help command failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            // Simple fallback using MessageService
            try {
                $this->messageService->sendMessage("🆘 Yordam:\n\n/start - Botni boshlash\n/info - Ma'lumotlar\n/help - Yordam");
            } catch (Exception $fallbackError) {
                Log::error('Fallback message also failed', ['error' => $fallbackError->getMessage()]);
            }
        }
    }
}

