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
            . "🔧 Sozlash buyruqlari:\n"
            . "/set_lunch_time [smena_id] [bosh_vaqt] [tug_vaqt] - Tushlik vaqtini o'rnatish\n"
            . "/set_lunch_duration [smena_id] [daqiqa] - Tushlik davomiyligini o'rnatish\n"
            . "/set_max_operators [smena_id] [son] - Maksimal operatorlar sonini o'rnatish\n\n"
            . "Oddiy buyruqlar:\n"
            . "/info - Ma'lumotlaringiz\n"
            . "/help - Yordam";
            
        $handler->sendReply($message);
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
            
        $handler->sendReply($message);
    }
    
    /**
     * Handle unknown commands based on user role
     */
    public function handleUnknownCommand(string $command, UserManagement $user, WebhookHandler $handler): void
    {
        // All users are either supervisor or operator, so show available commands
        $this->showAvailableCommands($user, $handler);
    }
    
    /**
     * Handle chat messages based on user role
     */
    public function handleChatMessage(UserManagement $user, WebhookHandler $handler): void
    {
        // All users are either supervisor or operator, so show their available commands
        $this->showAvailableCommands($user, $handler);
    }
}

