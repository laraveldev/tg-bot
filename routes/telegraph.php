<?php

use Illuminate\Support\Facades\Route;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Facades\Telegraph;
Telegraph::webhook('/telegraph/{token}');
Telegraph::routes('/my-webhook');
Route::get('/send-test', function () {
    $bot = TelegraphBot::find(1);
    $bot->chat(env('TELEGRAPH_CHAT_ID'))->message('Assalomu alaykum!')->send();

    return 'Yuborildi!';
});