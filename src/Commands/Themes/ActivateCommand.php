<?php

namespace Roots\BedrockCli\Commands\Themes;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\StateService;

class ActivateCommand extends Command
{
    use SpinnerTrait;
    
    private DockerService $dockerService;
    private WpCliService $wpCliService;
    private StateService $stateService;

    public function __construct(DockerService $dockerService, WpCliService $wpCliService, StateService $stateService)
    {
        parent::__construct();
        $this->dockerService = $dockerService;
        $this->wpCliService = $wpCliService;
        $this->stateService = $stateService;
    }

    protected function configure(): void
    {
        $this->setName('themes:activate')
             ->setDescription('Activa un tema')
             ->addArgument('theme', InputArgument::REQUIRED, 'Nombre del tema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theme = $input->getArgument('theme');
        $process = $this->wpCliService->themeActivate($theme);
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
        $this->stateService->markCompleted(getcwd(), $stepId);
    }


}
