<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Roots\BedrockCli\Services\DockerService;

class DockerCommand extends Command
{
    protected static $defaultName = 'docker';
    protected static $defaultDescription = 'Gestión de contenedores Docker';

    protected function configure(): void
    {
        $this
            ->addOption('up', null, InputOption::VALUE_NONE, 'Levantar contenedores')
            ->addOption('down', null, InputOption::VALUE_NONE, 'Bajar contenedores')
            ->addOption('restart', null, InputOption::VALUE_NONE, 'Reiniciar contenedores')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Ver estado')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Rebuild al levantar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();

        // Comandos directos
        if ($input->getOption('up')) {
            return $this->up($docker, $output, $input->getOption('build'));
        }
        if ($input->getOption('down')) {
            return $this->down($docker, $output);
        }
        if ($input->getOption('restart')) {
            return $this->restart($docker, $output);
        }
        if ($input->getOption('status')) {
            return $this->status($docker, $output);
        }

        // Menú interactivo
        return $this->showMenu($input, $output, $docker);
    }

    private function showMenu(InputInterface $input, OutputInterface $output, DockerService $docker): int
    {
        $helper = $this->getHelper('question');
        
        $question = new ChoiceQuestion(
            '<info>Selecciona una opción:</info>',
            [
                '1' => 'Levantar contenedores',
                '2' => 'Bajar contenedores',
                '3' => 'Reiniciar contenedores',
                '4' => 'Ver estado',
                '5' => 'Salir'
            ],
            '5'
        );

        $answer = $helper->ask($input, $output, $question);

        switch ($answer) {
            case '1':
                return $this->up($docker, $output, false);
            case '2':
                return $this->down($docker, $output);
            case '3':
                return $this->restart($docker, $output);
            case '4':
                return $this->status($docker, $output);
            case '5':
                return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function up(DockerService $docker, OutputInterface $output, bool $build): int
    {
        $output->writeln('<info>Levantando contenedores...</info>');
        $process = $docker->up($build);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores levantados</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al levantar contenedores</error>');
        return Command::FAILURE;
    }

    private function down(DockerService $docker, OutputInterface $output): int
    {
        $output->writeln('<info>Bajando contenedores...</info>');
        $process = $docker->down();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores bajados</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function restart(DockerService $docker, OutputInterface $output): int
    {
        $output->writeln('<info>Reiniciando contenedores...</info>');
        $process = $docker->restart();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores reiniciados</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function status(DockerService $docker, OutputInterface $output): int
    {
        $process = $docker->status();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
