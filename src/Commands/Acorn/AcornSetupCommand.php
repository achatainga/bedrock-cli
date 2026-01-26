<?php

namespace Roots\BedrockCli\Commands\Acorn;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class AcornSetupCommand extends Command
{
    use ProjectSelectorTrait;
    
    protected function configure(): void
    {
        $this->setName('acorn:setup')
             ->setDescription('🔧 Setup completo de Acorn (instalación nueva o reparación)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }

        $output->writeln('<fg=cyan;options=bold>🌱 ACORN SETUP COMPLETO</>')
        $output->writeln('');
        
        // 1. Detectar entorno
        $environment = $this->detectEnvironment();
        $output->writeln("<comment>Entorno detectado: {$environment}</comment>");
        
        // 2. Verificar/instalar paquete
        $this->ensureAcornPackage($output);
        
        // 3. Crear AppServiceProvider global
        $this->createGlobalProvider($output);
        
        // 4. Actualizar composer.json
        $this->updateComposerAutoload($output);
        
        // 5. Instalar/actualizar bootloader
        $this->installAdvancedBootloader($output);
        
        // 6. Configurar tema activo
        $this->setupActiveTheme($output, $environment);
        
        // 7. Inicializar storage y configs
        $this->initializeAcorn($output, $environment);
        
        // 8. Verificar instalación
        $this->verifyInstallation($output, $environment);
        
        $output->writeln('');
        $output->writeln('<info>✅ Acorn configurado completamente</info>');
        
        return Command::SUCCESS;
    }

    private function detectEnvironment(): string 
    {
        if (file_exists('.ddev/config.yaml')) return 'ddev';
        if (file_exists('docker-compose.yml')) return 'docker';
        return 'native';
    }

    private function executeWpCommand(string $command, string $environment): Process 
    {
        switch ($environment) {
            case 'ddev': 
                return Process::fromShellCommandline("ddev wp {$command}");
            case 'docker': 
                return Process::fromShellCommandline("docker-compose exec -T web wp {$command}");
            default: 
                return Process::fromShellCommandline("wp {$command}");
        }
    }

    private function ensureAcornPackage(OutputInterface $output): void
    {
        if (!$this->isPackageInstalled()) {
            $output->writeln('<comment>Instalando roots/acorn...</comment>');
            $process = Process::fromShellCommandline('composer require roots/acorn');
            $process->setTimeout(300);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln('<info>✓ Paquete instalado</info>');
            } else {
                $output->writeln('<error>✗ Error instalando paquete</error>');
            }
        } else {
            $output->writeln('<info>✓ Paquete ya instalado</info>');
        }
    }

    private function createGlobalProvider(OutputInterface $output): void
    {
        $providerDir = 'app/Providers';
        $providerFile = 'app/Providers/AppServiceProvider.php';
        
        if (!is_dir($providerDir)) {
            mkdir($providerDir, 0755, true);
            $output->writeln('<info>✓ Creado directorio app/Providers/</info>');
        }
        
        if (!file_exists($providerFile)) {
            $stubPath = __DIR__ . '/../../../stubs/app/Providers/AppServiceProvider.php.stub';
            if (file_exists($stubPath)) {
                copy($stubPath, $providerFile);
                $output->writeln('<info>✓ Creado AppServiceProvider global</info>');
            } else {
                $output->writeln('<error>✗ Stub AppServiceProvider no encontrado</error>');
            }
        } else {
            $output->writeln('<info>✓ AppServiceProvider ya existe</info>');
        }
    }

