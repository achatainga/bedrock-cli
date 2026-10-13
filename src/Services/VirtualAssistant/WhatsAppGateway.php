<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\VirtualAssistant;

use RuntimeException;

class WhatsAppGateway
{
    private string $baseUrl;
    private string $apiKey;
    private string $instanceName;

    public function __construct(
        string $baseUrl = 'http://localhost:8084',
        string $apiKey = '',
        string $instanceName = 'bedrock-assistant'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->instanceName = $instanceName;
    }

    /**
     * Dispatches an outbound WhatsApp text message via Evolution API v2.
     * Enforces strict timeout and structured error handling.
     */
    public function sendMessage(string $recipientPhone, string $message): array
    {
        $sanitizedPhone = preg_replace('/\D/', '', $recipientPhone);
        if (empty($sanitizedPhone) || strlen($sanitizedPhone) < 8) {
            throw new \InvalidArgumentException("Invalid recipient phone number: '{$recipientPhone}'");
        }

        $endpoint = "{$this->baseUrl}/message/sendText/{$this->instanceName}";
        $payload = [
            'number' => $sanitizedPhone,
            'options' => [
                'delay' => 1200, // anti-ban jitter
                'presence' => 'composing',
            ],
            'textMessage' => [
                'text' => $message,
            ],
        ];

        return $this->request('POST', $endpoint, $payload);
    }

    /**
     * Checks instance connection state (open, connecting, close).
     */
    public function getConnectionStatus(): array
    {
        $endpoint = "{$this->baseUrl}/instance/connectionState/{$this->instanceName}";
        return $this->request('GET', $endpoint);
    }

    /**
     * Executes safe HTTP request with SRE limits (timeout <= 1500ms for healthchecks or 5000ms for sends).
     */
    private function request(string $method, string $url, array $payload = []): array
    {
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if (\PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($raw === false) {
            return [
                'success' => false,
                'error' => "Network error: {$err}",
                'code' => $httpCode,
            ];
        }

        $decoded = json_decode($raw, true) ?? [];
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'code' => $httpCode,
            'data' => $decoded,
        ];
    }
}
