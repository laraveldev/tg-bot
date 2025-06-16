<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\UserManagement;
use Illuminate\Support\Facades\Log;

class AdminCommandService
{
    private TelegramUserService $userService;
    private WebhookHandler $handler;
    
    public function __construct(TelegramUserService $userService, WebhookHandler $handler)
    {
        $this->userService = $userService;
        $this->handler = $handler;
    }
    
    /**
     * Handle make user supervisor command
     */
    public function handleMakeSupervisor(): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        
        if ($userId) {
            // Update user to supervisor
            $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
            $user->role = 'supervisor';
            $user->save();
            
            $this->handler->sendReply("✅ Siz supervisor sifatida belgilandi! /help ni bosib tekshiring.");
        } else {
            $this->handler->sendReply("❌ User ID topilmadi.");
        }
    }
    
    /**
     * Handle group members sync command
     */
    public function handleSyncGroupMembers(): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
        
        if (!$user->isSupervisor()) {
            $this->handler->sendReply("❌ Bu buyruq faqat supervisor lar uchun!");
            return;
        }
        
        // Use group chat ID from env file
        $groupChatId = config('telegraph.chat_id') ?? env('TELEGRAPH_CHAT_ID');
        if (!$groupChatId) {
            $this->handler->sendReply("❌ Guruh chat ID topilmadi!");
            return;
        }
        
        $groupService = new GroupMembersService();
        $result = $groupService->syncGroupMembers((int)$groupChatId);
        
        if ($result['success']) {
            $message = "✅ Guruh a'zolari sinxronlashdi!\n\n";
            $message .= "📊 Natijalar:\n";
            $message .= "👥 Jami sinxronlandi: {$result['synced_count']}\n";
            $message .= "🆕 Yangi a'zolar: {$result['new_members']}\n";
            $message .= "👨‍💼 Adminlar: {$result['admin_count']}\n";
            $message .= "📈 Guruhda jami: {$result['total_estimated']} kishi";
        } else {
            $message = "❌ Sinxronlash xatosi: {$result['message']}";
        }
        
        $this->handler->sendReply($message);
    }
    
    /**
     * Handle register as operator command
     */
    public function handleRegisterAsOperator(): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $userInfo = $message['from'] ?? [];
        
        if ($userId) {
            $groupService = new GroupMembersService();
            $success = $groupService->registerAsOperator($userId, $userInfo);
            
            if ($success) {
                $this->handler->sendReply("✅ Siz operator sifatida ro'yxatdan o'tdingiz!\n\n📞 Iltimos, /start buyrug'ini bosib telefon raqamingizni kiriting.");
            } else {
                $this->handler->sendReply("❌ Ro'yxatdan o'tishda xatolik yuz berdi.");
            }
        } else {
            $this->handler->sendReply("❌ User ID topilmadi.");
        }
    }
    
    /**
     * Check if command is admin command and handle it
     */
    public function handleAdminCommand(string $command): bool
    {
        switch ($command) {
            case 'make_me_supervisor':
                $this->handleMakeSupervisor();
                return true;
                
            case 'sync_group_members':
                $this->handleSyncGroupMembers();
                return true;
                
            case 'register_me':
                $this->handleRegisterAsOperator();
                return true;
                
            default:
                return false;
        }
    }
}

