<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>      BEDROCK CLI v2.0          </> <fg=cyan;options=bold>     ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            $output->writeln('<fg=yellow>🚀 INICIO RÁPIDO</>');
            $output->writeln(' <fg=cyan>[1]</> 🩺 Doctor   - Verificar dependencias');
            $output->writeln(' <fg=cyan>[2]</> ⚙️ Setup    - Configuración inicial');
            $output->writeln(' <fg=cyan>[3]</> 📋 Profiles - Crear/gestionar profiles');
            $output->writeln('');
            
            $output->writeln('<fg=green>⚡ DESARROLLO</>');
            $output->writeln(' <fg=cyan>[4]</> 🐳 Docker   - Levantar/bajar contenedores');
            $output->writeln(' <fg=cyan>[5]</> 🎛️ Manage   - Plugins, Themes, Dependencies');
            $output->writeln(' <fg=cyan>[6]</> 🗄️ Database - Gestión de base de datos');
            $output->writeln('');
            
            $output->writeln('<fg=cyan>🔍 CONTENIDO</>');
            $output->writeln(' <fg=cyan>[7]</> 🔍 Search   - Buscar en WordPress.org');
            $output->writeln(' <fg=cyan>[8]</> ℹ️ Info     - Estado del proyecto');
            $output->writeln('');
            
            $output->writeln('<fg=magenta>🔧 AVANZADO</>');
            $output->writeln(' <fg=cyan>[9]</> 🚀 Init     - Inicializar ambiente');
            $output->writeln('');
            
            $output->writeln(' <fg=cyan>[O]</> ⚙️ Options   - Gestión de wp_options');
            $output->writeln(' <fg=cyan>[A]</> 🌱 Acorn    - Roots Acorn');
            $output->writeln(' <fg=cyan>[B]</> 💾 Backup   - Crear backup');
            $output->writeln(' <fg=cyan>[R]</> 🗑️ Reinstall - Reinstalar (DESTRUCTIVO)');
            $output->writeln('');
            
            $output->writeln(' <fg=red>[0]</> ❌ Salir');
            $output->writeln('');

            $question = new Question('<fg=yellow>Opción [0-9, O, A, B, R]:</> ', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $selectedIndex = strtoupper($selectedIndex);
            
            $validOptions = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'O', 'A', 'B', 'R'];
            if (!in_array($selectedIndex, $validOptions)) {
                $output->writeln('<error>Opción inválida. Usa 0-9, O, A, B, R.</error>');
                sleep(1);
                continue;
            }
            
            if ($selectedIndex === '0') {
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                '1' => 'doctor',
                '2' => 'setup',
                '3' => 'profile:menu',
                '4' => 'docker',
                '5' => 'manage',
                '6' => 'db',
                '7' => 'search:menu',
                '8' => 'info',
                '9' => 'init:menu',
                'O' => 'options',
                'A' => 'acorn',
                'B' => 'backup',
                'R' => 'reinstall',
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
