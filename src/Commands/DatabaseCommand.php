<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class DatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db')
            ->setDescription('Gestión de base de datos')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Crear base de datos')
            ->addOption('drop', null, InputOption::VALUE_NONE, 'Eliminar base de datos')
            ->addOption('import', null, InputOption::VALUE_REQUIRED, 'Importar SQL')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Exportar SQL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        if ($input->getOption('create')) {
            return $this->create($wpcli, $output);
        }
        if ($input->getOption('drop')) {
            return $this->drop($wpcli, $output);
        }
        if ($file = $input->getOption('import')) {
            return $this->import($wpcli, $output, $file);
        }
        if ($file = $input->getOption('export')) {
            return $this->export($wpcli, $output, $file);
        }

        return $this->showMenu($input, $output, $wpcli);
    }

    private function showMenu(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $choices = [
                1 => '<fg=green>Crear</> base de datos',
                2 => '<fg=green>Eliminar</> base de datos',
                3 => '<fg=green>Importar</> SQL',
                4 => '<fg=green>Exportar</> SQL',
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
                    $this->create($wpcli, $output);
                    break;
                case 2:
                    $this->drop($wpcli, $output);
                    break;
                case 3:
                    $output->writeln('<comment>Función de importación interactiva pendiente</comment>');
                    break;
                case 4:
                    $output->writeln('<comment>Función de exportación interactiva pendiente</comment>');
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function create(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Creando base de datos...</info>');
        $process = $wpcli->dbCreate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos creada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al crear base de datos</error>');
        return Command::FAILURE;
    }

    private function drop(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Eliminando base de datos...</info>');
        $process = $wpcli->dbDrop();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos eliminada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al eliminar base de datos</error>');
        return Command::FAILURE;
    }

    private function import(WpCliService $wpcli, OutputInterface $output, string $file): int
    {
        $output->writeln("<info>Importando {$file}...</info>");
        $process = $wpcli->dbImport($file);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos importada</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function export(WpCliService $wpcli, OutputInterface $output, string $file): int
    {
        $output->writeln("<info>Exportando a {$file}...</info>");
        $process = $wpcli->dbExport($file);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos exportada</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }
}
