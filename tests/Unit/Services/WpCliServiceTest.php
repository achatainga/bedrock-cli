<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\DockerService;
use Symfony\Component\Process\Process;

class WpCliServiceTest extends TestCase
{
    private WpCliService $service;
    private DockerService $dockerMock;

    protected function setUp(): void
    {
        $this->dockerMock = $this->createMock(DockerService::class);
        $this->service = new WpCliService($this->dockerMock);
    }

    public function test_it_executes_wp_cli_command(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->with('web', $this->anything())
            ->willReturn($processMock);

        $result = $this->service->exec(['plugin', 'list']);

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_creates_database(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->dbCreate();

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_drops_database(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->dbDrop();

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_installs_wordpress_core(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $options = [
            'url' => 'http://localhost',
            'title' => 'Test Site',
            'admin_user' => 'admin',
            'admin_password' => 'password',
            'admin_email' => 'admin@example.com'
        ];

        $result = $this->service->coreInstall($options);

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_activates_plugin(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->pluginActivate('akismet');

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_activates_theme(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->themeActivate('twentytwentyfour');

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_creates_user(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->userCreate('testuser', 'test@example.com', 'editor');

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_performs_search_replace(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->dbSearchReplace('oldurl.com', 'newurl.com');

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_executes_custom_command(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->custom('cache flush');

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_updates_all_plugins(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->willReturn($processMock);

        $result = $this->service->pluginUpdate();

        $this->assertInstanceOf(Process::class, $result);
    }

    public function test_it_escapes_special_characters_and_backticks_safely(): void
    {
        $processMock = $this->createMock(Process::class);
        
        $this->dockerMock
            ->expects($this->once())
            ->method('exec')
            ->with(
                'web',
                $this->callback(function (array $cmd) {
                    $cmdStr = $cmd[2] ?? '';
                    return str_contains($cmdStr, "'plugin'")
                        && str_contains($cmdStr, "'install'")
                        && str_contains($cmdStr, "'`id`'")
                        && str_contains($cmdStr, "'test;rm -rf /'");
                })
            )
            ->willReturn($processMock);

        $this->service->exec(['plugin', 'install', '`id`', 'test;rm -rf /']);
    }
}