    private function updateComposerAutoload(OutputInterface $output): void
    {
        $composerFile = 'composer.json';
        if (!file_exists($composerFile)) {
            $output->writeln('<error>✗ composer.json no encontrado</error>');
            return;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        $modified = false;
        
        // Agregar autoload PSR-4
        if (!isset($composer['autoload']['psr-4']['App\\'])) {
            $composer['autoload']['psr-4']['App\\'] = 'app/';
            $modified = true;
        }
        
        // Agregar post-autoload-dump script
        $scriptExists = false;
        if (isset($composer['scripts']['post-autoload-dump'])) {
            foreach ($composer['scripts']['post-autoload-dump'] as $script) {
                if (strpos($script, 'Roots\\Acorn\\ComposerScripts::postAutoloadDump') !== false) {
                    $scriptExists = true;
                    break;
                }
            }
        }
        
        if (!$scriptExists) {
            $composer['scripts']['post-autoload-dump'][] = 'Roots\\Acorn\\ComposerScripts::postAutoloadDump';
            $modified = true;
        }
        
        if ($modified) {
            file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $output->writeln('<info>✓ composer.json actualizado</info>');
            
            // Regenerar autoload
            $process = Process::fromShellCommandline('composer dump-autoload');
            $process->run();
            if ($process->isSuccessful()) {
                $output->writeln('<info>✓ Autoload regenerado</info>');
            }
        } else {
            $output->writeln('<info>✓ composer.json ya configurado</info>');
        }
    }

    private function installAdvancedBootloader(OutputInterface $output): void
    {
        $bootloaderFile = 'web/app/mu-plugins/acorn-boot.php';
        $stubPath = __DIR__ . '/../../../stubs/mu-plugins/acorn-boot.php.stub';
        
        if (file_exists($stubPath)) {
            copy($stubPath, $bootloaderFile);
            $output->writeln('<info>✓ Bootloader avanzado instalado</info>');
        } else {
            $output->writeln('<error>✗ Stub del bootloader no encontrado</error>');
        }
    }

    private function setupActiveTheme(OutputInterface $output, string $environment): void
    {
        $process = $this->executeWpCommand('theme list --status=active --field=name', $environment);
        $process->run();
        
        if ($process->isSuccessful()) {
            $themeName = trim($process->getOutput());
            if (!empty($themeName)) {
                $this->configureThemeForAcorn($themeName, $output);
            } else {
                $output->writeln('<comment>⚠ No se pudo detectar tema activo</comment>');
            }
        } else {
            $output->writeln('<comment>⚠ No se pudo obtener tema activo (WP no disponible)</comment>');
        }
    }

    private function configureThemeForAcorn(string $themeName, OutputInterface $output): void
    {
        $themePath = "web/app/themes/{$themeName}";
        
        if (!is_dir($themePath)) {
            $output->writeln("<comment>⚠ Tema {$themeName} no encontrado en {$themePath}</comment>");
            return;
        }
        
        // Crear estructura de providers
        $providersDir = "{$themePath}/app/Providers";
        if (!is_dir($providersDir)) {
            mkdir($providersDir, 0755, true);
        }
        
        // Crear ThemeServiceProvider si no existe
        $providerFile = "{$providersDir}/ThemeServiceProvider.php";
        if (!file_exists($providerFile)) {
            $providerContent = $this->getThemeServiceProviderStub();
            file_put_contents($providerFile, $providerContent);
            $output->writeln("<info>✓ ThemeServiceProvider creado para {$themeName}</info>");
        }
        
        // Actualizar config/app.php del tema
        $this->updateThemeConfig($themePath, $output);
        
        // Actualizar composer.json del tema
        $this->updateThemeComposer($themePath, $output);
    }

    private function updateThemeConfig(string $themePath, OutputInterface $output): void
    {
        $configFile = "{$themePath}/config/app.php";
        
        if (file_exists($configFile)) {
            $config = file_get_contents($configFile);
            
            // Verificar si ya tiene providers array
            if (strpos($config, "'providers'") === false) {
                // Agregar providers array antes del cierre
                $providersArray = "\n    'providers' => [\n        App\\Providers\\ThemeServiceProvider::class,\n    ],\n\n];";
                $config = str_replace("\n];", $providersArray, $config);
                
                file_put_contents($configFile, $config);
                $output->writeln('<info>✓ config/app.php del tema actualizado</info>');
            } else {
                $output->writeln('<info>✓ config/app.php ya tiene providers</info>');
            }
        } else {
            $output->writeln('<comment>⚠ config/app.php no encontrado en tema</comment>');
        }
    }

    private function updateThemeComposer(string $themePath, OutputInterface $output): void
    {
        $themeComposerPath = "{$themePath}/composer.json";
        
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
                $output->writeln('<info>✓ composer.json del tema actualizado</info>');
            } else {
                $output->writeln('<info>✓ composer.json del tema ya configurado</info>');
            }
        } else {
            $output->writeln('<comment>⚠ composer.json no encontrado en tema</comment>');
        }
    }

    private function initializeAcorn(OutputInterface $output, string $environment): void
    {
        // Inicializar storage
        $process = $this->executeWpCommand('acorn acorn:init storage', $environment);
        $process->setTimeout(60);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Storage inicializado</info>');
        } else {
            $output->writeln('<comment>⚠ No se pudo inicializar storage (normal si WP no está instalado)</comment>');
        }
        
        // Publicar configs
        $process = $this->executeWpCommand('acorn vendor:publish --tag=acorn', $environment);
        $process->setTimeout(60);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Configs publicados</info>');
        } else {
            $output->writeln('<comment>⚠ No se pudieron publicar configs (normal si WP no está instalado)</comment>');
        }
    }

    private function verifyInstallation(OutputInterface $output, string $environment): void
    {
        $output->writeln('');
        $output->writeln('<comment>Verificando instalación...</comment>');
        
        // Verificar archivos críticos
        $criticalFiles = [
            'app/Providers/AppServiceProvider.php' => 'AppServiceProvider global',
            'web/app/mu-plugins/acorn-boot.php' => 'Bootloader avanzado',
        ];
        
        foreach ($criticalFiles as $file => $description) {
            if (file_exists($file)) {
                $output->writeln("<info>✓ {$description}</info>");
            } else {
                $output->writeln("<error>✗ {$description} faltante</error>");
            }
        }
        
        // Verificar que Acorn funciona (solo si WP está disponible)
        $process = $this->executeWpCommand('acorn --version', $environment);
        $process->run();
        
        if ($process->isSuccessful()) {
            $version = trim($process->getOutput());
            $output->writeln("<info>✓ Acorn funcionando: {$version}</info>");
        } else {
            $output->writeln('<comment>⚠ No se pudo verificar Acorn (WP puede no estar disponible)</comment>');
        }
    }

    private function isPackageInstalled(): bool
    {
        if (!file_exists('composer.json')) {
            return false;
        }
        
        $composer = json_decode(file_get_contents('composer.json'), true);
        return isset($composer['require']['roots/acorn']);
    }

    private function getThemeServiceProviderStub(): string
    {
        return <<<'PHP'
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
    }
}