<?php

declare(strict_types=1);

namespace Tests\Unit\Commands\Generate;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Generate\GenerateCommand;
use Roots\BedrockCli\Commands\Generate\VerticalsListCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->add(new GenerateCommand());
        $this->app->add(new VerticalsListCommand());
    }

    public function test_it_executes_verticals_list(): void
    {
        $command = $this->app->find('verticals:list');
        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('50 verticales', $output);
        $this->assertStringContainsString('healthcare-provider', $output);
        $this->assertStringContainsString('saas-b2b', $output);
        $this->assertStringContainsString('BEDROCK CLI — CORE ENGINE VERTICALS', $output);
        $this->assertStringNotContainsString('BEDROCK UNIVERSAL ENTERPRISE FACTORY', $output);
        $this->assertStringNotContainsString('dt24-', $output);
    }

    public function test_it_executes_verticals_list_with_custom_prefix(): void
    {
        $command = $this->app->find('verticals:list');
        $tester = new CommandTester($command);
        $status = $tester->execute(['--prefix' => 'custom-']);

        $this->assertSame(0, $status);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('custom-', $output);
        $this->assertStringNotContainsString('dt24-', $output);
    }

    public function test_it_executes_generate_dry_run(): void
    {
        $command = $this->app->find('generate');
        $tester = new CommandTester($command);
        $status = $tester->execute([
            'vertical' => 'saas-b2b',
            'name' => 'test-saas',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $status);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('GENERANDO PROYECTO 10x: test-saas', $output);
        $this->assertStringContainsString('Pilar Administrativo & RBAC', $output);
        $this->assertStringContainsString('Omnichannel Virtual Assistant', $output);
        $this->assertStringContainsString('High-Conversion Landing Page', $output);
        $this->assertStringContainsString('DevOps & Cloud-Native Delivery', $output);
    }

    public function test_it_fails_on_invalid_vertical(): void
    {
        $command = $this->app->find('generate');
        $tester = new CommandTester($command);
        $status = $tester->execute([
            'vertical' => 'non-existent-sector',
        ]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("Vertical 'non-existent-sector' no encontrada", $tester->getDisplay());
    }
}
