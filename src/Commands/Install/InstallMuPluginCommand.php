<?php

namespace Roots\BedrockCli\Commands\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallMuPluginCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('install:mu-plugin')
             ->setDescription('Install Bedrock CLI MU-Plugin for REST API operations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("\n<fg=magenta>═══════════════════════════════════════════════════════════════</>");
        $output->writeln("<fg=magenta>  BEDROCK CLI - INSTALL MU-PLUGIN</>");
        $output->writeln("<fg=magenta>═══════════════════════════════════════════════════════════════</>\n");

        if (!file_exists('web/app/mu-plugins')) {
            $output->writeln("<fg=red>✗ Error: Not a Bedrock project</>");
            return Command::FAILURE;
        }

        $targetDir = 'web/app/mu-plugins/bedrock-cli-plugin';

        if (file_exists($targetDir)) {
            $output->writeln("<fg=yellow>⚠ MU-Plugin already installed</>");
            $output->writeln("<fg=gray>  Use 'install:update-mu-plugin' to update</>\n");
            return Command::SUCCESS;
        }

        $sourcePath = __DIR__ . '/../../../mu-plugin';
        
        if (!file_exists($sourcePath)) {
            $output->writeln("<fg=red>✗ Error: Plugin source not found</>");
            return Command::FAILURE;
        }

        $this->recursiveCopy($sourcePath, $targetDir);
        $output->writeln("<fg=green>✓ Plugin installed</>");

        // Run composer install
        $output->writeln("<fg=cyan>→ Installing dependencies...</>");
        $process = new \Symfony\Component\Process\Process(['composer', 'install', '--no-dev', '--quiet'], $targetDir);
        $process->setTimeout(120);
        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln("<fg=green>✓ Dependencies installed</>");
        }

        // Create logs directory
        $logsDir = $targetDir . '/logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $output->writeln("\n<fg=green>✓ Installation complete!</>");
        $output->writeln("<fg=gray>  Location: {$targetDir}</>\n");

        return Command::SUCCESS;
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === 'vendor') continue;
            
            if (is_dir($src . '/' . $file)) {
                $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        
        closedir($dir);
    }
}
