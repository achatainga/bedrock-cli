<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class SearchMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('search:menu')
             ->setDescription('Menú de búsqueda WordPress.org');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=yellow;options=bold>║</>  <fg=cyan;options=bold>SEARCH - WordPress.org</> <fg=yellow;options=bold>           ║</>');
        $output->writeln('<fg=yellow;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = [
            1 => 'Buscar plugins',
            2 => 'Info de plugin',
            3 => 'Buscar temas',
            4 => 'Info de tema',
            0 => 'Volver al menú principal',
        ];

        $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 0);
        $choice = $helper->ask($input, $output, $question);
        $selectedIndex = array_search($choice, $choices);

        if ($selectedIndex === 0) {
            return Command::SUCCESS;
        }

        $commandMap = [
            1 => 'plugin:search',
            2 => 'plugin:info',
            3 => 'theme:search',
            4 => 'theme:info',
        ];

        $commandName = $commandMap[$selectedIndex];
        if ($commandName) {
            $output->writeln('');
            $command = $this->getApplication()->find($commandName);
            $command->run($input, $output);
        }

        return Command::SUCCESS;
    }
}
