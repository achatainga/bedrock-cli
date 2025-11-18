<?php

namespace Roots\BedrockCli\Commands\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

class MigrateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrar base de datos desde SQL dump')
            ->addOption('sql-file', null, InputOption::VALUE_REQUIRED, 'Ruta al archivo SQL dump')
            ->addOption('old-prefix', null, InputOption::VALUE_OPTIONAL, 'Prefijo antiguo de tablas', 'wp_')
            ->addOption('new-prefix', null, InputOption::VALUE_OPTIONAL, 'Prefijo nuevo de tablas', 'wp_')
            ->addOption('old-url', null, InputOption::VALUE_REQUIRED, 'URL antigua del sitio')
            ->addOption('new-url', null, InputOption::VALUE_OPTIONAL, 'URL nueva del sitio')
            ->addOption('skip-acorn', null, InputOption::VALUE_NONE, 'Saltar configuración de Acorn')
            ->addOption('default-theme', null, InputOption::VALUE_NONE, 'Activar tema por defecto (twentytwentyfive)')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Confirmar automáticamente sin preguntar')
            ->setHelp('Automatiza la migración de una base de datos existente a un proyecto Bedrock limpio');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sqlFile = $input->getOption('sql-file');
        $oldPrefix = $input->getOption('old-prefix');
        $newPrefix = $input->getOption('new-prefix');
        $oldUrl = $input->getOption('old-url');
        $newUrl = $input->getOption('new-url');
        $skipAcorn = $input->getOption('skip-acorn');
        $defaultTheme = $input->getOption('default-theme');

        // Validaciones
        if (!$sqlFile) {
            $output->writeln('<error>Error: --sql-file es requerido</error>');
            return Command::FAILURE;
        }

        if (!file_exists($sqlFile)) {
            $output->writeln("<error>Error: Archivo SQL no encontrado: {$sqlFile}</error>");
            return Command::FAILURE;
        }

        if (!$oldUrl) {
            $output->writeln('<error>Error: --old-url es requerido</error>');
            return Command::FAILURE;
        }

        // Detectar new-url desde .env si no se proporciona
        if (!$newUrl) {
            if (file_exists('.env')) {
                $envContent = file_get_contents('.env');
                if (preg_match("/WP_HOME='([^']+)'/", $envContent, $matches)) {
                    $newUrl = $matches[1];
                    $output->writeln("<info>URL detectada desde .env: {$newUrl}</info>");
                }
            }
            
            if (!$newUrl) {
                $output->writeln('<error>Error: --new-url es requerido (no se pudo detectar desde .env)</error>');
                return Command::FAILURE;
            }
        }

        // Leer credenciales desde .env
        $dbName = $this->getEnvValue('DB_NAME');
        $dbUser = $this->getEnvValue('DB_USER');
        $dbPass = $this->getEnvValue('DB_PASSWORD');
        $dbHost = $this->getEnvValue('DB_HOST', 'mysql');

        if (!$dbName || !$dbUser) {
            $output->writeln('<error>Error: No se pudieron leer credenciales de BD desde .env</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>🚀 Iniciando migración de base de datos...</info>');
        $output->writeln('');

        // Verificar Docker
        $output->writeln('<comment>1. Verificando Docker...</comment>');
        if (!$this->isDockerRunning($output)) {
            $output->writeln('<error>Error: Docker no está corriendo. Ejecuta: docker-compose up -d</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Docker corriendo</info>');
        $output->writeln('');

        // Confirmar antes de proceder (si no está en modo --yes)
        if (!$input->getOption('yes') && !$input->getOption('no-interaction')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "<question>¿Continuar con la importación? Esto sobrescribirá la BD '{$dbName}' (y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
            $output->writeln('');
        }

        // Paso 2: Importar SQL
        $output->writeln('<comment>2. Importando SQL dump...</comment>');
        $output->writeln("<info>   Archivo: {$sqlFile}</info>");
        
        $importCmd = sprintf(
            'docker-compose exec -T %s mysql -u%s -p%s %s < "%s"',
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            $sqlFile
        );

        $process = Process::fromShellCommandline($importCmd);
        $process->setTimeout(600); // 10 minutos
        $process->run(function ($type, $buffer) use ($output) {
            if (Process::ERR === $type && !str_contains($buffer, 'mysql: [Warning]')) {
                $output->write($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $output->writeln('<error>✗ Error al importar SQL</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ SQL importado correctamente</info>');
        $output->writeln('');

        // Paso 3: Limpiar BD (si hay cambio de prefix)
        if ($oldPrefix !== $newPrefix) {
            $output->writeln('<comment>3. Limpiando base de datos...</comment>');
            $output->writeln("<info>   Renombrando: {$oldPrefix} → {$newPrefix}</info>");
            
            $cleanCmd = sprintf(
                'docker-compose exec -T web php scripts/clean-database.php %s %s',
                escapeshellarg($oldPrefix),
                escapeshellarg($newPrefix)
            );

            $process = Process::fromShellCommandline($cleanCmd);
            $process->setTimeout(300);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>✗ Error al limpiar BD</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>✓ Base de datos limpiada</info>');
            $output->writeln('');
        } else {
            $output->writeln('<comment>3. Limpieza de BD omitida (mismo prefix)</comment>');
            $output->writeln('');
        }

        // Paso 4: Search-replace URLs
        $output->writeln('<comment>4. Reemplazando URLs...</comment>');
        $output->writeln("<info>   {$oldUrl} → {$newUrl}</info>");
        
        $replaceCmd = sprintf(
            'docker-compose exec -T web wp search-replace %s %s --all-tables --quiet',
            escapeshellarg($oldUrl),
            escapeshellarg($newUrl)
        );

        $process = Process::fromShellCommandline($replaceCmd);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>✗ Error al reemplazar URLs</error>');
            return Command::FAILURE;
        }
        
        // Contar reemplazos
        $replaceOutput = $process->getOutput();
        if (preg_match('/(\d+) replacements/', $replaceOutput, $matches)) {
            $output->writeln("<info>✓ {$matches[1]} reemplazos realizados</info>");
        } else {
            $output->writeln('<info>✓ URLs reemplazadas</info>');
        }
        $output->writeln('');

        // Paso 5: Configurar Acorn
        if (!$skipAcorn) {
            $output->writeln('<comment>5. Configurando Acorn...</comment>');
            
            // Inicializar storage
            $process = Process::fromShellCommandline('docker-compose exec -T web wp acorn acorn:init storage');
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln('<info>✓ Storage inicializado</info>');
            }

            // Publicar configs
            $process = Process::fromShellCommandline('docker-compose exec -T web wp acorn vendor:publish --tag=acorn');
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln('<info>✓ Configs publicados</info>');
            }
            $output->writeln('');
        } else {
            $output->writeln('<comment>5. Configuración de Acorn omitida</comment>');
            $output->writeln('');
        }

        // Paso 6: Activar tema por defecto
        if ($defaultTheme) {
            $output->writeln('<comment>6. Activando tema por defecto...</comment>');
            
            $process = Process::fromShellCommandline('docker-compose exec -T web wp theme activate twentytwentyfive');
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln('<info>✓ Tema twentytwentyfive activado</info>');
            } else {
                $output->writeln('<comment>⚠ No se pudo activar tema (puede no estar instalado)</comment>');
            }
            $output->writeln('');
        }

        // Resumen final
        $output->writeln('<info>✅ Migración completada exitosamente</info>');
        $output->writeln('');
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln("  • Accede a: <info>{$newUrl}</info>");
        $output->writeln('  • Verifica plugins: <info>docker-compose exec web wp plugin list</info>');
        $output->writeln('  • Verifica tema: <info>docker-compose exec web wp theme list</info>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function isDockerRunning(OutputInterface $output): bool
    {
        $process = Process::fromShellCommandline('docker-compose ps --services --filter "status=running"');
        $process->run();
        
        return $process->isSuccessful() && !empty(trim($process->getOutput()));
    }

    private function getEnvValue(string $key, string $default = ''): string
    {
        if (!file_exists('.env')) {
            return $default;
        }

        $envContent = file_get_contents('.env');
        
        // Buscar con comillas simples
        if (preg_match("/{$key}='([^']+)'/", $envContent, $matches)) {
            return $matches[1];
        }
        
        // Buscar con comillas dobles
        if (preg_match("/{$key}=\"([^\"]+)\"/", $envContent, $matches)) {
            return $matches[1];
        }
        
        // Buscar sin comillas
        if (preg_match("/{$key}=([^\s]+)/", $envContent, $matches)) {
            return $matches[1];
        }

        return $default;
    }
}
