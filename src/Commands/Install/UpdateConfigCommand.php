<?php

namespace Roots\BedrockCli\Commands\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class UpdateConfigCommand extends Command
{
    use ProjectSelectorTrait;

    protected static $defaultName = 'install:update-config';
    protected static $defaultDescription = 'Update configuration files from stubs';

    protected function configure(): void
    {
        $this->setDescription('Update configuration files from stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $projectPath = $this->selectProject($io);
        if (!$projectPath) {
            return Command::FAILURE;
        }

        $io->title('BEDROCK CLI - UPDATE CONFIG');

        // Archivos de configuración a actualizar
        $configFiles = [
            'config/application.php' => 'config/application.php.stub',
        ];

        foreach ($configFiles as $target => $stub) {
            $stubPath = __DIR__ . '/../../../stubs/' . $stub;
            $targetPath = $projectPath . '/' . $target;

            if (!file_exists($stubPath)) {
                $io->warning("Stub not found: {$stub}");
                continue;
            }

            // Backup del archivo actual
            if (file_exists($targetPath)) {
                $backupPath = $targetPath . '.backup.' . date('Ymd_His');
                copy($targetPath, $backupPath);
                $io->text("✓ Backup created: " . basename($backupPath));
            }

            // Copiar nuevo archivo
            if (copy($stubPath, $targetPath)) {
                $io->text("✓ Updated: {$target}");
            } else {
                $io->error("✗ Failed to update: {$target}");
                return Command::FAILURE;
            }
        }

        $io->success('Configuration files updated successfully!');
        $io->note('Remember to restart your web server/containers to apply changes.');

        return Command::SUCCESS;
    }
}