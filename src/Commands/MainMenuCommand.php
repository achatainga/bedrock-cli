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
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</> <fg=yellow;options=bold>    BEDROCK CLI - Menú Principal</> <fg=cyan>    ║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = [
            '🐳 Docker - Gestión de contenedores',
            '💾 Database - Gestión de base de datos',
            '⚙️  Install - Instalar WordPress',
            '🔌 Plugins - Gestión de plugins',
            '🎨 Themes - Gestión de temas',
            '📦 Backup - Crear backup',
            '🔄 Update - Actualizar sistema',
            '❌ Salir',
        ];

        $question = new ChoiceQuestion('Selecciona una opción:', $choices, 0);
        $question->setErrorMessage('Opción %s inválida.');

        $choice = $helper->ask($input, $output, $question);
        $selectedIndex = array_search($choice, $choices);
        
        if ($selectedIndex === 7) {
            $output->writeln('');
            $output->writeln('<info>👋 Hasta luego!</info>');
            return Command::SUCCESS;
        }

        $commandMap = [
            0 => 'docker',
            1 => 'db',
            2 => 'install',
            3 => 'plugins',
            4 => 'themes',
            5 => 'backup',
            6 => 'update',
        ];

        $commandName = $commandMap[$selectedIndex];
        if ($commandName) {
            $output->writeln('');
            $command = $this->getApplication()->find($commandName);
            return $command->run($input, $output);
        }

        return Command::SUCCESS;
    }
}
