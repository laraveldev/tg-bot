<?php

use Illuminate\Support\Facades\Route;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Support\Facades\Log;

// Telegram webhookdan kelgan so‘rovni qabul qiluvchi
Telegraph::webhook(function (Telegraph $telegraph) {
    Log::info('Webhook ishga tushdi'); // test uchun log
    return response('ok'); // javob qaytarish muhim
});

// Ixtiyoriy: qo‘shimcha yo‘nalish (test uchun)
Route::get('/send-test', function () {
    $bot = TelegraphBot::find(1);
    $bot->chat(env('TELEGRAPH_CHAT_ID'))->message('Assalomu alaykum!')->send();

    return 'Yuborildi!';
});