<?php
namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Stringable;

class Handler extends WebhookHandler
{
    public function start(): void
    {
        $this->reply('🤖 Welcome to your Telegram Bot!');
        $this->reply('You can send me any message and I will respond!');
    }

    public function hello(string $name = 'there'): void
    {
        $this->reply('Hello, ' . $name . '! 👋');
    }

    protected function handleChatMessage(Stringable $text): void
    {
        // Handle any text message that doesn't match specific commands
        if ($text->lower()->contains(['hello', 'hi', 'hey'])) {
            $this->reply('Hello! 👋 How can I help you today?');
        } elseif ($text->lower()->contains(['thanks', 'thank you'])) {
            $this->reply('You\'re welcome! 😊');
        } elseif ($text->lower()->contains(['bye', 'goodbye'])) {
            $this->reply('Goodbye! See you later! 👋');
        } else {
            $this->reply('I received your message: "' . $text . '"\n\nYou can try:\n- /start - to see welcome message\n- /hello - to get a greeting');
        }
    }
}
