<?php

namespace App\Services\Telegram;

use App\Services\Telegram\TelegramUserService;
use App\Services\Telegram\MessageService;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;

class InfoCommandService
{
    private TelegramUserService $userService;
    private MessageService $messageService;
    private TelegraphBot $bot;
    private TelegraphChat $chat;
    
    public function __construct(
        TelegramUserService $userService,
        MessageService $messageService,
        TelegraphBot $bot,
        TelegraphChat $chat
    ) {
        $this->userService = $userService;
        $this->messageService = $messageService;
        $this->bot = $bot;
        $this->chat = $chat;
    }
    
    /**
     * Handle info command
     */
    public function handleInfo(): void
    {
        $userData = $this->userService->getUserData($this->chat->chat_id);
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        // Get user from management system
        $user = $this->userService->getOrCreateUser($this->chat->chat_id, $userId);
        
        $message = $this->messageService->getUserInfoMessage($userData, $userId, $user);
        $this->chat->message($message)->send();
    }
    
    /**
     * Handle about command
     */
    public function handleAbout(): void
    {
        $message = $this->messageService->getAboutMessage();
        $this->chat->message($message)->send();
    }
    
    /**
     * Handle contact command
     */
    public function handleContact(): void
    {
        $message = $this->messageService->getContactMessage();
        $this->chat->message($message)->send();
    }
}

