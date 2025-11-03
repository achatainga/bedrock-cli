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
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK CLI - Menú Principal  </> <fg=cyan;options=bold>     ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $output->writeln(' <fg=cyan>[1]</>  ℹ️  Info         - Estado del proyecto');
            $output->writeln(' <fg=green>[2]</>  ⚙️  Setup        - Configuración inicial');
            $output->writeln(' <fg=magenta>[3]</>  📝 Profiles     - Gestión de profiles');
            $output->writeln(' <fg=blue>[4]</>  🎛️  Manage       - Sistema unificado (v2.0)');
            $output->writeln(' <fg=magenta>[5]</>  🚀 Init         - Inicializar ambiente');
            $output->writeln(' <fg=yellow>[6]</>  🔍 Search       - Buscar plugins/temas');
            $output->writeln(' <fg=green>[7]</>  🐳 Docker       - Gestión de contenedores');
            $output->writeln(' <fg=green>[8]</>  🗄️  Database     - Gestión de base de datos');
            $output->writeln(' <fg=green>[9]</>  ⚙️  Options      - Gestión de opciones WP');
            $output->writeln(' <fg=green>[10]</> 🔌 Plugins      - Gestión de plugins');
            $output->writeln(' <fg=green>[11]</> 🎨 Themes       - Gestión de temas');
            $output->writeln(' <fg=green>[12]</> 🌱 Acorn        - Gestión de Roots Acorn');
            $output->writeln(' <fg=green>[13]</> 💾 Backup       - Crear backup');
            $output->writeln(' <fg=red>[14]</>  🗑️  Reinstall    - Reinstalar (DESTRUCTIVO)');
            $output->writeln(' <fg=cyan>[15]</> 🩺 Doctor       - Verificar dependencias');
            $output->writeln(' <fg=red>[0]</>   ❌ Salir');
            $output->writeln('');

            $question = new Question('<fg=yellow>Selecciona una opción [0-15]:</> ', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            if (!is_numeric($selectedIndex) || $selectedIndex < 0 || $selectedIndex > 15) {
                $output->writeln('<error>Opción inválida. Usa 0-15.</error>');
                sleep(1);
                continue;
            }
            
            if ($selectedIndex === '0') {
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                '1' => 'info',
                '2' => 'setup',
                '3' => 'profile:menu',
                '4' => 'manage',
                '5' => 'init:menu',
                '6' => 'search:menu',
                '7' => 'docker',
                '8' => 'db',
                '9' => 'options',
                '10' => 'plugins',
                '11' => 'themes',
                '12' => 'acorn',
                '13' => 'backup',
                '14' => 'reinstall',
                '15' => 'doctor',
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
