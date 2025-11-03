<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class StatusCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:status')
             ->setDescription('Muestra información de un plugin')
             ->addArgument('plugin', InputArgument::REQUIRED, 'Nombre del plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $input->getArgument('plugin');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $process = $wpcli->custom("plugin get {$plugin}");
        $this->runWithLoader($process, $output, "Consultando información del plugin: {$plugin}");

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    protected function runWithLoader(\Symfony\Component\Process\Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
