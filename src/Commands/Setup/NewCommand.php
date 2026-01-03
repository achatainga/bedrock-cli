<?php

namespace Roots\BedrockCli\Commands\Setup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Roots\BedrockCli\Services\BlueprintService;
use Roots\BedrockCli\Services\AuthService;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Services\ProjectValidationService;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Roots\BedrockCli\Traits\SpinnerTrait;

class NewCommand extends Command
{
    use PremiumAssetsTrait, SpinnerTrait;

    private ProfileService $profileService;
    private ComposerService $composerService;
    private BlueprintService $blueprintService;
    private AuthService $authService;
    private StateService $stateService;
    private ProjectValidationService $validationService;

    public function __construct(
        ProfileService $profileService,
        ComposerService $composerService,
        BlueprintService $blueprintService,
        AuthService $authService,
        StateService $stateService,
        ProjectValidationService $validationService
    ) {
        parent::__construct();
        $this->profileService = $profileService;
        $this->composerService = $composerService;
        $this->blueprintService = $blueprintService;
        $this->authService = $authService;
        $this->stateService = $stateService;
        $this->validationService = $validationService;
    }

    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Crear nuevo proyecto Bedrock')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del proyecto')
            ->addOption('no-docker', null, InputOption::VALUE_NONE, 'NO generar archivos Docker (por defecto SÍ se genera)')
            ->addOption('no-acorn', null, InputOption::VALUE_NONE, 'NO instalar Roots Acorn (por defecto SÍ se instala)')
            ->addOption('no-redis', null, InputOption::VALUE_NONE, 'NO instalar Redis (por defecto SÍ se instala)')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Nombre de la base de datos')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Usuario de BD', 'root')
            ->addOption('db-pass', null, InputOption::VALUE_REQUIRED, 'Contraseña de BD', 'mysql')
            ->addOption('http-port', null, InputOption::VALUE_REQUIRED, 'Puerto HTTP')
            ->addOption('mysql-port', null, InputOption::VALUE_REQUIRED, 'Puerto MySQL')
            ->addOption('redis-port', null, InputOption::VALUE_REQUIRED, 'Puerto Redis')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Sobrescribir si existe')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profile a usar', 'default');
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

        if (!$input->getOption('no-docker')) {
            $this->setupDocker($name, $input, $output);
        }

        $this->createExtendedStructure($name, $output);
        $this->generateEnvFile($name, $input, $output);
        $this->copyApplicationConfig($name, $output);
        $this->installMuPlugin($name, $output);
        $profile = $this->applyProfile($name, $input, $output);
        $this->generateBlueprints($name, $input, $output);
        $this->copySeeders($name, $output);
        $this->initGit($name, $output);
        $this->generateWizardState($name, $input, $profile, $output);
        
        // Verificación y auto-fix post-creación
        if (!$input->getOption('no-docker')) {
            $this->verifyAndFixProject($name, $output);
        }

        $output->writeln('');
        $output->writeln("<info>✓ Proyecto '{$name}' creado exitosamente</info>");
        $output->writeln('');
        
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln("  cd {$name}");
        if (!$input->getOption('no-docker')) {
            $output->writeln('  docker-compose up -d');
            $output->writeln('');
            $output->writeln('<comment>Instalar WordPress:</comment>');
            $output->writeln('  docker-compose exec web wp core install \\');
            $output->writeln('    --url=http://localhost:' . ($input->getOption('http-port') ?: $this->findFreePort(80, $output)) . ' \\');
            $output->writeln('    --title="Mi Sitio" \\');
            $output->writeln('    --admin_user=admin \\');
            $output->writeln('    --admin_password=admin \\');
            $output->writeln('    --admin_email=admin@example.com \\');
            $output->writeln('    --locale=es_ES');
            $output->writeln('');
            
            // Mostrar instrucciones de Acorn si fue instalado
            if (!$input->getOption('no-acorn')) {
                $output->writeln('<comment>⚠️  ACORN INSTALADO - Configuración requerida:</comment>');
                $output->writeln('<comment>  Después de instalar WordPress, ejecuta:</comment>');
                $output->writeln('<comment>    docker-compose exec web wp plugin activate acorn</comment>');
                $output->writeln('<comment>    docker-compose exec web wp acorn acorn:init storage</comment>');
                $output->writeln('<comment>    docker-compose exec web wp acorn vendor:publish --tag=acorn</comment>');
                $output->writeln('');
                $output->writeln('<comment>  Esto creará automáticamente:</comment>');
                $output->writeln('<comment>    • app/Providers/AppServiceProvider.php</comment>');
                $output->writeln('<comment>    • app/Console/Commands/</comment>');
                $output->writeln('<comment>    • config/app.php, config/assets.php, config/view.php</comment>');
                $output->writeln('<comment>    • storage/ (logs, cache, framework)</comment>');
                $output->writeln('');
            }
            
            $output->writeln('<comment>Lee README.md para workflow completo</comment>');
        } else {
            $output->writeln('  composer install');
        }
        $output->writeln('');
        $output->writeln('<comment>Tip: Usa -v, -vv o -vvv para ver output detallado de Composer</comment>');
        $output->writeln('<comment>Ejemplo: bedrock new proyecto -vvv --no-docker</comment>');

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
        $process->setTimeout(900);
        $this->runWithLoader($process, $output, 'Instalando Roots Acorn');

        // Copiar acorn-boot.php a mu-plugins
        $stubsDir = $this->getStubsDir();
        $this->copyStub("{$stubsDir}/mu-plugins/acorn-boot.php.stub", "{$name}/web/app/mu-plugins/acorn-boot.php", []);

        $output->writeln('<info>✓ Acorn instalado</info>');
    }

    private function installRedis(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Instalando Redis Object Cache...</info>');

        $process = new Process(['composer', 'require', 'rhubarbgroup/redis-cache', '--no-interaction'], $name);
        $process->setTimeout(900);
        $this->runWithLoader($process, $output, 'Instalando Redis');

        $output->writeln('<info>✓ Redis instalado</info>');
    }

    private function setupDocker(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Configurando Docker...</info>');

        $stubsDir = $this->getStubsDir();
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

        $stubsDir = $this->getStubsDir();
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

        $stubsDir = $this->getStubsDir();
        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        $httpPort = $input->getOption('http-port') ?: $this->findFreePort(80, $output);
        
        // Solo agregar WP_PORT si el puerto no es 80 (evita fallos)
        $wpPortLine = '';
        $wpHomeUrl = 'http://localhost';
        if ($httpPort != 80) {
            $wpPortLine = "\nWP_PORT={$httpPort}";
            $wpHomeUrl = "http://localhost:\${WP_PORT}";
        }
        
        $vars = [
            '{{PROJECT_NAME}}' => $name,
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $input->getOption('db-user'),
            '{{DB_PASSWORD}}' => $input->getOption('db-pass'),
            '{{HTTP_PORT}}' => $httpPort,
            '{{WP_PORT_LINE}}' => $wpPortLine,
            '{{WP_HOME_URL}}' => $wpHomeUrl,
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

        if (!$input->getOption('no-docker')) {
            $vars['{{HTTP_PORT}}'] = $httpPort;
            $vars['{{MYSQL_PORT}}'] = $input->getOption('mysql-port') ?: $this->findFreePort(3306, $output);
            $vars['{{REDIS_PORT}}'] = $input->getOption('redis-port') ?: $this->findFreePort(6379, $output);
            $this->copyStub("{$stubsDir}/README.project.stub", "{$name}/README.md", $vars);
        }

        $output->writeln('<info>✓ Archivo .env generado</info>');
    }

    // private function addBedrockCliToComposer(string $name, OutputInterface $output): void
    // {
    //     $output->writeln('<info>Configurando bedrock-cli...</info>');

    //     $composerFile = "{$name}/composer.json";
    //     $composer = json_decode(file_get_contents($composerFile), true);

    //     $composer['repositories']['bedrock-cli'] = [
    //         'type' => 'vcs',
    //         'url' => 'https://github.com/achatainga/bedrock-cli'
    //     ];

    //     $composer['require']['achatainga/bedrock-cli'] = 'dev-develop';

    //     file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    //     $process = new Process(['composer', 'update', 'achatainga/bedrock-cli', '--no-interaction'], $name);
    //     $process->setTimeout(300);
    //     $this->runWithLoader($process, $output, 'Instalando bedrock-cli');

    //     $output->writeln('<info>✓ bedrock-cli configurado</info>');
    // }

    private function initGit(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Inicializando Git...</info>');

        $process = new Process(['git', 'init'], $name);
        $process->run();

        $process = new Process(['git', 'add', '.'], $name);
        $process->setTimeout(120);
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
        // Verificar localhost
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        
        // Verificar 0.0.0.0 (all interfaces)
        $connection = @fsockopen('0.0.0.0', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        
        // Verificar con netstat/ss si está disponible (más confiable)
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $cmd = "ss -tuln 2>/dev/null | grep -E ':{$port}\s' || netstat -tuln 2>/dev/null | grep -E ':{$port}\s'";
            exec($cmd, $output, $returnCode);
            if (!empty($output)) {
                return false; // Puerto en uso
            }
        }
        
        return true;
    }

    private function copyApplicationConfig(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Configurando application.php con guards de constantes...</info>');
        
        $stubsDir = $this->getStubsDir();
        $this->copyStub("{$stubsDir}/config/application.php.stub", "{$name}/config/application.php", []);
        $this->copyStub("{$stubsDir}/config/environments/development.php.stub", "{$name}/config/environments/development.php", []);
        $this->copyStub("{$stubsDir}/config/environments/staging.php.stub", "{$name}/config/environments/staging.php", []);
        
        $output->writeln('<info>✓ application.php y environments configurados</info>');
    }

    private function installMuPlugin(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Instalando Bedrock CLI MU-Plugin...</info>');

        // Usar el MU plugin actualizado en lugar del stub obsoleto
        $reflection = new \ReflectionClass(self::class);
        $classFile = $reflection->getFileName();
        $muPluginDir = dirname($classFile, 4) . DIRECTORY_SEPARATOR . 'mu-plugin';
        
        $destination = "{$name}/web/app/mu-plugins/bedrock-cli-plugin";
        
        if (is_dir($muPluginDir)) {
            // Copiar todo el directorio del MU plugin actualizado
            $this->recursiveCopy($muPluginDir, $destination);
            $output->writeln('<info>✓ MU-Plugin actualizado instalado</info>');
        } else {
            $output->writeln('<comment>⚠ MU-Plugin no encontrado, omitiendo...</comment>');
        }
    }
    
    private function recursiveCopy(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
    }

    private function applyProfile(string $name, InputInterface $input, OutputInterface $output): \Roots\BedrockCli\DTOs\Profile|array|null
    {
        $profileName = $input->getOption('profile');
        $output->writeln("<info>Aplicando profile '{$profileName}'...</info>");

        try {
            $profile = $this->profileService->loadProfile($profileName);
            
            // SIEMPRE detectar Docker mode en tiempo real
            $dockerMode = !$input->getOption('no-docker');
            if ($dockerMode) {
                $output->writeln('<fg=yellow>⚠️  Docker detectado. Forzando modo copia para assets premium.</>');                $profile['docker_mode'] = true;
            }
            
            // Descargar assets premium a caché si no existen
            $this->downloadPremiumAssetsToCache($profile, $output);
            
            $this->composerService->generateFromProfile($profile, $name);
            $this->composerService->copyProfileToProject($profile, $name);
            
            $output->writeln('<info>Instalando dependencias del profile...</info>');
            $process = new Process(['composer', 'update', '--no-interaction'], $name);
            $process->setTimeout(600);
            $this->runWithLoader($process, $output, 'Instalando dependencias');
            
            $this->composerService->copyCustomZipFiles($profile, $name);
            $output->writeln('<info>✓ Archivos .zip copiados</info>');
            
            $output->writeln("<info>✓ Profile '{$profileName}' aplicado exitosamente</info>");
            return $profile;
        } catch (\RuntimeException $e) {
            $output->writeln("<error>Error al aplicar profile: {$e->getMessage()}</error>");
            $output->writeln('<comment>Continuando sin profile...</comment>');
            return null;
        }
    }

    private function generateBlueprints(string $name, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('<info>Generando blueprints...</info>');

        $profileName = $input->getOption('profile');

        try {
            $profile = $this->profileService->loadProfile($profileName);
            $this->blueprintService->generateBlueprints($profile, $name);
            $output->writeln('<info>✓ Blueprints generados (production, staging, development)</info>');
        } catch (\RuntimeException $e) {
            $output->writeln("<comment>Blueprints no generados: {$e->getMessage()}</comment>");
        }
    }

    private function copySeeders(string $name, OutputInterface $output): void
    {
        $output->writeln('<info>Copiando seeders...</info>');

        $stubsDir = $this->getStubsDir() . '/seeders';
        $seedersDir = "{$name}/database/seeders";

        $seeders = [
            'DatabaseSeeder.php.stub',
            'CoreSeeder.php.stub',
            'WooCommerceSeeder.php.stub',
            'ThemeSeeder.php.stub',
            'PluginsSeeder.php.stub',
            'ProductsSeeder.php.stub',
        ];

        foreach ($seeders as $seeder) {
            $source = "{$stubsDir}/{$seeder}";
            $destination = "{$seedersDir}/" . str_replace('.stub', '', $seeder);
            
            if (file_exists($source)) {
                copy($source, $destination);
            }
        }

        $output->writeln('<info>✓ Seeders copiados (compatibles con Acorn y wp eval-file)</info>');
    }

    private function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $output->writeln("<comment>{$message}...</comment>");

        $process->start(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        $process->wait();

        if ($process->isSuccessful()) {
            $output->writeln("<info>✓ {$message} completado</info>");
        }
    }

    private function getStubsDir(): string
    {
        // Detectar si estamos en instalación global o local
        $reflection = new \ReflectionClass(self::class);
        $classFile = $reflection->getFileName();
        
        // Subir desde src/Commands/Setup/NewCommand.php hasta la raíz del paquete
        return dirname($classFile, 4) . DIRECTORY_SEPARATOR . 'stubs';
    }

    private function installPremiumAssets(string $name, \Roots\BedrockCli\DTOs\Profile|array|null $profile, InputInterface $input, OutputInterface $output): void
    {
        if (!$profile || empty($profile['plugins']['premium'])) {
            return;
        }

        $output->writeln('<info>Instalando assets premium...</info>');

        $hasVcsPlugins = false;
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs') {
                $hasVcsPlugins = true;
                break;
            }
        }

        if ($hasVcsPlugins) {
            $this->copyAuthJson($name, $output);
        }

        $this->configureComposerRepositories($name, $profile, $output);
        $this->requirePremiumPlugins($name, $profile, $output);
    }

    private function copyAuthJson(string $name, OutputInterface $output): void
    {
        $globalAuth = $this->authService->getAuthFile();

        if (!file_exists($globalAuth)) {
            $output->writeln('<comment>⚠️  auth.json no encontrado, omitiendo...</comment>');
            return;
        }

        copy($globalAuth, "{$name}/auth.json");
        file_put_contents("{$name}/.gitignore", "\nauth.json\n", FILE_APPEND);
        $output->writeln('<info>✓ auth.json copiado al proyecto</info>');
    }

    private function configureComposerRepositories(string $name, \Roots\BedrockCli\DTOs\Profile|array $profile, OutputInterface $output): void
    {
        $composerFile = "{$name}/composer.json";
        $composer = json_decode(file_get_contents($composerFile), true);

        $repositories = [];
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs' && !in_array($plugin['url'], array_column($repositories, 'url'))) {
                $repositories[] = ['type' => 'vcs', 'url' => $plugin['url']];
            } elseif ($plugin['source'] === 'path') {
                $repositories[] = ['type' => 'path', 'url' => $plugin['path'], 'options' => ['symlink' => true]];
            }
        }

        if (!empty($repositories)) {
            $composer['repositories'] = array_merge($composer['repositories'] ?? [], $repositories);
            file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $output->writeln('<info>✓ Repositories configurados</info>');
        }
    }

    private function requirePremiumPlugins(string $name, \Roots\BedrockCli\DTOs\Profile|array $profile, OutputInterface $output): void
    {
        foreach ($profile['plugins']['premium'] as $plugin) {
            $package = $plugin['source'] === 'vcs' 
                ? "detodo24/{$plugin['name']}" 
                : "local/{$plugin['name']}";
            
            $version = $plugin['version'];
            
            $output->writeln("<comment>Instalando {$package}:{$version}...</comment>");
            
            $process = new Process(['composer', 'require', "{$package}:{$version}", '--no-interaction'], $name);
            $process->setTimeout(900);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln("<info>✓ {$plugin['name']} instalado</info>");
            } else {
                $output->writeln("<error>✗ Error instalando {$plugin['name']}</error>");
            }
        }
    }

    private function generateWizardState(string $name, InputInterface $input, \Roots\BedrockCli\DTOs\Profile|array|null $profile, OutputInterface $output): void
    {
        $output->writeln('<info>Generando wizard de configuración...</info>');


        $httpPort = $input->getOption('http-port') ?: $this->findFreePort(80, $output);

        $themeName = 'twentytwentyfive';
        if ($profile && !empty($profile['themes'])) {
            $allThemes = array_merge(
                $profile['themes']['public'] ?? [],
                $profile['themes']['premium'] ?? [],
                $profile['themes']['custom'] ?? []
            );
            if (!empty($allThemes)) {
                $firstTheme = reset($allThemes);
                $themeName = is_array($firstTheme) ? ($firstTheme['slug'] ?? $firstTheme['name']) : $firstTheme;
            }
        }

        $config = [
            'http_port' => $httpPort,
            'has_acorn' => !$input->getOption('no-acorn'),
            'has_plugins' => $profile && (!empty($profile['plugins']['public']) || !empty($profile['plugins']['premium']) || !empty($profile['plugins']['custom'])),
            'has_theme' => $profile && !empty($profile['themes']),
            'theme_name' => $themeName
        ];

        $this->stateService->generateInitialState($name, $config);
        $output->writeln('<info>✓ bedrock_state.json creado</info>');
    }
    
    private function verifyAndFixProject(string $name, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Verificando proyecto...</info>');
        
        $projectPath = realpath($name);
        
        if (!$projectPath) {
            $output->writeln('<comment>⚠ No se pudo verificar el proyecto</comment>');
            return;
        }
        
        $inconsistencies = $this->validationService->detectInconsistencies($projectPath);
        
        if (empty($inconsistencies)) {
            $output->writeln('<info>✓ Proyecto verificado y listo</info>');
        } else {
            $output->writeln('<comment>⚠ Usa "bedrock doctor --fix" para resolver problemas</comment>');
        }
    }
    
    private function downloadPremiumAssetsToCache(\Roots\BedrockCli\DTOs\Profile|array $profile, OutputInterface $output): void
    {
        $cacheService = new \Roots\BedrockCli\Services\PremiumCacheService();
        
        // Descargar plugins premium
        if (!empty($profile['plugins']['premium'])) {
            foreach ($profile['plugins']['premium'] as $plugin) {
                if ($plugin['source'] === 'cache' && !empty($plugin['path'])) {
                    try {
                        if (!$cacheService->pluginExists($plugin['name'], $plugin['version'])) {
                            $output->writeln("<comment>📥 Descargando {$plugin['name']} v{$plugin['version']}...</comment>");
                            $cacheService->downloadPlugin(
                                $plugin['original_url'] ?? $plugin['url'],
                                $plugin['name'],
                                $plugin['version'],
                                $plugin['path']
                            );
                            $output->writeln("<info>✓ {$plugin['name']} descargado</info>");
                        }
                    } catch (\Exception $e) {
                        $output->writeln("<error>✗ Error descargando {$plugin['name']}: {$e->getMessage()}</error>");
                        throw $e;
                    }
                }
            }
        }
        
        // Descargar themes premium
        if (!empty($profile['themes']['premium'])) {
            foreach ($profile['themes']['premium'] as $theme) {
                if ($theme['source'] === 'cache' && !empty($theme['path'])) {
                    try {
                        if (!$cacheService->themeExists($theme['name'], $theme['version'])) {
                            $output->writeln("<comment>📥 Descargando {$theme['name']} v{$theme['version']}...</comment>");
                            $cacheService->downloadTheme(
                                $theme['original_url'] ?? $theme['url'],
                                $theme['name'],
                                $theme['version'],
                                $theme['path']
                            );
                            $output->writeln("<info>✓ {$theme['name']} descargado</info>");
                        }
                    } catch (\Exception $e) {
                        $output->writeln("<error>✗ Error descargando {$theme['name']}: {$e->getMessage()}</error>");
                        throw $e;
                    }
                }
            }
        }
    }
}
