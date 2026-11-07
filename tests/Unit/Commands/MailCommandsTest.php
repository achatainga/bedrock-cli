<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Mail\ClearCommand;
use Roots\BedrockCli\Commands\Mail\ListCommand;
use Roots\BedrockCli\Commands\Mail\ResendActivationCommand;
use Roots\BedrockCli\Commands\Mail\ViewCommand;
use Roots\BedrockCli\Services\MailpitService;
use Symfony\Component\Console\Tester\CommandTester;

class MailCommandsTest extends TestCase
{
    public function testListCommandEmpty(): void
    {
        $mockService = $this->createMock(MailpitService::class);
        $mockService->method('getBaseUrl')->willReturn('http://127.0.0.1:8025');
        $mockService->method('getMessages')->willReturn([]);

        $cmd = new ListCommand($mockService);
        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No se encontraron correos', $tester->getDisplay());
    }

    public function testListCommandWithMessages(): void
    {
        $mockService = $this->createMock(MailpitService::class);
        $mockService->method('getBaseUrl')->willReturn('http://127.0.0.1:8025');
        $mockService->method('getMessages')->willReturn([
            [
                'ID' => 'msg-123',
                'Created' => '2026-10-23T20:00:00Z',
                'From' => ['Name' => 'DeTodo24', 'Address' => 'no-reply@detodo24.com'],
                'To' => [['Address' => 'user@test.com']],
                'Subject' => 'Activa tu cuenta',
                'Snippet' => 'Hola usuario activa tu cuenta...',
            ]
        ]);

        $cmd = new ListCommand($mockService);
        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('msg-123', $display);
        $this->assertStringContainsString('Activa tu cuenta', $display);
    }

    public function testViewCommandDisplaysActivationInfo(): void
    {
        $mockService = $this->createMock(MailpitService::class);
        $mockService->method('getMessage')->with('msg-123')->willReturn([
            'ID' => 'msg-123',
            'Date' => '2026-10-23T20:00:00Z',
            'From' => ['Name' => 'DeTodo24', 'Address' => 'no-reply@detodo24.com'],
            'To' => [['Address' => 'user@test.com']],
            'Subject' => 'Activa tu cuenta',
            'Text' => 'Codigo: 849201. Enlace: https://staging.detodo24.com/?dt24_activate=testtoken',
            'HTML' => '',
        ]);
        $mockService->method('extractActivationDetails')->willReturn([
            'code' => '849201',
            'url' => 'https://staging.detodo24.com/?dt24_activate=testtoken',
        ]);

        $cmd = new ViewCommand($mockService);
        $tester = new CommandTester($cmd);
        $tester->execute(['id' => 'msg-123']);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('CODIGO DE ACTIVACION: 849201', $display);
        $this->assertStringContainsString('ENLACE DE ACTIVACION: https://staging.detodo24.com/?dt24_activate=testtoken', $display);
    }

    public function testClearCommand(): void
    {
        $mockService = $this->createMock(MailpitService::class);
        $mockService->method('getBaseUrl')->willReturn('http://127.0.0.1:8025');
        $mockService->method('clearMessages')->willReturn(true);

        $cmd = new ClearCommand($mockService);
        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('vaciada con éxito', $tester->getDisplay());
    }

    public function testResendActivationCommand(): void
    {
        $mockService = $this->createMock(MailpitService::class);
        $mockService->method('triggerResendActivation')->willReturn([
            'success' => true,
            'message' => 'Correo enviado',
        ]);
        $mockService->method('getMessages')->willReturn([
            [
                'ID' => 'new-msg-456',
                'Subject' => 'Activa tu cuenta en DT24',
            ]
        ]);
        $mockService->method('getMessage')->with('new-msg-456')->willReturn([
            'ID' => 'new-msg-456',
            'Text' => 'Codigo: 654321',
        ]);
        $mockService->method('extractActivationDetails')->willReturn([
            'code' => '654321',
            'url' => 'https://staging.detodo24.com/?dt24_activate=newtok',
        ]);

        $cmd = new ResendActivationCommand($mockService);
        $tester = new CommandTester($cmd);
        $tester->execute(['email' => 'test@detodo24.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('CODIGO DE ACTIVACION RECIBIDO: 654321', $display);
    }
}
