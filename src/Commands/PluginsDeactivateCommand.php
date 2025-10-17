<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class PluginsDeactivateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:deactivate')
             ->setDescription('Desactiva un plugin')
             ->addArgument('plugin', InputArgument::REQUIRED, 'Nombre del plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $input->getArgument('plugin');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $output->writeln("<info>Desactivando {$plugin}...</info>");
        $process = $wpcli->pluginDeactivate($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desactivado</info>');
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
