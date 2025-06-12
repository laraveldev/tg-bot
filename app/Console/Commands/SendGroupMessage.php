<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;

class SendGroupMessage extends Command
{
    protected $signature = 'telegram:send-group-message';
    protected $description = 'Guruhga avtomatik habar yuboradi har 1 daqiqada';

    public function handle()
    {
        $chatId = -1002768510963; // Guruh ID
        $message = "⏰ Bu avtomatik xabar. Bot ishlayapti.";

        $bot = TelegraphBot::first();
        if ($bot) {
            Telegraph::bot($bot)
                ->chat($chatId)
                ->message($message)
                ->send();

            $this->info("Xabar yuborildi.");
        } else {
            $this->error("Bot topilmadi.");
        }
    }
}