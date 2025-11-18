<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\DockerService;
use Symfony\Component\Process\Process;

class DockerServiceTest extends TestCase
{
    private DockerService $service;

    protected function setUp(): void
    {
        $this->service = new DockerService();
    }

    public function test_it_creates_up_process(): void
    {
        $process = $this->service->up();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('docker-compose', $process->getCommandLine());
        $this->assertStringContainsString('up', $process->getCommandLine());
    }

    public function test_it_creates_up_process_with_build(): void
    {
        $process = $this->service->up(true);
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('--build', $process->getCommandLine());
    }

    public function test_it_creates_down_process(): void
    {
        $process = $this->service->down();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('docker-compose', $process->getCommandLine());
        $this->assertStringContainsString('down', $process->getCommandLine());
    }

    public function test_it_creates_restart_process(): void
    {
        $process = $this->service->restart();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('restart', $process->getCommandLine());
    }

    public function test_it_creates_status_process(): void
    {
        $process = $this->service->status();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('ps', $process->getCommandLine());
    }

    public function test_it_creates_exec_process(): void
    {
        $process = $this->service->exec('web', ['bash', '-c', 'ls']);
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('exec', $process->getCommandLine());
        $this->assertStringContainsString('web', $process->getCommandLine());
    }

    public function test_it_creates_rebuild_process(): void
    {
        $process = $this->service->rebuild();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('build', $process->getCommandLine());
        $this->assertStringContainsString('--no-cache', $process->getCommandLine());
    }

    public function test_it_creates_logs_process(): void
    {
        $process = $this->service->logs();
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('logs', $process->getCommandLine());
        $this->assertStringContainsString('--tail=100', $process->getCommandLine());
    }

    public function test_it_creates_logs_process_with_follow(): void
    {
        $process = $this->service->logs(true);
        
        $this->assertInstanceOf(Process::class, $process);
        $this->assertStringContainsString('-f', $process->getCommandLine());
    }
}
