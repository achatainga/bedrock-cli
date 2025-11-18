<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class StatusCommand extends Command
{
    use SpinnerTrait;
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


}
