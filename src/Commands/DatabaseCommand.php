<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class DatabaseCommand extends Command
{
    protected static $defaultName = 'db';
    protected static $defaultDescription = 'Gestión de base de datos';

    protected function configure(): void
    {
        $this
            ->addOption('create', null, InputOption::VALUE_NONE, 'Crear base de datos')
            ->addOption('import', null, InputOption::VALUE_REQUIRED, 'Importar SQL')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Exportar SQL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        // Comandos directos
        if ($input->getOption('create')) {
            return $this->create($wpcli, $output);
        }
        if ($file = $input->getOption('import')) {
            return $this->import($wpcli, $output, $file);
        }
        if ($file = $input->getOption('export')) {
            return $this->export($wpcli, $output, $file);
        }

        // Menú interactivo
        return $this->showMenu($input, $output, $wpcli);
    }

    private function showMenu(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        
        $question = new ChoiceQuestion(
            '<info>Selecciona una opción:</info>',
            [
                '1' => 'Crear base de datos',
                '2' => 'Importar SQL',
                '3' => 'Exportar SQL',
                '4' => 'Salir'
            ],
            '4'
        );

        $answer = $helper->ask($input, $output, $question);

        switch ($answer) {
            case '1':
                return $this->create($wpcli, $output);
            case '2':
                $output->writeln('<comment>Función de importación interactiva pendiente</comment>');
                return Command::SUCCESS;
            case '3':
                $output->writeln('<comment>Función de exportación interactiva pendiente</comment>');
                return Command::SUCCESS;
            case '4':
                return Command::SUCCESS;
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
