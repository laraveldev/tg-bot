<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramUser;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Facades\Telegraph;

class SendTelegramMessage extends Command
{
    protected $signature = 'telegram:send-message';

    protected $description = 'Barcha foydalanuvchilarga xabar yuboradi';

    public function handle()
    {
    $bot = TelegraphBot::first();
    $chatId = -1002768510963; // Guruh ID

    if ($bot) {
        Telegraph::bot($bot)
            ->chat($chatId)
            ->message("✅ Avtomatik xabar har daqiqada yuborildi")
            ->send();
    }

        $this->info("Barcha xabarlar yuborildi.");
    }
}