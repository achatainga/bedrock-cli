<?php

namespace Roots\BedrockCli\Commands\Themes;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\StateService;

class ActivateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('themes:activate')
             ->setDescription('Activa un tema')
             ->addArgument('theme', InputArgument::REQUIRED, 'Nombre del tema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theme = $input->getArgument('theme');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $process = $wpcli->themeActivate($theme);
        $this->runWithLoader($process, $output, "Activando tema: {$theme}");

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema activado</info>');
            $this->markStepCompleted(4);
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    private function markStepCompleted(int $stepId): void
    {
        $stateService = new StateService();
        $stateService->markCompleted(getcwd(), $stepId);
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
