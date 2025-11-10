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

        // Verify we're in a Bedrock project
        if (!file_exists('web/app/mu-plugins')) {
            $output->writeln("<fg=red>✗ Error: Not a Bedrock project (web/app/mu-plugins not found)</>");
            return Command::FAILURE;
        }

        $targetPath = 'web/app/mu-plugins/bedrock-cli-api.php';

        // Check if already installed
        if (file_exists($targetPath)) {
            $output->writeln("<fg=yellow>⚠ MU-Plugin already installed at:</>");
            $output->writeln("<fg=gray>  {$targetPath}</>\n");
            return Command::SUCCESS;
        }

        // Copy stub to target
        $stubPath = __DIR__ . '/../../../stubs/mu-plugins/bedrock-cli-api.php';
        
        if (!file_exists($stubPath)) {
            $output->writeln("<fg=red>✗ Error: Stub file not found</>");
            return Command::FAILURE;
        }

        if (!copy($stubPath, $targetPath)) {
            $output->writeln("<fg=red>✗ Error: Failed to copy MU-Plugin</>");
            return Command::FAILURE;
        }

        $output->writeln("<fg=green>✓ MU-Plugin installed successfully</>");
        $output->writeln("<fg=gray>  Location: {$targetPath}</>");
        $output->writeln("\n<fg=cyan>The plugin will auto-generate a security token on first use.</>\n");

        return Command::SUCCESS;
    }
}
