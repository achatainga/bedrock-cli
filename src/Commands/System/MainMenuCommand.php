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
            $output->writeln(' <cyan>[1]</cyan> 🩺 Doctor   - Verificar dependencias');
            $output->writeln(' <cyan>[2]</cyan> ⚙️ Setup    - Configuración inicial');
            $output->writeln(' <cyan>[3]</cyan> 📋 Profiles - Crear/gestionar profiles');
            $output->writeln('');
            
            $output->writeln('<fg=green>⚡ DESARROLLO</>');
            $output->writeln(' <cyan>[4]</cyan> 🐳 Docker   - Levantar/bajar contenedores');
            $output->writeln(' <cyan>[5]</cyan> 🎛️ Manage   - Plugins, Themes, Dependencies');
            $output->writeln(' <cyan>[6]</cyan> 🗄️ Database - Gestión de base de datos');
            $output->writeln('');
            
            $output->writeln('<fg=cyan>🔍 CONTENIDO</>');
            $output->writeln(' <cyan>[7]</cyan> 🔍 Search   - Buscar en WordPress.org');
            $output->writeln(' <cyan>[8]</cyan> ℹ️ Info     - Estado del proyecto');
            $output->writeln('');
            
            $output->writeln('<fg=magenta>🔧 AVANZADO</>');
            $output->writeln(' <cyan>[9]</cyan> 🚀 Init     - Inicializar ambiente');
            $output->writeln('');
            
            $output->writeln(' <cyan>[O]</cyan> ⚙️ Options   - Gestión de wp_options');
            $output->writeln(' <cyan>[A]</cyan> 🌱 Acorn    - Roots Acorn');
            $output->writeln(' <cyan>[B]</cyan> 💾 Backup   - Crear backup');
            $output->writeln(' <cyan>[R]</cyan> 🗑️ Reinstall - Reinstalar (DESTRUCTIVO)');
            $output->writeln('');
            
            $output->writeln(' <red>[0]</red> ❌ Salir');
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
