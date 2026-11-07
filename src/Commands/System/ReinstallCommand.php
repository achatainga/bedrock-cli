<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Roots\BedrockCli\Services\SecurityService;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class ReinstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('reinstall')
            ->setDescription('Reinstalar aplicación completa (DESTRUCTIVO)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=red;options=bold>═══════════════════════════════════════════════════════════</>');
        $output->writeln('<fg=red;options=bold>  REINSTALACIÓN COMPLETA DE LA APLICACIÓN</>');
        $output->writeln('<fg=red;options=bold>═══════════════════════════════════════════════════════════</>');
        $output->writeln('');
        $output->writeln('<fg=yellow>Esta operación realizará:</>');
        $output->writeln('  1. <fg=red>Resetear base de datos</>    - (eliminar todos los datos)');
        $output->writeln('  2. <fg=red>Eliminar WordPress</>        - (rm -rf web/wp)');
        $output->writeln('  3. <fg=red>Eliminar vendor</>           - (rm -rf vendor)');
        $output->writeln('  4. <fg=green>Reinstalar dependencias</> - (composer install)');
        $output->writeln('  5. <fg=green>Rebuild Docker</>          - (sin caché)');
        $output->writeln('  6. <fg=green>Instalar WordPress</>      - (con datos guardados)');
        $output->writeln('');
        
        if (!SecurityService::confirmDangerousAction(
            $input,
            $output,
            $helper,
            'Esta acción ELIMINARÁ TODOS LOS DATOS y reinstalará la aplicación desde cero.'
        )) {
            return Command::SUCCESS;
        }

        // Guardar datos de instalación
        $output->writeln('<fg=cyan>Guardando datos de instalación...</>');
        $wpData = $this->saveWpInstallData($output);
        
        if (!$wpData) {
            $output->writeln('<fg=yellow>No se pudieron obtener datos de WordPress. Continuando sin reinstalación automática.</>');
        }

        // 1. Resetear base de datos
        $output->writeln('');
        $output->writeln('<fg=cyan>Paso 1/6: Reseteando base de datos...</>');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        $process = $wpcli->dbReset();
        $this->runWithLoader($process, $output, 'Reseteando base de datos');
        
        if (!$process->isSuccessful()) {
            $output->writeln('<fg=red>✗ Error al resetear base de datos</>');
            return Command::FAILURE;
        }
        $output->writeln('<fg=green>✓ Base de datos reseteada</>');

        // 2. Eliminar WordPress
        $output->writeln('');
        $output->writeln('<fg=cyan>Paso 2/6: Eliminando WordPress...</>');
        $this->removeDirectory('web/wp', $output);

        // 3. Eliminar vendor
        $output->writeln('');
        $output->writeln('<fg=cyan>Paso 3/6: Eliminando vendor...</>');
        $this->removeDirectory('vendor', $output);

        // 4. Composer install
        $output->writeln('');
        $output->writeln('<fg=cyan>Paso 4/6: Instalando dependencias...</>');
        $this->runComposerInstall($output);

        // 5. Docker rebuild
        $output->writeln('');
        $output->writeln('<fg=cyan>Paso 5/6: Reconstruyendo contenedores Docker...</>');
        $docker->rebuild();
        $output->writeln('<fg=green>✓ Contenedores reconstruidos</>');

        // 6. Instalar WordPress
        if ($wpData) {
            $output->writeln('');
            $output->writeln('<fg=cyan>Paso 6/6: Instalando WordPress...</>');
            $this->reinstallWordPress($wpcli, $wpData, $output);
        } else {
            $output->writeln('');
            $output->writeln('<fg=yellow>Paso 6/6: Omitido (no hay datos de instalación)</>');
        }

        $output->writeln('');
        $output->writeln('<fg=green;options=bold>═══════════════════════════════════════════════════════════</>');
        $output->writeln('<fg=green;options=bold>  ✓ REINSTALACIÓN COMPLETADA</>');
        $output->writeln('<fg=green;options=bold>═══════════════════════════════════════════════════════════</>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function saveWpInstallData(OutputInterface $output): ?array
    {
        $envFile = getcwd() . '/.env';
        if (!file_exists($envFile)) {
            return null;
        }

        $env = parse_ini_file($envFile);
        $url = $env['WP_HOME'] ?? null;
        
        if (!$url) {
            return null;
        }

        // Intentar obtener datos del sitio actual
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        $process = $wpcli->exec(['option', 'get', 'blogname']);
        $process->run();
        $title = $process->isSuccessful() ? trim($process->getOutput()) : 'Mi Sitio';
        
        $process = $wpcli->exec(['option', 'get', 'admin_email']);
        $process->run();
        $email = $process->isSuccessful() ? trim($process->getOutput()) : 'admin@example.com';

        return [
            'url' => $url,
            'title' => $title,
            'email' => $email,
        ];
    }

    private function removeDirectory(string $path, OutputInterface $output): void
    {
        $fullPath = getcwd() . '/' . $path;
        
        if (!file_exists($fullPath)) {
            $output->writeln("<fg=yellow>✓ {$path} no existe, omitiendo...</>");
            return;
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($fullPath);
            $output->writeln("<fg=green>✓ {$path} eliminado</>");
        } catch (\Throwable $e) {
            $output->writeln("<fg=red>✗ Error al eliminar {$path}: {$e->getMessage()}</>");
        }
    }

    private function runComposerInstall(OutputInterface $output): void
    {
        $process = new Process(['composer', 'install', '--no-dev', '--optimize-autoloader'], getcwd(), null, null, 600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Dependencias instaladas</>');
        } else {
            $output->writeln('<fg=red>✗ Error al instalar dependencias</>');
        }
    }

    private function reinstallWordPress(WpCliService $wpcli, array $data, OutputInterface $output): void
    {
        $process = $wpcli->coreInstall([
            'url' => $data['url'],
            'title' => $data['title'],
            'admin_user' => 'admin',
            'admin_password' => 'admin',
            'admin_email' => $data['email']
        ]);
        
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ WordPress instalado</>');
            $output->writeln('');
            $output->writeln('<fg=yellow>Credenciales de acceso:</>');
            $output->writeln('  Usuario: <fg=white;options=bold>admin</>');
            $output->writeln('  Contraseña: <fg=white;options=bold>admin</>');
            $output->writeln('  URL: <fg=cyan>' . $data['url'] . '/wp/wp-admin</>');
        } else {
            $output->writeln('<fg=red>✗ Error al instalar WordPress</>');
        }
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
