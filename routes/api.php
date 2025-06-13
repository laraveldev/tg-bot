<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Telegram Bot API routes
Route::prefix('telegram')->group(function () {
    Route::get('/bots', [TelegramController::class, 'getBotInfo']);
    Route::get('/chats', [TelegramController::class, 'getChatInfo']);
    Route::post('/send-message', [TelegramController::class, 'sendTestMessage']);
});
