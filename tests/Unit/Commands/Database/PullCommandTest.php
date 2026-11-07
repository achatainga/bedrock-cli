<?php

namespace Tests\Unit\Commands\Database;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Database\PullCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PullCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PullCommand();

        $this->assertEquals('db:pull', $command->getName());
        $this->assertStringContainsString('Descarga e importa la base de datos remota', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('remote'));
        $this->assertTrue($command->getDefinition()->hasOption('db'));
        $this->assertTrue($command->getDefinition()->hasOption('user'));
        $this->assertTrue($command->getDefinition()->hasOption('password'));
        $this->assertTrue($command->getDefinition()->hasOption('file'));
        $this->assertTrue($command->getDefinition()->hasOption('skip-replace'));
        $this->assertTrue($command->getDefinition()->hasOption('target-url'));
        $this->assertTrue($command->getDefinition()->hasOption('source-url'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PullCommand());

        $this->assertTrue($application->has('db:pull'));
    }

    public function testExecuteFailsWhenLocalDumpFileNotFound(): void
    {
        $application = new Application();
        $application->add(new PullCommand());

        $command = $application->find('db:pull');
        $tester = new CommandTester($command);

        $tester->execute([
            '--file' => 'non_existent_dump_file_' . uniqid() . '.sql'
        ]);

        $this->assertEquals(1, $tester->getStatusCode());
        $this->assertStringContainsString('No se encontró el archivo', $tester->getDisplay());
    }

    public function testExecuteDryRunLocalFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'dump_');
        file_put_contents($tmpFile, "-- MySQL dump\n-- Dump completed on 2026-11-07");

        try {
            $application = new Application();
            $application->add(new PullCommand());

            $command = $application->find('db:pull');
            $tester = new CommandTester($command);

            $tester->execute([
                '--file' => $tmpFile,
                '--dry-run' => true
            ]);

            $this->assertEquals(0, $tester->getStatusCode());
            $this->assertStringContainsString('[DRY-RUN]', $tester->getDisplay());
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }
}
