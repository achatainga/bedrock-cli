<?php

namespace Roots\BedrockCli\Commands\Themes;

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
        $this->setName('themes:status')
             ->setDescription('Muestra información de un tema')
             ->addArgument('theme', InputArgument::REQUIRED, 'Nombre del tema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theme = $input->getArgument('theme');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $process = $wpcli->custom("theme get {$theme}");
        $this->runWithLoader($process, $output, "Consultando información del tema: {$theme}");

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }


}
