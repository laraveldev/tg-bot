<?php

namespace App\Http\Controllers;

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TelegramController extends Controller
{
    /**
     * Get bot information
     */
    public function getBotInfo(): JsonResponse
    {
        $bots = TelegraphBot::all();
        return response()->json([
            'status' => 'success',
            'bots' => $bots,
            'total_bots' => $bots->count()
        ]);
    }

    /**
     * Get chat information
     */
    public function getChatInfo(): JsonResponse
    {
        $chats = TelegraphChat::all();
        return response()->json([
            'status' => 'success',
            'chats' => $chats,
            'total_chats' => $chats->count()
        ]);
    }

    /**
     * Send a message to all chats (for testing)
     */
    public function sendTestMessage(Request $request): JsonResponse
    {
        $message = $request->input('message', 'Hello from your Telegram Bot! 🤖');
        
        $chats = TelegraphChat::all();
        $sent = 0;
        
        foreach ($chats as $chat) {
            try {
                $chat->message($message)->send();
                $sent++;
            } catch (\Exception $e) {
                // Log error but continue with other chats
                \Log::error('Failed to send message to chat ' . $chat->chat_id . ': ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Messages sent',
            'sent_to' => $sent,
            'total_chats' => $chats->count()
        ]);
    }
}
