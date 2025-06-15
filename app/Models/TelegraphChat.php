<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphChat as BaseTelegraphChat;

class TelegraphChat extends BaseTelegraphChat
{
    protected $fillable = [
        'chat_id',
        'name',
        'telegraph_bot_id',
        'first_name',
        'last_name',
        'username',
        'phone_number',
    ];

    protected $casts = [
        'chat_id' => 'string',
        'first_name' => 'string',
        'last_name' => 'string',
        'username' => 'string',
        'phone_number' => 'string',
    ];
}

