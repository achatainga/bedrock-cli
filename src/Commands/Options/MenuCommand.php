<?php

namespace Roots\BedrockCli\Commands\Options;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;

class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options')
             ->setDescription('Gestión de opciones de WordPress (wp_options)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<cyan>╔═══════════════════════════════════════╗</cyan>');
            $output->writeln('<cyan>║</cyan>   ⚙️  OPTIONS - wp_options          <cyan>║</cyan>');
            $output->writeln('<cyan>╚═══════════════════════════════════════╝</cyan>');
            $output->writeln('');
            $output->writeln('<comment>Exporta e importa configuraciones de WordPress (wp_options).</comment>');
            $output->writeln('<comment>Útil para sincronizar configuraciones entre entornos.</comment>');
            $output->writeln('');

            $output->writeln(' <fg=cyan>[1]</> 📤 Exportar - Extraer opciones a JSON');
            $output->writeln(' <fg=cyan>[2]</> 📥 Importar - Inyectar opciones desde JSON');
            $output->writeln(' <fg=cyan>[3]</> 📜 Listar - Ver archivos JSON disponibles');
            $output->writeln(' <fg=cyan>[4]</> ⚙️  Gestionar - Importar/Exportar opción específica');
            $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
            $output->writeln('');

            $question = new Question('Opción [0-4]: ', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            if (!is_numeric($selectedIndex) || $selectedIndex < 0 || $selectedIndex > 4) {
                $output->writeln('<error>Opción inválida</error>');
                continue;
            }
            
            if ($selectedIndex === 0) {
                return Command::SUCCESS;
            }

            $commandMap = [
                1 => 'options:pull',
                2 => 'options:push',
                3 => 'options:list',
                4 => 'options:manage',
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
