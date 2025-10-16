<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\SecurityService;

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
                3 => '<fg=green>Resetear</> base de datos',
                4 => '<fg=green>Importar</> SQL',
                5 => '<fg=green>Exportar</> SQL',
                6 => '<fg=green>Buscar/Reemplazar</> en DB',
                7 => '<fg=green>Ejecutar Query</> SQL',
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
                    $this->reset($wpcli, $output);
                    break;
                case 4:
                    $output->writeln('<comment>Función de importación interactiva pendiente</comment>');
                    break;
                case 5:
                    $output->writeln('<comment>Función de exportación interactiva pendiente</comment>');
                    break;
                case 6:
                    $this->searchReplace($input, $output, $wpcli);
                    break;
                case 7:
                    $this->query($input, $output, $wpcli);
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
        $helper = $this->getHelper('question');
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        if (!SecurityService::confirmDangerousAction(
            $input,
            $output,
            $helper,
            'Esta acción ELIMINARÁ PERMANENTEMENTE la base de datos.'
        )) {
            return Command::SUCCESS;
        }
        
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

    private function reset(WpCliService $wpcli, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        if (!SecurityService::confirmDangerousAction(
            $input,
            $output,
            $helper,
            'Esta acción BORRARÁ TODOS LOS DATOS de la base de datos.'
        )) {
            return Command::SUCCESS;
        }
        
        $output->writeln('<info>Reseteando base de datos...</info>');
        $process = $wpcli->dbReset();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos reseteada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al resetear base de datos</error>');
        return Command::FAILURE;
    }

    private function searchReplace(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        $search = $helper->ask($input, $output, new Question('<fg=yellow>Buscar:</>'));
        $replace = $helper->ask($input, $output, new Question('<fg=yellow>Reemplazar por:</>'));
        
        $output->writeln("<info>Buscando '{$search}' y reemplazando por '{$replace}'...</info>");
        $process = $wpcli->dbSearchReplace($search, $replace);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Reemplazo completado</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function query(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        $query = $helper->ask($input, $output, new Question('<fg=yellow>Query SQL:</>'));
        
        $output->writeln('<info>Ejecutando query...</info>');
        $process = $wpcli->dbQuery($query);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Query ejecutado</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }
}
