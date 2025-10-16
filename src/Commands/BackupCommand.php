<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BackupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup')
            ->setDescription('Crear backup completo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>Comando backup pendiente de implementación</comment>');
        return Command::SUCCESS;
    }
}
