<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class ThemesActivateCommand extends Command
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

        $output->writeln("<info>Activando {$theme}...</info>");
        $process = $wpcli->themeActivate($theme);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema activado</info>');
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
