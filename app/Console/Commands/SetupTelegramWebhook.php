<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Log;

class SetupTelegramWebhook extends Command
{
    protected $signature = 'telegram:setup-webhook {--enable-chat-member}';
    protected $description = 'Setup Telegram webhook with chat member updates';

    public function handle()
    {
        $bot = TelegraphBot::first();
        if (!$bot) {
            $this->error('❌ No bot found!');
            return 1;
        }
        
        $botToken = $bot->token;
        $webhookUrl = config('app.url') . '/telegraph/' . $bot->token . '/webhook';
        
        // Define allowed updates
        $allowedUpdates = [
            'message',
            'edited_message',
            'callback_query',
            'inline_query',
            'chosen_inline_result',
            'channel_post',
            'edited_channel_post'
        ];
        
        // Add chat member updates if requested
        if ($this->option('enable-chat-member')) {
            $allowedUpdates[] = 'chat_member';
            $allowedUpdates[] = 'my_chat_member';
            $this->info('✅ Chat member updates enabled');
        }
        
        try {
            $url = "https://api.telegram.org/bot{$botToken}/setWebhook";
            $data = [
                'url' => $webhookUrl,
                'allowed_updates' => json_encode($allowedUpdates),
                'drop_pending_updates' => true
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                
                if ($result && $result['ok']) {
                    $this->info('✅ Webhook setup successful!');
                    $this->info("📡 Webhook URL: {$webhookUrl}");
                    $this->info('🔄 Allowed updates: ' . implode(', ', $allowedUpdates));
                    
                    if (isset($result['result']['url'])) {
                        $this->info('🌐 Active webhook: ' . $result['result']['url']);
                    }
                    
                    return 0;
                } else {
                    $this->error('❌ Webhook setup failed: ' . ($result['description'] ?? 'Unknown error'));
                    return 1;
                }
            } else {
                $this->error("❌ HTTP request failed with code: {$httpCode}");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Webhook setup failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}

