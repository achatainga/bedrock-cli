<?php

namespace Roots\BedrockCli\Commands;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\StateService;

abstract class BaseActivateCommand extends Command
{
    use SpinnerTrait;
    
    protected DockerService $dockerService;
    protected WpCliService $wpCliService;
    protected StateService $stateService;

    public function __construct(DockerService $dockerService, WpCliService $wpCliService, StateService $stateService)
    {
        parent::__construct();
        $this->dockerService = $dockerService;
        $this->wpCliService = $wpCliService;
        $this->stateService = $stateService;
    }

    abstract protected function getItemType(): string; // 'plugin' o 'theme'
    abstract protected function getStepId(): int;

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this->setName("{$type}s:activate")
             ->setDescription("Activa un " . ($type === 'plugin' ? 'plugin' : 'tema'))
             ->addArgument($type, InputArgument::REQUIRED, "Nombre del " . ($type === 'plugin' ? 'plugin' : 'tema'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $this->getItemType();
        $item = $input->getArgument($type);
        $method = "{$type}Activate";
        
        $process = $this->wpCliService->$method($item);
        $this->runWithLoader($process, $output, "Activando " . ($type === 'plugin' ? 'plugin' : 'tema') . ": {$item}");

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ ' . ($type === 'plugin' ? 'Plugin' : 'Tema') . ' activado</info>');
            $this->stateService->markCompleted(getcwd(), $this->getStepId());
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
