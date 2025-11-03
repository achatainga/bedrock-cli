<?php

namespace Roots\BedrockCli\Commands\Profile;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('profile:menu')
             ->setDescription('Menú de gestión de profiles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>  <fg=yellow;options=bold>PROFILES - Gestión de Profiles</> <fg=magenta;options=bold>    ║</>');
        $output->writeln('<fg=magenta;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = [
            1 => 'Crear nuevo profile',
            2 => 'Listar profiles',
            3 => 'Ver detalles de profile',
            4 => 'Editar profile',
            5 => 'Exportar profile desde proyecto',
            6 => 'Aplicar profile a proyecto',
            7 => 'Eliminar profile',
            0 => 'Volver al menú principal',
        ];

        $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 0);
        $choice = $helper->ask($input, $output, $question);
        $selectedIndex = array_search($choice, $choices);

        if ($selectedIndex === 0) {
            return Command::SUCCESS;
        }

        $commandMap = [
            1 => 'profile:create',
            2 => 'profile:list',
            3 => 'profile:show',
            4 => 'profile:edit',
            5 => 'profile:export',
            6 => 'profile:apply',
            7 => 'profile:delete',
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
