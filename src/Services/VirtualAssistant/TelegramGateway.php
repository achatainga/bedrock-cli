<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\VirtualAssistant;

class TelegramGateway
{
    private string $botToken;
    private string $apiBase;

    public function __construct(string $botToken = '')
    {
        $this->botToken = $botToken;
        $this->apiBase = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function sendMessage(string|int $chatId, string $text, array $replyMarkup = []): array
    {
        $url = "{$this->apiBase}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request($url, $payload);
    }

    public function setWebhook(string $webhookUrl): array
    {
        $url = "{$this->apiBase}/setWebhook";
        return $this->request($url, ['url' => $webhookUrl]);
    }

    private function request(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if (\PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($raw === false) {
            return ['ok' => false, 'error' => $err, 'code' => $httpCode];
        }

        return json_decode($raw, true) ?? ['ok' => false, 'code' => $httpCode];
    }
}
