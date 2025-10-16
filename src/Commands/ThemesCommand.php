<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThemesCommand extends Command
{
    protected static $defaultName = 'themes';
    protected static $defaultDescription = 'Gestión de temas';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>Comando themes pendiente de implementación</comment>');
        return Command::SUCCESS;
    }
}
