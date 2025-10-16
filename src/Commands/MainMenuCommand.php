<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MainMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('main-menu')
             ->setDescription('Menú principal interactivo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║</> <fg=yellow;options=bold>    BEDROCK CLI - Menú Principal</> <fg=cyan>    ║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $choices = [
                1 => '🐳 Docker - Gestión de contenedores',
                2 => '💾 Database - Gestión de base de datos',
                3 => '⚙️  Install - Instalar WordPress',
                4 => '🔌 Plugins - Gestión de plugins',
                5 => '🎨 Themes - Gestión de temas',
                6 => '📦 Backup - Crear backup',
                7 => '🔄 Update - Actualizar sistema',
                0 => '❌ Salir',
            ];

            $question = new ChoiceQuestion('Selecciona una opción:', $choices, 1);
            $question->setErrorMessage('Opción %s inválida.');

            $choice = $helper->ask($input, $output, $question);
            $selectedIndex = array_search($choice, $choices);
            
            if ($selectedIndex === 0) {
                $output->writeln('');
                $output->writeln('<info>👋 Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                1 => 'docker',
                2 => 'db',
                3 => 'install',
                4 => 'plugins',
                5 => 'themes',
                6 => 'backup',
                7 => 'update',
            ];

            $commandName = $commandMap[$selectedIndex];
            if ($commandName) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandName);
                $command->run($input, $output);
            }
        }

        return Command::SUCCESS;
    }
}
