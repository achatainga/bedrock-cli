<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Actualizar sistema completo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>===== ACTUALIZACIÓN DEL SISTEMA =====</>');
        $output->writeln('');
        
        $process = $wpcli->coreUpdate();
        $this->runWithLoader($process, $output, 'Actualizando WordPress core');
        
        $output->writeln('');
        $process = $wpcli->pluginUpdate();
        $this->runWithLoader($process, $output, 'Actualizando plugins');
        
        $output->writeln('');
        $process = $wpcli->themeUpdate();
        $this->runWithLoader($process, $output, 'Actualizando temas');
        
        $output->writeln('');
        $output->writeln('<info>✓ Sistema actualizado completamente</info>');
        $output->writeln('');
        
        return Command::SUCCESS;
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
