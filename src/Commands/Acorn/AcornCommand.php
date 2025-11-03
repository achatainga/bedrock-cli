<?php

namespace Roots\BedrockCli\Commands\Acorn;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
        // Mostrar estado actual
        $this->showStatus($output);
        
        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            '<question>Selecciona una acción:</question>',
            [
                '1' => 'Instalar paquete Composer',
                '2' => 'Inicializar storage',
                '3' => 'Publicar configs',
                '4' => 'Instalar completo (1+2+3)',
                '5' => 'Limpiar cache',
                '6' => 'Ver estado detallado',
                '7' => 'Eliminar configs y storage',
                '8' => 'Desinstalar paquete Composer',
                '9' => 'Desinstalar completo (7+8)',
                '10' => '¿Qué es Acorn? (Ayuda)',
                '11' => 'Ventajas y desventajas',
                '0' => 'Salir'
            ],
            '0'
        );

        $question->setErrorMessage('Opción %s inválida.');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case 'Instalar paquete Composer':
                return $this->installPackage($output);
            
            case 'Inicializar storage':
                return $this->initStorage($output);
            
            case 'Publicar configs':
                return $this->publishConfigs($output);
            
            case 'Instalar completo (1+2+3)':
                return $this->fullInstall($output);
            
            case 'Limpiar cache':
                return $this->cleanStorage($output);
            
            case 'Ver estado detallado':
                return $this->showDetailedStatus($output);
            
            case 'Eliminar configs y storage':
                return $this->removeFiles($input, $output);
            
            case 'Desinstalar paquete Composer':
                return $this->removePackage($input, $output);
            
            case 'Desinstalar completo (7+8)':
                return $this->fullUninstall($input, $output);
            
            case '¿Qué es Acorn? (Ayuda)':
                return $this->showHelp($output);
            
            case 'Ventajas y desventajas':
                return $this->showProsAndCons($output);
            
            case 'Salir':
                return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function showStatus(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Acorn - Gestión Completa         </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $packageInstalled = $this->isPackageInstalled();
        $storageExists = is_dir('storage');
        $configsExist = file_exists('config/app.php');
        
        $output->writeln('<comment>Estado Actual:</comment>');
        $output->writeln(sprintf(' Paquete Composer: %s', $packageInstalled ? '<info>✓ Instalado</info>' : '<error>✗ No instalado</error>'));
        $output->writeln(sprintf(' Storage:          %s', $storageExists ? '<info>✓ Inicializado</info>' : '<error>✗ No inicializado</error>'));
        $output->writeln(sprintf(' Configs:          %s', $configsExist ? '<info>✓ Publicados</info>' : '<error>✗ No publicados</error>'));
        $output->writeln('');
    }

    private function showDetailedStatus(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>═══ Estado Detallado de Acorn ═══</info>');
        $output->writeln('');
        
        // Verificar paquete
        $packageInstalled = $this->isPackageInstalled();
        $version = $this->getAcornVersion();
        
        $output->writeln(sprintf('<comment>Paquete:</comment>  %s', 
            $packageInstalled ? "<info>✓ roots/acorn {$version}</info>" : '<error>✗ No instalado</error>'
        ));
        
        // Verificar storage
        if (is_dir('storage')) {
            $size = $this->getDirectorySize('storage');
            $output->writeln(sprintf('<comment>Storage:</comment>  <info>✓ Inicializado (%s)</info>', $this->formatBytes($size)));
        } else {
            $output->writeln('<comment>Storage:</comment>  <error>✗ No inicializado</error>');
        }
        
        // Verificar configs
        $configFiles = [
            'config/app.php', 'config/assets.php', 'config/view.php',
            'config/auth.php', 'config/database.php', 'config/filesystems.php',
            'config/logging.php', 'config/services.php', 'config/session.php'
        ];
        
        $existingConfigs = array_filter($configFiles, 'file_exists');
        $output->writeln(sprintf('<comment>Configs:</comment>  <info>✓ %d/9 archivos publicados</info>', count($existingConfigs)));
        
        // Verificar cache
        if (is_dir('web/app/cache/acorn')) {
            $cacheSize = $this->getDirectorySize('web/app/cache/acorn');
            $output->writeln(sprintf('<comment>Cache:</comment>    <info>%s</info>', $this->formatBytes($cacheSize)));
            if ($cacheSize > 10 * 1024 * 1024) {
                $output->writeln('            <comment>⚠ Cache grande, considera limpiar</comment>');
            }
        }
        
        // Verificar MU plugin
        if (file_exists('web/app/mu-plugins/acorn-boot.php')) {
            $output->writeln('<comment>MU Plugin:</comment> <info>✓ acorn-boot.php activo</info>');
        }
        
        $output->writeln('');
        return Command::SUCCESS;
    }

    private function showHelp(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>═══ ¿Qué es Roots Acorn? ═══</info>');
        $output->writeln('');
        $output->writeln('Acorn trae componentes de Laravel a WordPress:');
        $output->writeln('');
        $output->writeln('<comment>• Blade Templates</comment>');
        $output->writeln('  Mejor que PHP templates, más limpio y potente');
        $output->writeln('  Ejemplo: @if, @foreach, @include');
        $output->writeln('');
        $output->writeln('<comment>• Service Container</comment>');
        $output->writeln('  Inyección de dependencias automática');
        $output->writeln('  Código más testeable y mantenible');
        $output->writeln('');
        $output->writeln('<comment>• Asset Management</comment>');
        $output->writeln('  Integración con Vite/Mix');
        $output->writeln('  Hot reload, compilación automática');
        $output->writeln('');
        $output->writeln('<comment>• Eloquent ORM</comment>');
        $output->writeln('  Alternativa moderna a WP_Query');
        $output->writeln('  Relaciones, scopes, mutators');
        $output->writeln('');
        $output->writeln('<comment>¿Cuándo usar Acorn?</comment>');
        $output->writeln('  ✓ Proyectos nuevos con temas custom');
        $output->writeln('  ✓ Equipos familiarizados con Laravel');
        $output->writeln('  ✓ Aplicaciones complejas');
        $output->writeln('');
        $output->writeln('<comment>¿Cuándo NO usar Acorn?</comment>');
        $output->writeln('  ✗ Sitios simples con temas pre-hechos');
        $output->writeln('  ✗ Equipo sin experiencia en Laravel');
        $output->writeln('  ✗ Plugins que no son compatibles');
        $output->writeln('');
        
        return Command::SUCCESS;
    }

    private function showProsAndCons(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>═══ Ventajas y Desventajas ═══</info>');
        $output->writeln('');
        $output->writeln('<fg=green>VENTAJAS:</>');
        $output->writeln('  ✓ Código más limpio y organizado');
        $output->writeln('  ✓ Desarrollo más rápido (Blade, helpers)');
        $output->writeln('  ✓ Mejor separación de lógica y vista');
        $output->writeln('  ✓ Testing más fácil');
        $output->writeln('  ✓ Asset pipeline moderno');
        $output->writeln('  ✓ Comunidad Laravel + WordPress');
        $output->writeln('');
        $output->writeln('<fg=red>DESVENTAJAS:</>');
        $output->writeln('  ✗ Curva de aprendizaje (Laravel)');
        $output->writeln('  ✗ Overhead adicional (~5-10 MB)');
        $output->writeln('  ✗ Algunos plugins pueden no funcionar');
        $output->writeln('  ✗ Requiere Composer y build tools');
        $output->writeln('  ✗ Más complejo de deployar');
        $output->writeln('');
        $output->writeln('<comment>Recomendación:</comment>');
        $output->writeln('  Si tu proyecto es simple, NO necesitas Acorn.');
        $output->writeln('  Si construyes aplicaciones complejas, Acorn vale la pena.');
        $output->writeln('');
        
        return Command::SUCCESS;
    }

    private function installPackage(OutputInterface $output): int
    {
        if ($this->isPackageInstalled()) {
            $output->writeln('<comment>⚠ Acorn ya está instalado</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<info>Instalando roots/acorn vía Composer...</info>');
        
        $process = Process::fromShellCommandline('composer require roots/acorn');
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Paquete instalado correctamente</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al instalar paquete</error>');
        return Command::FAILURE;
    }

    private function removePackage(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isPackageInstalled()) {
            $output->writeln('<comment>⚠ Acorn no está instalado</comment>');
            return Command::SUCCESS;
        }
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '<question>¿Eliminar paquete roots/acorn de composer.json? (y/n):</question> ',
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<info>Eliminando roots/acorn...</info>');
        
        $process = Process::fromShellCommandline('composer remove roots/acorn');
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Paquete eliminado correctamente</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al eliminar paquete</error>');
        return Command::FAILURE;
    }

    private function fullInstall(OutputInterface $output): int
    {
        $output->writeln('<info>Instalación completa de Acorn...</info>');
        $output->writeln('');
        
        // 1. Instalar paquete
        if (!$this->isPackageInstalled()) {
            $output->writeln('<comment>Paso 1/3: Instalando paquete...</comment>');
            if ($this->installPackage($output) === Command::FAILURE) {
                return Command::FAILURE;
            }
        } else {
            $output->writeln('<comment>Paso 1/3: Paquete ya instalado ✓</comment>');
        }
        
        // 2. Inicializar storage
        $output->writeln('<comment>Paso 2/3: Inicializando storage...</comment>');
        if ($this->initStorage($output) === Command::FAILURE) {
            return Command::FAILURE;
        }
        
        // 3. Publicar configs
        $output->writeln('<comment>Paso 3/3: Publicando configs...</comment>');
        if ($this->publishConfigs($output) === Command::FAILURE) {
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<info>✅ Acorn instalado completamente</info>');
        
        return Command::SUCCESS;
    }

    private function fullUninstall(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<error>⚠️  DESINSTALACIÓN COMPLETA</error>');
        $output->writeln('Esto eliminará:');
        $output->writeln('  • Paquete roots/acorn de composer.json');
        $output->writeln('  • Todos los archivos de config/');
        $output->writeln('  • Carpeta storage/');
        $output->writeln('  • Cache de acorn/');
        $output->writeln('');
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '<question>¿Estás seguro? (y/n):</question> ',
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }
        
        // 1. Eliminar archivos
        $this->removeFiles($input, $output, true);
        
        // 2. Eliminar paquete
        $this->removePackage($input, $output);
        
        $output->writeln('');
        $output->writeln('<info>✅ Acorn desinstalado completamente</info>');
        
        return Command::SUCCESS;
    }

    private function removeFiles(InputInterface $input, OutputInterface $output, bool $skipConfirm = false): int
    {
        if (!$skipConfirm) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>¿Eliminar configs y storage? (y/n):</question> ',
                false
            );
            
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
        }
        
        $output->writeln('<info>Eliminando archivos...</info>');
        
        // Eliminar configs
        $configFiles = [
            'config/app.php', 'config/assets.php', 'config/view.php',
            'config/auth.php', 'config/database.php', 'config/filesystems.php',
            'config/logging.php', 'config/services.php', 'config/session.php'
        ];
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
                $output->writeln("<comment>✓ Eliminado: {$file}</comment>");
            }
        }
        
        // Eliminar storage
        $process = Process::fromShellCommandline('docker-compose exec -T web sh -c "rm -rf storage"');
        $process->run();
        if ($process->isSuccessful()) {
            $output->writeln('<comment>✓ Eliminado: storage/</comment>');
        }
        
        // Eliminar cache
        $process = Process::fromShellCommandline('docker-compose exec -T web sh -c "rm -rf web/app/cache/acorn"');
        $process->run();
        if ($process->isSuccessful()) {
            $output->writeln('<comment>✓ Eliminado: web/app/cache/acorn/</comment>');
        }
        
        return Command::SUCCESS;
    }

    private function initStorage(OutputInterface $output): int
    {
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
        $process = Process::fromShellCommandline('docker-compose exec -T web wp acorn vendor:publish --tag=acorn');
        $process->setTimeout(60);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Configs publicados</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al publicar configs</error>');
        return Command::FAILURE;
    }

    private function cleanStorage(OutputInterface $output): int
    {
        $output->writeln('<info>Limpiando cache...</info>');
        
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
        
        $output->writeln('<info>✓ Cache limpiado</info>');
        return Command::SUCCESS;
    }

    private function isPackageInstalled(): bool
    {
        if (!file_exists('composer.json')) {
            return false;
        }
        
        $composer = json_decode(file_get_contents('composer.json'), true);
        return isset($composer['require']['roots/acorn']);
    }

    private function getAcornVersion(): string
    {
        if (!file_exists('composer.lock')) {
            return 'unknown';
        }
        
        $lock = json_decode(file_get_contents('composer.lock'), true);
        foreach ($lock['packages'] ?? [] as $package) {
            if ($package['name'] === 'roots/acorn') {
                return $package['version'];
            }
        }
        
        return 'unknown';
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) {
            return 0;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
