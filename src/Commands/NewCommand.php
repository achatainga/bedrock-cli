<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Crear nuevo proyecto Bedrock')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del proyecto')
            ->addOption('with-acorn', null, InputOption::VALUE_NONE, 'Instalar Roots Acorn')
            ->addOption('with-docker', null, InputOption::VALUE_NONE, 'Generar archivos Docker')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Nombre de la base de datos')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Usuario de BD', 'root')
            ->addOption('db-pass', null, InputOption::VALUE_REQUIRED, 'Contraseña de BD', 'mysql')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Sobrescribir si existe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $force = $input->getOption('force');
        
        if (is_dir($name) && !$force) {
            $output->writeln("<error>El directorio '{$name}' ya existe. Usa --force para sobrescribir.</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Creando proyecto Bedrock: {$name}</info>");
        $output->writeln('');

        if ($this->createBedrockProject($name, $output) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        if ($input->getOption('with-acorn')) {
            $this->installAcorn($name, $output);
        }

        if ($input->getOption('with-docker')) {
            $this->setupDocker($name, $input, $output);
        }

        $this->createExtendedStructure($name, $output);
        $this->generateEnvFile($name, $input, $output);
        $this->initGit($name, $output);

        $output->writeln('');
        $output->writeln("<info>✓ Proyecto '{$name}' creado exitosamente</info>");
        $output->writeln('');
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln("  cd {$name}");
        if ($input->getOption('with-docker')) {
            $output->writeln('  docker-compose up -d');
        }
        $output->writeln('  composer install');

        return Command::SUCCESS;
    }

    private function createBedrockProject(string $name, OutputInterface $output): int
    {
        $process = new Process(['composer', 'create-project', 'roots/bedrock', $name, '--no-interaction']);
        $process->setTimeout(600);

        $this->runWithLoader($process, $output, 'Instalando Roots Bedrock');

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al crear proyecto Bedrock</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function installAcorn(string $name, OutputInterface $output): void
    {
        $process = new Process(['composer', 'require', 'roots/acorn'], $name);
        $process->setTimeout(300);

        $this->runWithLoader($process, $output, 'Instalando Roots Acorn');
    }

    private function setupDocker(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Configurando Docker...</info>');

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $projectName = $name;
        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        $dbUser = $input->getOption('db-user');
        $dbPass = $input->getOption('db-pass');

        $vars = [
            '{{PROJECT_NAME}}' => $projectName,
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $dbUser,
            '{{DB_PASSWORD}}' => $dbPass,
        ];

        $this->copyStub("{$stubsDir}/docker-compose.yml.stub", "{$name}/docker-compose.yml", $vars);
        $this->copyStub("{$stubsDir}/Dockerfile.web.stub", "{$name}/Dockerfile.web", $vars);
        
        @mkdir("{$name}/docker/nginx", 0755, true);
        @mkdir("{$name}/docker/mysql", 0755, true);
        
        $this->copyStub("{$stubsDir}/docker/nginx/default.conf.stub", "{$name}/docker/nginx/default.conf", $vars);
        $this->copyStub("{$stubsDir}/docker/mysql/client.cnf.stub", "{$name}/docker/mysql/client.cnf", $vars);
        $this->copyStub("{$stubsDir}/docker/mysql/my.cnf.stub", "{$name}/docker/mysql/my.cnf", $vars);

        $output->writeln('<info>✓ Archivos Docker creados</info>');
    }

    private function createExtendedStructure(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Creando estructura extendida...</info>');

        $dirs = [
            "{$name}/database/migrations",
            "{$name}/database/seeders",
            "{$name}/database/snapshots",
            "{$name}/config/plugins",
            "{$name}/scripts",
        ];

        foreach ($dirs as $dir) {
            @mkdir($dir, 0755, true);
            file_put_contents("{$dir}/.gitkeep", '');
        }

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $vars = ['{{PROJECT_NAME}}' => $name];
        $this->copyStub("{$stubsDir}/scripts/sanitize-db.sh.stub", "{$name}/scripts/sanitize-db.sh", $vars);
        chmod("{$name}/scripts/sanitize-db.sh", 0755);

        $output->writeln('<info>✓ Estructura creada</info>');
    }

    private function generateEnvFile(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Generando archivo .env...</info>');

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        
        $vars = [
            '{{PROJECT_NAME}}' => $name,
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $input->getOption('db-user'),
            '{{DB_PASSWORD}}' => $input->getOption('db-pass'),
            '{{AUTH_KEY}}' => $this->generateKey(),
            '{{SECURE_AUTH_KEY}}' => $this->generateKey(),
            '{{LOGGED_IN_KEY}}' => $this->generateKey(),
            '{{NONCE_KEY}}' => $this->generateKey(),
            '{{AUTH_SALT}}' => $this->generateKey(),
            '{{SECURE_AUTH_SALT}}' => $this->generateKey(),
            '{{LOGGED_IN_SALT}}' => $this->generateKey(),
            '{{NONCE_SALT}}' => $this->generateKey(),
        ];

        $this->copyStub("{$stubsDir}/.env.stub", "{$name}/.env", $vars);
        $this->copyStub("{$stubsDir}/.gitignore.stub", "{$name}/.gitignore", $vars);

        $output->writeln('<info>✓ Archivo .env generado</info>');
    }

    private function initGit(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Inicializando Git...</info>');

        $process = new Process(['git', 'init'], $name);
        $process->run();

        $process = new Process(['git', 'add', '.'], $name);
        $process->run();

        $process = new Process(['git', 'commit', '-m', 'Initial commit'], $name);
        $process->run();

        $output->writeln('<info>✓ Git inicializado</info>');
    }

    private function copyStub(string $stub, string $destination, array $vars): void
    {
        $content = file_get_contents($stub);
        $content = str_replace(array_keys($vars), array_values($vars), $content);
        file_put_contents($destination, $content);
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function runWithLoader(Process $process, OutputInterface $output, string $message): void
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
