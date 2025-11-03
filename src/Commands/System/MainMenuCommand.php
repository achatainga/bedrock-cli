<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;

class MainMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('menu')
             ->setDescription('Abre el menú interactivo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK CLI - Menú Principal  </> <fg=cyan;options=bold>     ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $choices = [
                1 => '<fg=cyan>Info</>         - Estado del proyecto y tareas pendientes',
                2 => '<fg=green>Setup</>        - Configuración inicial',
                3 => '<fg=magenta>Profiles</>     - Gestión de profiles',
                4 => '<fg=magenta>Init</>         - Inicializar ambiente',
                5 => '<fg=yellow>Search</>       - Buscar plugins/temas WordPress.org',
                6 => '<fg=green>Docker</>       - Gestión de contenedores',
                7 => '<fg=green>Database</>     - Gestión de base de datos',
                8 => '<fg=green>Options</>      - Gestión de opciones WP',
                9 => '<fg=green>Plugins</>      - Gestión de plugins',
                10 => '<fg=green>Themes</>       - Gestión de temas',
                11 => '<fg=green>Acorn</>        - Gestión de Roots Acorn',
                12 => '<fg=green>Backup</>       - Crear backup',
                13 => '<fg=red>Reinstall</>    - Reinstalar aplicación (DESTRUCTIVO)',
                14 => '<fg=cyan>Doctor</>       - Verificar dependencias del sistema',
                0 => '<fg=red>Salir</>',
            ];

            $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 1);
            $question->setAutocompleterValues(null);
            $question->setErrorMessage('<fg=red>Opción %s inválida.</>');

            $choice = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            $selectedIndex = array_search($choice, $choices);
            
            if ($selectedIndex === 0) {
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                1 => 'info',
                2 => 'setup',
                3 => 'profile:menu',
                4 => 'init:menu',
                5 => 'search:menu',
                6 => 'docker',
                7 => 'db',
                8 => 'options',
                9 => 'plugins',
                10 => 'themes',
                11 => 'acorn',
                12 => 'backup',
                13 => 'reinstall',
                14 => 'doctor',
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
