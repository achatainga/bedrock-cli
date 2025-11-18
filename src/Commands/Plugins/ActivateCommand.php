<?php

namespace Roots\BedrockCli\Commands\Plugins;

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
    protected function configure(): void
    {
        $this->setName('plugins:activate')
             ->setDescription('Activa un plugin')
             ->addArgument('plugin', InputArgument::REQUIRED, 'Nombre del plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $input->getArgument('plugin');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $process = $wpcli->pluginActivate($plugin);
        $this->runWithLoader($process, $output, "Activando plugin: {$plugin}");

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin activado</info>');
            $this->markStepCompleted(3);
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    private function markStepCompleted(int $stepId): void
    {
        $stateService = new StateService();
        $stateService->markCompleted(getcwd(), $stepId);
    }


}
