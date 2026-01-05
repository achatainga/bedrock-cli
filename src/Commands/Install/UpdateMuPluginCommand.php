<?php

namespace Roots\BedrockCli\Commands\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class UpdateMuPluginCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('install:update-mu-plugin')
             ->setDescription('Update old MU-Plugin to new bedrock-cli-plugin structure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("\n<fg=magenta>═══════════════════════════════════════════════════════════════</>");
        $output->writeln("<fg=magenta>  BEDROCK CLI - UPDATE MU-PLUGIN</>");
        $output->writeln("<fg=magenta>═══════════════════════════════════════════════════════════════</>\n");

        if (!file_exists('web/app/mu-plugins')) {
            $output->writeln("<fg=red>✗ Error: Not a Bedrock project</>");
            return Command::FAILURE;
        }

        $oldPlugin = 'web/app/mu-plugins/bedrock-cli-api.php';
        $newPluginDir = 'web/app/mu-plugins/bedrock-cli-plugin';

        // Check old plugin exists
        if (file_exists($oldPlugin)) {
            $output->writeln("<fg=yellow>⚠ Detected old MU-Plugin: bedrock-cli-api.php</>");
            
            // Migrate token if exists
            $token = $this->getExistingToken();
            if ($token) {
                $output->writeln("<fg=cyan>✓ Token found, will be preserved</>");
            }
            
            // Remove old plugin
            unlink($oldPlugin);
            $output->writeln("<fg=green>✓ Old plugin removed</>\n");
        }

        // Copy new plugin
        $sourcePath = __DIR__ . '/../../../mu-plugin';
        
        if (!file_exists($sourcePath)) {
            $output->writeln("<fg=red>✗ Error: New plugin source not found</>");
            return Command::FAILURE;
        }

        // Remove existing if present
        if (file_exists($newPluginDir)) {
            $output->writeln("<fg=yellow>⚠ Removing old version...</>");
            $removeResult = $this->recursiveRemove($newPluginDir);
            if (!$removeResult) {
                $output->writeln("<fg=red>✗ Permission denied. Run: sudo chown -R $USER:$USER web/app/mu-plugins</>");
                return Command::FAILURE;
            }
        }

        // Copy directory
        $this->recursiveCopy($sourcePath, $newPluginDir);
        $output->writeln("<fg=green>✓ New plugin installed</>");

        // Run composer install
        $output->writeln("<fg=cyan>→ Installing dependencies...</>");
        $process = new Process(['composer', 'install', '--no-dev', '--quiet'], $newPluginDir);
        $process->setTimeout(120);
        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln("<fg=green>✓ Dependencies installed</>");
        }

        // Create logs directory
        $logsDir = $newPluginDir . '/logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $output->writeln("\n<fg=green>✓ Update complete!</>");
        $output->writeln("<fg=gray>  Location: {$newPluginDir}</>\n");

        return Command::SUCCESS;
    }

    private function getExistingToken(): ?string
    {
        $wpConfigPath = 'config/application.php';
        if (!file_exists($wpConfigPath)) return null;

        // Try to get token from WordPress if possible
        // This is a simplified check - actual token is in database
        return null;
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

    private function recursiveRemove(string $dir): bool
    {
        if (!is_dir($dir)) return true;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                if (!$this->recursiveRemove($path)) return false;
            } else {
                if (!@unlink($path)) return false;
            }
        }
        
        return @rmdir($dir);
    }
}
