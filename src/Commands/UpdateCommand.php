<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Actualizar sistema completo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>===== ACTUALIZACIÓN DEL SISTEMA =====</>');
        $output->writeln('');
        $output->writeln('<comment>Comando update pendiente de implementación</comment>');
        $output->writeln('');
        return Command::SUCCESS;
    }
}
