<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Facades\Log;

class TelegramController extends WebhookHandler
{
    public function handleUnknownCommand(array $message): void
    {
        Log::info('Nomaʼlum buyruq:', $message);
    }

    public function handleChatJoinRequest(array $message): void
    {
        Log::info('Yangi aʼzo qo‘shildi:', $message);
    }

    public function handleText(array $message): void
    {
        Log::info('Matnli xabar:', $message);
    }

    public function chat_member_updated(array $data): void
    {
        Log::info('chat_member_updated:', $data);

        if (isset($data['new_chat_member']['user'])) {
            $telegramId = $data['new_chat_member']['user']['id'];
            $fullName = $data['new_chat_member']['user']['first_name'] ?? 'NoName';

            TelegramUser::updateOrCreate(
                ['telegram_id' => $telegramId],
                ['name' => $fullName]
            );

            $bot = TelegraphBot::first();

            if ($bot) {
                Telegraph::bot($bot)
                    ->chat($telegramId)
                    ->message("Salom $fullName! Guruhga xush kelibsiz!")
                    ->send();
            } else {
                Log::error('Bot topilmadi.');
            }
        }
    }
}