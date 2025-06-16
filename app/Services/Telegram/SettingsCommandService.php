<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Services\LunchManagement\LunchScheduleService;

class SettingsCommandService
{
    private TelegramUserService $userService;
    private LunchCommandHandler $lunchHandler;
    private WebhookHandler $handler;
    
    public function __construct(
        TelegramUserService $userService, 
        LunchCommandHandler $lunchHandler, 
        WebhookHandler $handler
    ) {
        $this->userService = $userService;
        $this->lunchHandler = $lunchHandler;
        $this->handler = $handler;
    }
    
    /**
     * Handle set lunch time command
     */
    public function handleSetLunchTime(string $command): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetLunchTime($user, $parts);
        $this->handler->sendReply($response);
    }
    
    /**
     * Handle set lunch duration command
     */
    public function handleSetLunchDuration(string $command): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetLunchDuration($user, $parts);
        $this->handler->sendReply($response);
    }
    
    /**
     * Handle set max operators command
     */
    public function handleSetMaxOperators(string $command): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
        
        // Parse command parameters
        $parts = explode(' ', $command);
        array_shift($parts); // Remove command name
        
        $response = $this->lunchHandler->handleSetMaxOperators($user, $parts);
        $this->handler->sendReply($response);
    }
    
    /**
     * Check if command is settings command and handle it
     */
    public function handleSettingsCommand(string $command): bool
    {
        if (str_starts_with($command, 'set_lunch_time')) {
            $this->handleSetLunchTime($command);
            return true;
        }
        
        if (str_starts_with($command, 'set_lunch_duration')) {
            $this->handleSetLunchDuration($command);
            return true;
        }
        
        if (str_starts_with($command, 'set_max_operators')) {
            $this->handleSetMaxOperators($command);
            return true;
        }
        
        return false;
    }
}

