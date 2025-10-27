<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class SeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('seed')
            ->setDescription('Ejecutar seeders de base de datos')
            ->addOption('class', null, InputOption::VALUE_REQUIRED, 'Seeder específico a ejecutar')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Resetear DB antes de seed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        $projectRoot = getcwd();
        $seedersPath = $projectRoot . '/database/seeders';
        
        if (!is_dir($seedersPath)) {
            $output->writeln('<error>Carpeta database/seeders no encontrada</error>');
            $output->writeln('<comment>Crear con: mkdir -p database/seeders</comment>');
            return Command::FAILURE;
        }
        
        if ($input->getOption('fresh')) {
            $process = $wpcli->dbReset();
            $this->runWithLoader($process, $output, 'Reseteando base de datos');
            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error reseteando DB</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>✓ Base de datos reseteada</info>');
            $output->writeln('');
        }
        
        $class = $input->getOption('class');
        
        if ($class) {
            return $this->runSeeder($output, $wpcli, $seedersPath, $class);
        }
        
        return $this->runDatabaseSeeder($output, $wpcli, $seedersPath);
    }
    
    private function runSeeder(OutputInterface $output, WpCliService $wpcli, string $path, string $class): int
    {
        $file = $path . '/' . $class . '.php';
        
        if (!file_exists($file)) {
            $output->writeln("<error>Seeder no encontrado: {$class}</error>");
            return Command::FAILURE;
        }
        
        $dockerPath = str_replace(getcwd(), '/var/www/html', $file);
        $dockerPath = str_replace('\\', '/', $dockerPath);
        
        $process = $wpcli->custom("eval-file {$dockerPath}");
        $this->runWithLoader($process, $output, "🌱 Ejecutando seeder: {$class}");
        
        if ($process->isSuccessful()) {
            $output->writeln("<info>✓ {$class} completado</info>");
            return Command::SUCCESS;
        }
        
        $output->writeln("<error>✗ Error en {$class}</error>");
        return Command::FAILURE;
    }
    
    private function runDatabaseSeeder(OutputInterface $output, WpCliService $wpcli, string $path): int
    {
        $databaseSeeder = $path . '/DatabaseSeeder.php';
        
        if (file_exists($databaseSeeder)) {
            return $this->runSeeder($output, $wpcli, $path, 'DatabaseSeeder');
        }
        
        $seeders = glob($path . '/*Seeder.php');
        
        if (empty($seeders)) {
            $output->writeln('<comment>No hay seeders para ejecutar</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<info>🌱 Ejecutando todos los seeders...</info>');
        $output->writeln('');
        
        foreach ($seeders as $seeder) {
            $class = basename($seeder, '.php');
            $result = $this->runSeeder($output, $wpcli, $path, $class);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
            $output->writeln('');
        }
        
        $output->writeln('<info>✓ Seeding completado exitosamente</info>');
        return Command::SUCCESS;
    }

    protected function runWithLoader(\Symfony\Component\Process\Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
