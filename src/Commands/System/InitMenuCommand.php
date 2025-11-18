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
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🚀 INIT - Inicializar Ambiente  <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<comment>Inicializa el proyecto para diferentes ambientes (production, staging, development).</comment>');
        $output->writeln('');

        $output->writeln(' <fg=cyan>[1]</> 🏭 Production (mínimo, sin fake data)');
        $output->writeln(' <fg=cyan>[2]</> 🎪 Staging (con fake data)');
        $output->writeln(' <fg=cyan>[3]</> 🛠️  Development (ambiente completo)');
        $output->writeln(' <fg=cyan>[4]</> ⚙️  Custom (opciones avanzadas)');
        $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-4]: </>', '0');
        $selectedIndex = $helper->ask($input, $output, $question);
        
        if (!is_numeric($selectedIndex) || $selectedIndex < 0 || $selectedIndex > 4) {
            $output->writeln('<error>Opción inválida</error>');
            return Command::FAILURE;
        }

        if ($selectedIndex === '0') {
            return Command::SUCCESS;
        }

        $envMap = [
            '1' => 'production',
            '2' => 'staging',
            '3' => 'development',
        ];

        if ($selectedIndex === '4') {
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
