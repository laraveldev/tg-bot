<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphBot as BaseTelegraphBot;

class TelegraphBot extends BaseTelegraphBot
{
    protected $fillable = [
        'token',
        'name',
        'handler_class',
    ];
    
    /**
     * Get the handler class for this bot
     */
    public function getHandlerClass(): string
    {
        return $this->handler_class ?: config('telegraph.webhook.handler');
    }
}

