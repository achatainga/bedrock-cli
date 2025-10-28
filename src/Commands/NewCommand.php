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
            ->addOption('with-docker', null, InputOption::VALUE_NONE, 'Generar archivos Docker')
            ->addOption('no-acorn', null, InputOption::VALUE_NONE, 'NO instalar Roots Acorn (por defecto SÍ se instala)')
            ->addOption('no-redis', null, InputOption::VALUE_NONE, 'NO instalar Redis (por defecto SÍ se instala)')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Nombre de la base de datos')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Usuario de BD', 'root')
            ->addOption('db-pass', null, InputOption::VALUE_REQUIRED, 'Contraseña de BD', 'mysql')
            ->addOption('http-port', null, InputOption::VALUE_REQUIRED, 'Puerto HTTP')
            ->addOption('mysql-port', null, InputOption::VALUE_REQUIRED, 'Puerto MySQL')
            ->addOption('redis-port', null, InputOption::VALUE_REQUIRED, 'Puerto Redis')
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

        if (!$input->getOption('no-acorn')) {
            $this->installAcorn($name, $input, $output);
        }

        if (!$input->getOption('no-redis')) {
            $this->installRedis($name, $output);
        }

        if ($input->getOption('with-docker')) {
            $this->setupDocker($name, $input, $output);
        }

        $this->createExtendedStructure($name, $output);
        $this->generateEnvFile($name, $input, $output);
        $this->addBedrockCliToComposer($name, $output);
        $this->initGit($name, $output);

        $output->writeln('');
        $output->writeln("<info>✓ Proyecto '{$name}' creado exitosamente</info>");
        $output->writeln('');
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln("  cd {$name}");
        if ($input->getOption('with-docker')) {
            $output->writeln('  docker-compose up -d');
            $output->writeln('');
            $output->writeln('<comment>Lee README.md para workflow completo</comment>');
        } else {
            $output->writeln('  composer install');
        }
        $output->writeln('');
        $output->writeln('<comment>Tip: Usa -v, -vv o -vvv para ver output detallado de Composer</comment>');
        $output->writeln('<comment>Ejemplo: bedrock new proyecto -vvv --with-docker</comment>');

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

    private function installAcorn(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Instalando Roots Acorn...</info>');

        $process = new Process(['composer', 'config', 'allow-plugins.mnsami/composer-custom-directory-installer', 'true'], $name);
        $process->run();

        $process = new Process(['composer', 'require', 'roots/acorn', '--no-interaction'], $name);
        $process->setTimeout(300);
        $this->runWithLoader($process, $output, 'Instalando Roots Acorn');

        $output->writeln('<info>✓ Acorn instalado</info>');
        $output->writeln('');
        $output->writeln('<comment>⚠️  IMPORTANTE: Después de levantar Docker, ejecuta:</comment>');
        $output->writeln('<comment>  docker-compose exec web wp acorn acorn:init storage</comment>');
        $output->writeln('<comment>  docker-compose exec web wp acorn vendor:publish --tag=acorn</comment>');
        $output->writeln('');
        $output->writeln('<comment>Esto creará:</comment>');
        $output->writeln('<comment>  - app/Providers/AppServiceProvider.php</comment>');
        $output->writeln('<comment>  - app/Console/Commands/</comment>');
        $output->writeln('<comment>  - config/app.php, config/assets.php, config/view.php, etc.</comment>');
        $output->writeln('<comment>  - storage/ (logs, cache, framework)</comment>');
    }

    private function installRedis(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Instalando Redis Object Cache...</info>');

        $process = new Process(['composer', 'require', 'rhubarbgroup/redis-cache', '--no-interaction'], $name);
        $process->setTimeout(300);
        $this->runWithLoader($process, $output, 'Instalando Redis');

        $output->writeln('<info>✓ Redis configurado</info>');
    }

    private function setupDocker(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Configurando Docker...</info>');

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $projectName = $name;
        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        $dbUser = $input->getOption('db-user');
        $dbPass = $input->getOption('db-pass');

        $httpPort = $input->getOption('http-port') ?: $this->findFreePort(80, $output);
        $mysqlPort = $input->getOption('mysql-port') ?: $this->findFreePort(3306, $output);
        $redisPort = $input->getOption('redis-port') ?: $this->findFreePort(6379, $output);

        $vars = [
            '{{PROJECT_NAME}}' => $projectName,
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $dbUser,
            '{{DB_PASSWORD}}' => $dbPass,
            '{{HTTP_PORT}}' => $httpPort,
            '{{MYSQL_PORT}}' => $mysqlPort,
            '{{REDIS_PORT}}' => $redisPort,
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
        $dbName = str_replace('-', '_', $name);
        $vars = [
            '{{PROJECT_NAME}}' => $name,
            '{{DB_NAME}}' => $dbName
        ];
        $this->copyStub("{$stubsDir}/scripts/sanitize-db.sh.stub", "{$name}/scripts/sanitize-db.sh", $vars);
        $this->copyStub("{$stubsDir}/scripts/clean-database.php.stub", "{$name}/scripts/clean-database.php", $vars);
        chmod("{$name}/scripts/sanitize-db.sh", 0755);
        chmod("{$name}/scripts/clean-database.php", 0755);

        $output->writeln('<info>✓ Estructura creada</info>');
    }

    private function generateEnvFile(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Generando archivo .env...</info>');

        $stubsDir = dirname(__DIR__, 2) . '/stubs';
        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        $httpPort = $input->getOption('http-port') ?: $this->findFreePort(80, $output);
        
        $vars = [
            '{{PROJECT_NAME}}' => $name,
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $input->getOption('db-user'),
            '{{DB_PASSWORD}}' => $input->getOption('db-pass'),
            '{{HTTP_PORT}}' => $httpPort,
            '{{AUTH_KEY}}' => $this->generateKey(),
            '{{SECURE_AUTH_KEY}}' => $this->generateKey(),
            '{{LOGGED_IN_KEY}}' => $this->generateKey(),
            '{{NONCE_KEY}}' => $this->generateKey(),
            '{{AUTH_SALT}}' => $this->generateKey(),
            '{{SECURE_AUTH_SALT}}' => $this->generateKey(),
            '{{LOGGED_IN_SALT}}' => $this->generateKey(),
            '{{NONCE_SALT}}' => $this->generateKey(),
            '{{APP_KEY}}' => 'base64:' . base64_encode(random_bytes(32)),
        ];

        $this->copyStub("{$stubsDir}/.env.stub", "{$name}/.env", $vars);
        $this->copyStub("{$stubsDir}/.gitignore.stub", "{$name}/.gitignore", $vars);

        if ($input->getOption('with-docker')) {
            $vars['{{HTTP_PORT}}'] = $httpPort;
            $vars['{{MYSQL_PORT}}'] = $input->getOption('mysql-port') ?: $this->findFreePort(3306, $output);
            $vars['{{REDIS_PORT}}'] = $input->getOption('redis-port') ?: $this->findFreePort(6379, $output);
            $this->copyStub("{$stubsDir}/README.project.stub", "{$name}/README.md", $vars);
        }

        $output->writeln('<info>✓ Archivo .env generado</info>');
    }

    private function addBedrockCliToComposer(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Configurando bedrock-cli...</info>');

        $composerFile = "{$name}/composer.json";
        $composer = json_decode(file_get_contents($composerFile), true);

        $composer['repositories']['bedrock-cli'] = [
            'type' => 'vcs',
            'url' => 'https://github.com/achatainga/bedrock-cli'
        ];

        $composer['require']['achatainga/bedrock-cli'] = 'dev-develop';

        file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $process = new Process(['composer', 'update', 'achatainga/bedrock-cli', '--no-interaction'], $name);
        $process->setTimeout(300);
        $this->runWithLoader($process, $output, 'Instalando bedrock-cli');

        $output->writeln('<info>✓ bedrock-cli configurado</info>');
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

    private function findFreePort(int $preferred, OutputInterface $output): int
    {
        $port = $preferred;
        $maxAttempts = 100;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($this->isPortFree($port)) {
                if ($port !== $preferred) {
                    $output->writeln("<comment>Puerto {$preferred} ocupado, usando {$port}</comment>");
                }
                return $port;
            }
            $port++;
        }
        
        return $preferred;
    }

    private function isPortFree(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        
        return true;
    }

    private function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        $lastOutput = '';

        $process->start(function ($type, $buffer) use (&$lastOutput, $output) {
            $lastOutput = trim($buffer);
            if ($output->isVerbose()) {
                $output->writeln($buffer);
            }
        });

        while ($process->isRunning()) {
            $spinner = $frames[$frameIndex];
            $statusLine = substr($lastOutput, 0, 80);
            $padding = str_repeat(' ', 100);
            
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$spinner}</> {$statusLine}{$padding}");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }

        $padding = str_repeat(' ', 100);
        $output->write("\r<comment>{$message}</comment> <info>✓</info>{$padding}\n");
    }
}
