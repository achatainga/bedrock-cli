<?php

namespace Roots\BedrockCli\Commands;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\WpCliService;

abstract class BaseStatusCommand extends Command
{
    use SpinnerTrait;
    
    protected WpCliService $wpCliService;

    public function __construct(WpCliService $wpCliService)
    {
        parent::__construct();
        $this->wpCliService = $wpCliService;
    }

    abstract protected function getItemType(): string; // 'plugin' o 'theme'

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this->setName("{$type}s:status")
             ->setDescription("Muestra información de un " . ($type === 'plugin' ? 'plugin' : 'tema'))
             ->addArgument($type, InputArgument::REQUIRED, "Nombre del " . ($type === 'plugin' ? 'plugin' : 'tema'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $this->getItemType();
        $item = $input->getArgument($type);
        $process = $this->wpCliService->custom("{$type} get {$item}");
        $this->runWithLoader($process, $output, "Consultando información del " . ($type === 'plugin' ? 'plugin' : 'tema') . ": {$item}");

        if ($process->isSuccessful()) {
            $output->writeln($process->getOutput());
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
