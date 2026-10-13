<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VirtualAssistant;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\VirtualAssistant\WhatsAppGateway;

class WhatsAppGatewayTest extends TestCase
{
    public function test_it_rejects_invalid_phone_numbers(): void
    {
        $gateway = new WhatsAppGateway();
        $this->expectException(\InvalidArgumentException::class);
        $gateway->sendMessage('abc', 'Hello');
    }

    public function test_it_formats_safe_requests(): void
    {
        $gateway = new WhatsAppGateway('http://127.0.0.1:9999', 'fake-key', 'test-instance');
        // Request to offline endpoint returns structured network error gracefully without crashing
        $result = $gateway->sendMessage('+12345678901', 'Test message');
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
