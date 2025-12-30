<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Roots\BedrockCli\Services\VcsValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ApplyCommand extends Command
{
    use ProjectSelectorTrait;
    
    private ProfileService $profileService;
    private ComposerService $composerService;
    private VcsValidator $vcsValidator;

    public function __construct(
        ProfileService $profileService,
        ComposerService $composerService,
        VcsValidator $vcsValidator
    ) {
        parent::__construct();
        $this->profileService = $profileService;
        $this->composerService = $composerService;
        $this->vcsValidator = $vcsValidator;
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:apply')
            ->setDescription('Aplicar un profile a un proyecto existente')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile')
            ->addOption('yes', 'y', null, 'Confirmar automáticamente sin preguntar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }
        
        $projectRoot = getcwd();

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "<question>¿Aplicar profile '{$name}' a este proyecto? Esto modificará composer.json (Y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        $profile = $this->profileService->loadProfile($name);
        
        // SIEMPRE detectar Docker mode en tiempo real
        $dockerDetected = $this->profileService->detectDockerMode($projectRoot);
        if ($dockerDetected) {
            $output->writeln('<fg=yellow>⚠️  Docker detectado. Forzando modo copia para assets premium.</>');
            $profile['docker_mode'] = true;
        }
        
        // Validar VCS plugins si existen
        if ($this->hasVcsPlugins($profile)) {
            $output->writeln('');
            $output->writeln('<info>🔍 Validando VCS plugins...</info>');
            $profile = $this->validateVcsPlugins($profile, $output);
            $this->profileService->saveProfile($name, $profile);
        }
        
        $output->writeln('');
        $output->writeln('<info>Aplicando profile...</info>');
        
        // Verificar si hay plugins en cache que no existen y actualizar cache automáticamente
        $this->ensureCacheUpdated($profile, $output);
        
        // IMPORTANTE: Regenerar composer.json ANTES de copiar el nuevo profile
        // para que cleanPreviousProfilePackages() pueda leer el profile anterior
        $this->composerService->generateFromProfile($profile, $projectRoot);
        $output->writeln('✓ composer.json actualizado');
        
        // Actualizar .bedrock/profile.json DESPUÉS de generar composer.json
        $this->composerService->copyProfileToProject($profile, $projectRoot);
        $output->writeln('✓ .bedrock/profile.json actualizado');
        
        // Copiar archivos .zip custom
        $this->composerService->copyCustomZipFiles($profile, $projectRoot);
        $output->writeln('✓ Archivos .zip copiados');
        
        $output->writeln('');
        $output->writeln('<info>Ejecutando composer update...</info>');
        
        $process = new \Symfony\Component\Process\Process(['composer', 'update', '--no-interaction'], $projectRoot);
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al ejecutar composer update</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<info>✓ Profile aplicado y dependencias instaladas</info>');
        $output->writeln('');
        
        // Configurar child theme para Acorn si es necesario
        $this->configureChildThemeForAcorn($profile, $projectRoot, $output);
        
        // Verificar si profile tiene activation_order
        if (isset($profile['activation_order'])) {
            $this->applyActivationOrder($input, $output, $profile, $projectRoot);
        }

        return Command::SUCCESS;
    }
    
    private function configureChildThemeForAcorn(\Roots\BedrockCli\DTOs\Profile|array $profile, string $projectRoot, OutputInterface $output): void
    {
        // Verificar si Acorn está instalado
        $composerJson = json_decode(file_get_contents($projectRoot . '/composer.json'), true);
        if (!isset($composerJson['require']['roots/acorn'])) {
            return; // Acorn no instalado, skip
        }
        
        // Obtener tema del profile
        $themes = $profile['themes']['premium'] ?? [];
        if (empty($themes)) {
            return;
        }
        
        foreach ($themes as $theme) {
            $themeName = $theme['name'];
            $themePath = $projectRoot . '/web/app/themes/' . $themeName;
            
            // Verificar si es child theme (contiene 'child' en el nombre)
            if (!str_contains($themeName, 'child')) {
                continue;
            }
            
            if (!is_dir($themePath)) {
                continue;
            }
            
            $output->writeln('<info>🔧 Configurando child theme para Acorn...</info>');
            
            // 1. Crear estructura app/Providers
            $providersDir = $themePath . '/app/Providers';
            if (!is_dir($providersDir)) {
                mkdir($providersDir, 0755, true);
                $output->writeln('  ✓ Creado: app/Providers/');
            }
            
            // 2. Crear ThemeServiceProvider.php
            $providerFile = $providersDir . '/ThemeServiceProvider.php';
            if (!file_exists($providerFile)) {
                $providerContent = <<<'PHP'
<?php

namespace App\Providers;

use Roots\Acorn\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        //
    }
}

PHP;
                file_put_contents($providerFile, $providerContent);
                $output->writeln('  ✓ Creado: ThemeServiceProvider.php');
            }
            
            // 3. Actualizar composer.json del tema
            $themeComposerPath = $themePath . '/composer.json';
            if (file_exists($themeComposerPath)) {
                $themeComposer = json_decode(file_get_contents($themeComposerPath), true);
                
                if (!isset($themeComposer['autoload']['psr-4']['App\\'])) {
                    $themeComposer['autoload'] = $themeComposer['autoload'] ?? [];
                    $themeComposer['autoload']['psr-4'] = $themeComposer['autoload']['psr-4'] ?? [];
                    $themeComposer['autoload']['psr-4']['App\\'] = 'app/';
                    
                    file_put_contents(
                        $themeComposerPath,
                        json_encode($themeComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );
                    $output->writeln('  ✓ Actualizado: composer.json (autoload PSR-4)');
                    
                    // 4. Regenerar autoload
                    $process = new \Symfony\Component\Process\Process(['composer', 'dump-autoload'], $projectRoot);
                    $process->run();
                    if ($process->isSuccessful()) {
                        $output->writeln('  ✓ Autoload regenerado');
                    }
                    
                    // 5. Limpiar cache de Acorn
                    $process = new \Symfony\Component\Process\Process(
                        ['docker-compose', 'exec', '-T', 'web', 'wp', 'acorn', 'optimize:clear'],
                        $projectRoot
                    );
                    $process->run();
                    if ($process->isSuccessful()) {
                        $output->writeln('  ✓ Cache de Acorn limpiado');
                    }
                }
            }
            
            $output->writeln('<info>✓ Child theme configurado para Acorn</info>');
            $output->writeln('');
            
            // 6. Ajustar permisos de cache
            $output->writeln('<comment>Ajustando permisos de cache...</comment>');
            $process = new \Symfony\Component\Process\Process(
                ['docker-compose', 'exec', '-T', 'web', 'chown', '-R', 'www-data:www-data', '/var/www/html/web/app/cache'],
                $projectRoot
            );
            $process->run();
            $process = new \Symfony\Component\Process\Process(
                ['docker-compose', 'exec', '-T', 'web', 'chmod', '-R', '755', '/var/www/html/web/app/cache'],
                $projectRoot
            );
            $process->run();
            $output->writeln('  ✓ Permisos de cache ajustados');
            $output->writeln('');
        }
    }
    
    private function applyActivationOrder(InputInterface $input, OutputInterface $output, \Roots\BedrockCli\DTOs\Profile|array $profile, string $projectRoot): void
    {
        $orderData = $profile['activation_order'];
        
        // Guardar en config/plugins/activation-order.json
        $configDir = $projectRoot . '/config/plugins';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $orderFile = $configDir . '/activation-order.json';
        file_put_contents($orderFile, json_encode([
            'activation_order' => $orderData['order'],
            'dependencies' => $orderData['dependencies'] ?? [],
            'created_at' => $orderData['updated_at']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $output->writeln('<info>✓ Orden de activación guardado</info>');
        
        // Preguntar si aplicar ahora
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<fg=yellow>¿Aplicar orden de activación ahora? [S/n]:</> ',
                true
            );
            
            if ($helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<info>Activando plugins en orden...</info>');
                
                $process = new \Symfony\Component\Process\Process(
                    ['php', 'vendor/bin/bedrock', 'plugins:order', 'activate'],
                    $projectRoot
                );
                $process->setTimeout(300);
                $process->run(function ($type, $buffer) use ($output) {
                    $output->write($buffer);
                });
                
                if ($process->isSuccessful()) {
                    $output->writeln('<info>✓ Plugins activados correctamente</info>');
                } else {
                    $output->writeln('<error>Error al activar plugins</error>');
                }
            }
        }
    }
    
    private function ensureCacheUpdated(\Roots\BedrockCli\DTOs\Profile|array $profile, OutputInterface $output): void
    {
        $cacheDir = $_SERVER['HOME'] . '/.bedrock/cache';
        $missingPlugins = [];
        
        // Verificar plugins premium en cache
        foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
            if ($plugin['source'] === 'cache') {
                $zipPath = $cacheDir . '/' . $plugin['path'] . basename($plugin['path'], '/') . '.zip';
                if (!file_exists($zipPath)) {
                    $missingPlugins[] = $plugin['name'] . ' v' . $plugin['version'];
                }
            }
        }
        
        // Verificar themes premium en cache
        foreach ($profile['themes']['premium'] ?? [] as $theme) {
            if ($theme['source'] === 'cache') {
                $zipPath = $cacheDir . '/' . $theme['path'] . basename($theme['path'], '/') . '.zip';
                if (!file_exists($zipPath)) {
                    $missingPlugins[] = $theme['name'] . ' v' . $theme['version'];
                }
            }
        }
        
        if (!empty($missingPlugins)) {
            $output->writeln('');
            $output->writeln('<fg=yellow>⚠️  Plugins/themes no encontrados en cache:</>');            foreach ($missingPlugins as $missing) {
                $output->writeln("  • {$missing}");
            }
            $output->writeln('');
            $output->writeln('<info>🔄 Actualizando desde repositorio premium...</info>');
            
            // Buscar repo de premium assets
            $premiumRepo = null;
            foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
                if (isset($plugin['original_url'])) {
                    $premiumRepo = $plugin['original_url'];
                    break;
                }
            }
            
            if ($premiumRepo) {
                // Extraer path del repo clonado
                $repoName = basename($premiumRepo, '.git');
                $possiblePaths = [
                    $_SERVER['HOME'] . '/code/dt24/' . $repoName,
                    $_SERVER['HOME'] . '/code/' . $repoName,
                    getcwd() . '/../' . $repoName
                ];
                
                foreach ($possiblePaths as $repoPath) {
                    if (is_dir($repoPath . '/.git')) {
                        // Hacer git pull para obtener nuevas versiones
                        $output->writeln("<comment>Actualizando repositorio: {$repoPath}</comment>");
                        $process = new \Symfony\Component\Process\Process(['git', 'pull'], $repoPath);
                        $process->run();
                        
                        if ($process->isSuccessful()) {
                            $output->writeln('<info>✓ Repositorio actualizado</info>');
                        }
                        
                        // Copiar archivos faltantes del repo al cache
                        $output->writeln('<comment>Copiando al cache...</comment>');
                        $copied = 0;
                        
                        foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
                            if ($plugin['source'] === 'cache') {
                                $sourceZip = $repoPath . '/' . $plugin['path'] . basename($plugin['path'], '/') . '.zip';
                                $targetDir = $cacheDir . '/' . $plugin['path'];
                                $targetZip = $targetDir . basename($plugin['path'], '/') . '.zip';
                                
                                if (file_exists($sourceZip) && !file_exists($targetZip)) {
                                    if (!is_dir($targetDir)) {
                                        mkdir($targetDir, 0755, true);
                                    }
                                    copy($sourceZip, $targetZip);
                                    $copied++;
                                }
                            }
                        }
                        
                        foreach ($profile['themes']['premium'] ?? [] as $theme) {
                            if ($theme['source'] === 'cache') {
                                $sourceZip = $repoPath . '/' . $theme['path'] . basename($theme['path'], '/') . '.zip';
                                $targetDir = $cacheDir . '/' . $theme['path'];
                                $targetZip = $targetDir . basename($theme['path'], '/') . '.zip';
                                
                                if (file_exists($sourceZip) && !file_exists($targetZip)) {
                                    if (!is_dir($targetDir)) {
                                        mkdir($targetDir, 0755, true);
                                    }
                                    copy($sourceZip, $targetZip);
                                    $copied++;
                                }
                            }
                        }
                        
                        $output->writeln("<info>✓ {$copied} archivos copiados al cache</info>");
                        $output->writeln('');
                        return;
                    }
                }
                
                $output->writeln('<error>No se encontró el repositorio de premium assets</error>');
                $output->writeln("<comment>Clona el repo: git clone {$premiumRepo}</comment>");
                throw new \Exception("Repositorio premium no encontrado");
            }
        }
    }
    
    private function hasVcsPlugins(\Roots\BedrockCli\DTOs\Profile|array $profile): bool
    {
        if (empty($profile['plugins']['premium'])) {
            return false;
        }
        
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs') {
                return true;
            }
        }
        
        return false;
    }
    
    private function validateVcsPlugins(\Roots\BedrockCli\DTOs\Profile|array $profile, OutputInterface $output): \Roots\BedrockCli\DTOs\Profile|array
    {
        $updated = 0;
        
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs') {
                // Extraer rama del version (dev-branch o branch directa)
                $branch = $plugin['version'];
                if (str_starts_with($branch, 'dev-')) {
                    $branch = substr($branch, 4); // Remover "dev-"
                }
                
                $info = $this->vcsValidator->getPackageInfo($plugin['url'], $branch);
                
                if ($info) {
                    // Extract, modify, reassign pattern for ArrayAccess
                    $require = $profile['require'];
                    $require[$info['name']] = "dev-{$branch}";
                    $profile['require'] = $require;
                    $updated++;
                }
            }
        }
        
        if ($updated > 0) {
            $output->writeln("  ✓ {$updated} VCS plugins validados");
        }
        
        return $profile;
    }


}
