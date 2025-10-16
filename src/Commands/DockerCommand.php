<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;

class DockerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('docker')
            ->setDescription('Gestión de contenedores Docker')

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
        
        while (true) {
            $choices = [
                1 => '<fg=green>Levantar</> contenedores',
                2 => '<fg=green>Bajar</> contenedores',
                3 => '<fg=green>Reiniciar</> contenedores',
                4 => '<fg=green>Reconstruir</> (sin caché)',
                5 => '<fg=green>Ver estado</>',
                6 => '<fg=green>Ver logs</>',
                0 => '<fg=yellow>Volver atrás</>',
            ];
            
            $question = new ChoiceQuestion(
                '<fg=cyan>Selecciona una opción:</>',
                $choices,
                1
            );
            $question->setAutocompleterValues(null);

            $answer = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $index = is_numeric($answer) ? (int)$answer : array_search($answer, $choices);
            
            if ($index === 0) {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case 1:
                    $this->up($docker, $output, false);
                    break;
                case 2:
                    $this->down($docker, $output);
                    break;
                case 3:
                    $this->restart($docker, $output);
                    break;
                case 4:
                    $this->rebuild($docker, $output);
                    break;
                case 5:
                    $this->status($docker, $output);
                    break;
                case 6:
                    $this->logs($docker, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function up(DockerService $docker, OutputInterface $output, bool $build): int
    {
        $output->writeln('<info>Levantando contenedores...</info>');
        $process = $docker->up($build);
        
        if (DIRECTORY_SEPARATOR !== '\\') {
            $process->setTty(true);
            $process->run();
        } else {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }
        
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

    private function rebuild(DockerService $docker, OutputInterface $output): int
    {
        $output->writeln('<info>Reconstruyendo contenedores sin caché...</info>');
        $process = $docker->rebuild();
        
        if (DIRECTORY_SEPARATOR !== '\\') {
            $process->setTty(true);
            $process->run();
        } else {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores reconstruidos</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al reconstruir contenedores</error>');
        return Command::FAILURE;
    }

    private function logs(DockerService $docker, OutputInterface $output): int
    {
        $output->writeln('<info>Mostrando logs (Ctrl+C para salir)...</info>');
        $process = $docker->logs();
        
        if (DIRECTORY_SEPARATOR !== '\\') {
            $process->setTty(true);
            $process->run();
        } else {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }
        
        return Command::SUCCESS;
    }
}
