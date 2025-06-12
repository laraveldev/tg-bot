<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();

        // Foydalanuvchi guruhga qo‘shilgani
        if (isset($data['message']['new_chat_members'])) {
            foreach ($data['message']['new_chat_members'] as $member) {
                TelegramUser::updateOrCreate(
                    ['telegram_id' => $member['id']],
                    [
                        'first_name' => $member['first_name'] ?? '',
                        'last_name' => $member['last_name'] ?? '',
                        'username' => $member['username'] ?? '',
                    ]
                );
            }
        }

        return response()->json(['ok' => true]);
    }
}
