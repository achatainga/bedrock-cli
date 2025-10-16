<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class PluginsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('plugins')
            ->setDescription('Gestión de plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $question = new ChoiceQuestion(
            '<info>Selecciona una opción:</info>',
            [
                '1' => 'Listar plugins',
                '2' => 'Descomprimir ZIPs',
                '3' => 'Activar plugin',
                '4' => 'Salir'
            ],
            '4'
        );

        $answer = $helper->ask($input, $output, $question);

        switch ($answer) {
            case '1':
                $output->writeln('<comment>Función pendiente de implementación</comment>');
                break;
            case '2':
                $output->writeln('<comment>Función pendiente de implementación</comment>');
                break;
            case '3':
                $output->writeln('<comment>Función pendiente de implementación</comment>');
                break;
        }

        return Command::SUCCESS;
    }
}
