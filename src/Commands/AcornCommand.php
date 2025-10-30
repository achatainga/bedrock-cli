<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;

class AcornCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('acorn')
            ->setDescription('Gestionar Roots Acorn (menú interactivo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            '<question>Selecciona una acción de Acorn:</question>',
            [
                '1' => 'Inicializar storage',
                '2' => 'Publicar configs',
                '3' => 'Inicializar + Publicar (ambos)',
                '4' => 'Limpiar cache y storage',
                '5' => 'Salir'
            ],
            '5'
        );

        $question->setErrorMessage('Opción %s inválida.');

        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case 'Inicializar storage':
                return $this->initStorage($output);

            case 'Publicar configs':
                return $this->publishConfigs($output);

            case 'Inicializar + Publicar (ambos)':
                $result = $this->initStorage($output);
                if ($result === Command::SUCCESS) {
                    return $this->publishConfigs($output);
                }
                return $result;

            case 'Limpiar cache y storage':
                return $this->cleanStorage($output);

            case 'Salir':
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function initStorage(OutputInterface $output): int
    {
        $output->writeln('<info>Inicializando Acorn storage...</info>');

        $process = Process::fromShellCommandline('docker-compose exec -T web wp acorn acorn:init storage');
        $process->setTimeout(60);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Storage inicializado</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Error al inicializar storage</error>');
        return Command::FAILURE;
    }

    private function publishConfigs(OutputInterface $output): int
    {
        $output->writeln('<info>Publicando configs de Acorn...</info>');

        $process = Process::fromShellCommandline('docker-compose exec -T web wp acorn vendor:publish --tag=acorn');
        $process->setTimeout(60);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Configs publicados</info>');
            $output->writeln('');
            $output->writeln('<comment>Archivos creados:</comment>');
            $output->writeln('  • config/app.php');
            $output->writeln('  • config/assets.php');
            $output->writeln('  • config/view.php');
            $output->writeln('  • config/auth.php');
            $output->writeln('  • config/database.php');
            $output->writeln('  • config/filesystems.php');
            $output->writeln('  • config/logging.php');
            $output->writeln('  • config/services.php');
            $output->writeln('  • config/session.php');
            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Error al publicar configs</error>');
        return Command::FAILURE;
    }

    private function cleanStorage(OutputInterface $output): int
    {
        $output->writeln('<info>Limpiando cache y storage...</info>');

        $commands = [
            'rm -rf storage/framework/cache/*',
            'rm -rf storage/framework/sessions/*',
            'rm -rf storage/framework/views/*',
            'rm -rf storage/logs/*'
        ];

        foreach ($commands as $cmd) {
            $process = Process::fromShellCommandline("docker-compose exec -T web sh -c '{$cmd}'");
            $process->run();
        }

        $output->writeln('<info>✓ Cache y storage limpiados</info>');
        $output->writeln('');
        $output->writeln('<comment>Carpetas limpiadas:</comment>');
        $output->writeln('  • storage/framework/cache/');
        $output->writeln('  • storage/framework/sessions/');
        $output->writeln('  • storage/framework/views/');
        $output->writeln('  • storage/logs/');

        return Command::SUCCESS;
    }
}
