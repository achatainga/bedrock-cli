<?php

namespace Roots\BedrockCli\Commands\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class PullCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('db:pull')
             ->setDescription('Descarga e importa la base de datos remota con verificación de integridad y search-replace')
             ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Host o alias SSH remoto', 'dt24-godaddy')
             ->addOption('db', null, InputOption::VALUE_REQUIRED, 'Nombre de la base de datos remota', 'qqgi77wff00i_marketplace')
             ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Usuario MySQL remoto', 'qqgi77wff00i_marketplace')
             ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Contraseña MySQL remota')
             ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Archivo .sql local existente (omite descarga SSH)')
             ->addOption('skip-replace', null, InputOption::VALUE_NONE, 'Omitir búsqueda y reemplazo de URLs')
             ->addOption('target-url', null, InputOption::VALUE_REQUIRED, 'URL destino para search-replace', 'http://localhost:8080')
             ->addOption('source-url', null, InputOption::VALUE_REQUIRED, 'URL origen para search-replace', 'https://detodo24.com')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular sin importar ni modificar datos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $remote = $input->getOption('remote');
        $dbName = $input->getOption('db');
        $dbUser = $input->getOption('user');
        $dbPass = $input->getOption('password');
        $localFile = $input->getOption('file');
        $skipReplace = $input->getOption('skip-replace');
        $targetUrl = $input->getOption('target-url');
        $sourceUrl = $input->getOption('source-url');
        $dryRun = $input->getOption('dry-run');

        $dumpFile = $localFile ?: 'godaddy_dump.sql';

        if (!$localFile) {
            $output->writeln("<info>Iniciando volcado remoto desde SSH: {$remote}...</info>");

            if (!$dbPass) {
                // Verificar si está en .env
                if (file_exists('.env')) {
                    $envContent = file_get_contents('.env');
                    if (preg_match('/^DB_REMOTE_PASSWORD=(.*)$/m', $envContent, $matches)) {
                        $dbPass = trim($matches[1], "\"' ");
                    }
                }
            }

            if (!$dbPass) {
                $helper = $this->getHelper('question');
                $question = new Question('Ingrese la contraseña de MySQL remoto (GoDaddy): ');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $dbPass = $helper->ask($input, $output, $question);
            }

            if ($dryRun) {
                $output->writeln("<comment>[DRY-RUN] Comprobando conexión SSH con {$remote}...</comment>");
                $testProcess = new Process(['ssh', $remote, 'echo OK']);
                $testProcess->run();
                if ($testProcess->isSuccessful() && trim($testProcess->getOutput()) === 'OK') {
                    $output->writeln('<info>✓ Conexión SSH validada exitosamente</info>');
                } else {
                    $output->writeln('<error>✗ Falló la conexión SSH con el host remoto</error>');
                    return Command::FAILURE;
                }
                return Command::SUCCESS;
            }

            $dumpCmd = sprintf(
                'ssh %s "mysqldump -u %s -p\'%s\' %s --default-character-set=utf8mb4 --single-transaction --quick" > %s',
                escapeshellarg($remote),
                escapeshellarg($dbUser),
                addcslashes($dbPass, "'\\"),
                escapeshellarg($dbName),
                escapeshellarg($dumpFile)
            );

            $output->writeln('<comment>Descargando esquema y datos en streaming utf8mb4...</comment>');
            exec($dumpCmd, $cmdOutput, $returnVar);

            if ($returnVar !== 0 || !file_exists($dumpFile) || filesize($dumpFile) < 1000) {
                $output->writeln('<error>✗ Error durante la descarga del volcado remoto.</error>');
                return Command::FAILURE;
            }

            $sizeMb = round(filesize($dumpFile) / (1024 * 1024), 2);
            $output->writeln("<info>✓ Volcado descargado exitosamente ({$sizeMb} MB)</info>");
        }

        // Verificación de integridad usando PHP nativo (agnóstico del SO)
        $output->writeln('<comment>Verificando integridad del archivo SQL...</comment>');
        $isComplete = false;
        if (file_exists($dumpFile) && filesize($dumpFile) > 0) {
            $fp = fopen($dumpFile, 'rb');
            if ($fp) {
                $seekPos = max(0, filesize($dumpFile) - 4096);
                fseek($fp, $seekPos);
                $tailContent = fread($fp, 4096);
                fclose($fp);
                if (str_contains($tailContent, 'Dump completed') || str_contains($tailContent, '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */')) {
                    $isComplete = true;
                }
            }
        }

        if (!$isComplete) {
            $output->writeln('<comment>⚠️ Advertencia: No se encontró la marca final de Dump completed en los últimos 4KB. Verifique el volcado.</comment>');
        } else {
            $output->writeln('<info>✓ Integridad del dump verificada (Fin de volcado detectado)</info>');
        }

        if ($dryRun) {
            $output->writeln('<info>[DRY-RUN] Simulación completa. Omitiendo importación.</info>');
            return Command::SUCCESS;
        }

        // Importación a contenedor MySQL
        $output->writeln('<comment>Importando datos a contenedor Docker MySQL...</comment>');
        $importCmd = sprintf(
            'docker compose exec -T mysql mysql -uroot -pmysql %s < %s',
            escapeshellarg($dbName),
            escapeshellarg($dumpFile)
        );
        exec($importCmd, $importOutput, $importReturn);

        if ($importReturn !== 0) {
            $output->writeln('<error>✗ Error al importar los datos en el contenedor MySQL.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Base de datos importada exitosamente</info>');

        // Search and Replace
        if (!$skipReplace) {
            $output->writeln("<comment>Ejecutando search-replace: {$sourceUrl} -> {$targetUrl}...</comment>");
            $replaceCmd = sprintf(
                'docker compose exec -T web wp search-replace %s %s --all-tables --precise --skip-columns=guid --allow-root',
                escapeshellarg($sourceUrl),
                escapeshellarg($targetUrl)
            );
            exec($replaceCmd, $replaceOutput, $replaceReturn);

            // Actualizar opciones de siteurl y home
            exec(sprintf('docker compose exec -T web wp option update siteurl "%s/wp" --allow-root', $targetUrl));
            exec(sprintf('docker compose exec -T web wp option update home "%s" --allow-root', $targetUrl));

            $output->writeln('<info>✓ Reemplazo de URLs completado y opciones de sitio actualizadas</info>');
        }

        $output->writeln('');
        $output->writeln('<info>🎉 Sincronización de base de datos finalizada con éxito.</info>');

        return Command::SUCCESS;
    }
}
