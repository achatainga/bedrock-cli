<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class InitMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('init:menu')
             ->setDescription('Menú de inicialización de ambientes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>  <fg=yellow;options=bold>INIT - Inicializar Ambiente</> <fg=magenta;options=bold>       ║</>');
        $output->writeln('<fg=magenta;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = [
            1 => 'Production (mínimo, sin fake data)',
            2 => 'Staging (con fake data)',
            3 => 'Development (ambiente completo)',
            4 => 'Custom (opciones avanzadas)',
            0 => 'Volver al menú principal',
        ];

        $question = new ChoiceQuestion('<fg=yellow>Selecciona ambiente:</>', $choices, 0);
        $choice = $helper->ask($input, $output, $question);
        $selectedIndex = array_search($choice, $choices);

        if ($selectedIndex === 0) {
            return Command::SUCCESS;
        }

        $envMap = [
            1 => 'production',
            2 => 'staging',
            3 => 'development',
        ];

        if ($selectedIndex === 4) {
            // Custom options
            $question = new Question('Ambiente (production/staging/development): ', 'production');
            $env = $helper->ask($input, $output, $question);
            
            $question = new Question('Archivo SQL (opcional, Enter para omitir): ');
            $dbFile = $helper->ask($input, $output, $question);
            
            $args = ['--env' => $env];
            if ($dbFile) {
                $args['--db-file'] = $dbFile;
            }
            
            $command = $this->getApplication()->find('init');
            $command->run(new ArrayInput($args), $output);
        } else {
            $env = $envMap[$selectedIndex];
            $command = $this->getApplication()->find('init');
            $command->run(new ArrayInput(['--env' => $env]), $output);
        }

        return Command::SUCCESS;
    }
}
