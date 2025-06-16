<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\UserManagement;
use Illuminate\Support\Facades\Log;

class ButtonCommandService
{
    private TelegramUserService $userService;
    private WebhookHandler $handler;
    
    public function __construct(TelegramUserService $userService, WebhookHandler $handler)
    {
        $this->userService = $userService;
        $this->handler = $handler;
    }
    
    /**
     * Get button to command mapping
     */
    public function getCommandMapping(): array
    {
        return [
            // Supervisor commands
            '📊 Tushlik Holati' => 'lunch_status',
            '📋 Jadval' => 'lunch_schedule',
            '⚙️ Sozlamalar' => 'lunch_settings',
            '👥 Operatorlar' => 'operators',
            '🔄 Navbat Tuzish' => 'reorder_queue',
            '➡️ Keyingi Guruh' => 'next_group',
            '🔄 Guruh Sinxronlash' => 'sync_group_members',
            '📊 Statistika' => 'group_statistics',
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
    }
    
    /**
     * Process button command
     */
    public function processButtonCommand(string $buttonText): ?string
    {
        $commandMapping = $this->getCommandMapping();
        
        Log::info('Button text received', [
            'text' => $buttonText, 
            'length' => strlen($buttonText),
            'bytes' => bin2hex($buttonText)
        ]);
        
        // Log all available mappings for debugging
        Log::info('Available command mappings', ['mappings' => array_keys($commandMapping)]);
        
        // Check for exact match
        if (isset($commandMapping[$buttonText])) {
            $methodName = $commandMapping[$buttonText];
            Log::info('Button mapped to command', ['button' => $buttonText, 'command' => $methodName]);
            return $methodName;
        }
        
        // If no exact match, try partial matching for common words
        foreach ($commandMapping as $buttonKey => $command) {
            if (strpos($buttonText, 'Tushlik') !== false && strpos($buttonKey, 'Tushlik') !== false) {
                Log::info('Partial match found', ['button' => $buttonText, 'matched_key' => $buttonKey, 'command' => $command]);
                return $command;
            }
        }
        
        return null;
    }
    
    /**
     * Check if user has permission for command
     */
    public function checkPermission(string $command, UserManagement $user): bool
    {
        $supervisorCommands = [
            'lunch_status', 'lunch_schedule', 'lunch_settings', 
            'operators', 'reorder_queue', 'next_group', 
            'sync_group_members', 'group_statistics'
        ];
        
        if (in_array($command, $supervisorCommands) && !$user->isSupervisor()) {
            $this->handler->sendReply("❌ Bu buyruq sizga ruxsat berilmagan!");
            return false;
        }
        
        return true;
    }
}

