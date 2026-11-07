<?php

namespace Roots\BedrockCli\Commands\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class UpdateConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('install:update-config')
             ->setDescription('Update configuration files from stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $projectPath = getcwd();
        $io->title('BEDROCK CLI - UPDATE CONFIG');
        $io->text("Working in: {$projectPath}");

        // Verificar que estamos en un proyecto bedrock
        if (!file_exists($projectPath . '/composer.json')) {
            $io->error('Not in a Bedrock project directory');
            return Command::FAILURE;
        }

        $configFiles = [
            'config/application.php' => 'config/application.php.stub',
        ];

        foreach ($configFiles as $target => $stub) {
            $stubPath = __DIR__ . '/../../../stubs/' . $stub;
            $targetPath = $projectPath . '/' . $target;
            
            if (!file_exists($stubPath)) {
                $io->warning("Stub not found: {$stubPath}");
                continue;
            }

            if (file_exists($targetPath)) {
                $backupPath = $targetPath . '.backup.' . date('Ymd_His');
                copy($targetPath, $backupPath);
                $io->text("✓ Backup: " . basename($backupPath));
            }

            if (copy($stubPath, $targetPath)) {
                $io->text("✓ Updated: {$target}");
            } else {
                $io->error("✗ Failed: {$target}");
                return Command::FAILURE;
            }
        }

        $io->success('Config updated! Restart containers to apply changes.');
        return Command::SUCCESS;
    }
}