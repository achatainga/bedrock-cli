<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services;

class MailpitService
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (getenv('MAILPIT_URL') ?: 'http://127.0.0.1:8025'), '/');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check if Mailpit API is reachable.
     */
    public function isHealthy(): bool
    {
        $res = $this->request('GET', '/api/v1/info');
        return is_array($res);
    }

    /**
     * List messages from Mailpit.
     */
    public function getMessages(int $limit = 20, string $query = ''): array
    {
        $endpoint = '/api/v1/messages?limit=' . $limit;
        if (!empty($query)) {
            $endpoint .= '&query=' . urlencode($query);
        }

        $res = $this->request('GET', $endpoint);
        return $res['messages'] ?? [];
    }

    /**
     * Get a single message by ID with full details.
     */
    public function getMessage(string $id): ?array
    {
        return $this->request('GET', '/api/v1/message/' . urlencode($id));
    }

    /**
     * Delete a single message by ID.
     */
    public function deleteMessage(string $id): bool
    {
        $res = $this->request('DELETE', '/api/v1/message/' . urlencode($id));
        return $res !== null;
    }

    /**
     * Clear all messages in Mailpit.
     */
    public function clearMessages(): bool
    {
        $res = $this->request('DELETE', '/api/v1/messages');
        return $res !== null;
    }

    /**
     * Trigger resend-activation REST API on WordPress.
     */
    public function triggerResendActivation(string $email, string $siteUrl = 'https://staging.detodo24.com'): array
    {
        $url = rtrim($siteUrl, '/') . '/wp-json/dt24-smart-register/v1/auth/resend-activation';
        $payload = json_encode(['email' => $email]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ];
            $context = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $context);
        }

        if ($raw === false || empty($raw)) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar al endpoint de WordPress: ' . $url,
            ];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'raw' => $raw];
    }

    /**
     * Extract activation link and 6-digit code from message Text/HTML.
     */
    public function extractActivationDetails(array $message): array
    {
        $details = [
            'code' => null,
            'url'  => null,
        ];

        $body = ($message['Text'] ?? '') . "\n" . ($message['HTML'] ?? '');

        // Extract 6-digit code
        if (preg_match('/\b([0-9]{6})\b/', $body, $matches)) {
            $details['code'] = $matches[1];
        }

        // Extract dt24_activate URL
        if (preg_match('/(https?:\/\/[^\s"\'<>]+(?:dt24_activate|dt24_activate_token)[^\s"\'<>]*)/', $body, $matches)) {
            $details['url'] = html_entity_decode($matches[1]);
        }

        return $details;
    }

    /**
     * Internal HTTP request helper using cURL with stream fallback.
     */
    private function request(string $method, string $path): ?array
    {
        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                return null;
            }

            if ($method === 'DELETE' && ($httpCode === 200 || $httpCode === 204)) {
                return ['ok' => true];
            }

            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => "Accept: application/json\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            return null;
        }

        if (empty($raw) && $method === 'DELETE') {
            return ['ok' => true];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
