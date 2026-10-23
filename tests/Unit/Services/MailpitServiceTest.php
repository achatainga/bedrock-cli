<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\MailpitService;

class MailpitServiceTest extends TestCase
{
    public function testDefaultBaseUrl(): void
    {
        $service = new MailpitService();
        $this->assertNotEmpty($service->getBaseUrl());
        $this->assertStringContainsString('8025', $service->getBaseUrl());
    }

    public function testCustomBaseUrl(): void
    {
        $service = new MailpitService('http://custom-host:9000/');
        $this->assertSame('http://custom-host:9000', $service->getBaseUrl());
    }

    public function testExtractActivationDetails(): void
    {
        $service = new MailpitService();

        $message = [
            'Text' => "¡Hola testactivacion!\nPara activar tu cuenta haz clic aquí:\nhttps://staging.detodo24.com/?dt24_activate=5699ce3f98324fbb7870cf1be918708ef8ce170406443010327d6271a52ee937\nO ingresa este código: 900079\n",
            'HTML' => '<p>Código: 900079</p>',
        ];

        $details = $service->extractActivationDetails($message);

        $this->assertSame('900079', $details['code']);
        $this->assertSame(
            'https://staging.detodo24.com/?dt24_activate=5699ce3f98324fbb7870cf1be918708ef8ce170406443010327d6271a52ee937',
            $details['url']
        );
    }

    public function testExtractActivationDetailsWithFallbackToken(): void
    {
        $service = new MailpitService();

        $message = [
            'Text' => "Enlace: https://staging.detodo24.com/my-account/?dt24_activate_token=abc123xyz&user_id=1002",
            'HTML' => '',
        ];

        $details = $service->extractActivationDetails($message);

        $this->assertNull($details['code']);
        $this->assertStringContainsString('dt24_activate_token=abc123xyz', $details['url']);
    }
}
