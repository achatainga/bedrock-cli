<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Snapshot;

use Roots\BedrockCli\Commands\Setup\NewCommand;
use Roots\BedrockCli\Services\Ingress\NginxIngressService;
use Roots\BedrockCli\Services\PortFinderService;
use Roots\BedrockCli\Services\Sync\AssetSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CloneCommand extends Command
{
    private PortFinderService $portFinder;
    private AssetSyncService $assetSync;
    private NginxIngressService $ingressService;

    public function __construct()
    {
        parent::__construct();
        $this->portFinder = new PortFinderService();
        $this->assetSync = new AssetSyncService();
        $this->ingressService = new NginxIngressService();
    }

    protected function configure(): void
    {
        $this
            ->setName('snapshot:clone')
            ->setAliases(['clone'])
            ->setDescription('Clona una instancia de WordPress (local o remota) hacia un entorno Bedrock aislado')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del nuevo proyecto Bedrock')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Ruta local al WordPress original (ej. /home/user/public_html)')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Host SSH remoto (ej. dt24-godaddy) si el origen es remoto')
            ->addOption('remote-path', null, InputOption::VALUE_REQUIRED, 'Ruta en el host remoto', '/home/qqgi77wff00i/public_html')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Dominio destino (ej. staging.detodo24.com o localhost)')
            ->addOption('http-port', null, InputOption::VALUE_REQUIRED, 'Puerto HTTP forzado')
            ->addOption('mysql-port', null, InputOption::VALUE_REQUIRED, 'Puerto MySQL forzado')
            ->addOption('redis-port', null, InputOption::VALUE_REQUIRED, 'Puerto Redis forzado')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Nombre de base de datos destino')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Usuario BD contenedor', 'root')
            ->addOption('db-pass', null, InputOption::VALUE_REQUIRED, 'Contraseña BD contenedor', 'mysql')
            ->addOption('proxy-uploads', null, InputOption::VALUE_REQUIRED, 'URL origen para proxy transparente de uploads (ej. https://detodo24.com)')
            ->addOption('auto-ingress', null, InputOption::VALUE_NONE, 'Configurar automáticamente proxy reverso en Nginx host (cPanel)')
            ->addOption('skip-db', null, InputOption::VALUE_NONE, 'Omitir volcado e importación de base de datos')
            ->addOption('skip-assets', null, InputOption::VALUE_NONE, 'Omitir copia de temas y plugins')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Sobrescribir si el directorio destino ya existe')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Modo simulación sin escribir cambios');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $source = $input->getOption('source');
        $remote = $input->getOption('remote');
        $remotePath = $input->getOption('remote-path');
        $domain = $input->getOption('domain');
        $proxyUploads = $input->getOption('proxy-uploads');
        $autoIngress = $input->getOption('auto-ingress');
        $skipDb = $input->getOption('skip-db');
        $skipAssets = $input->getOption('skip-assets');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $output->writeln('<info>=======================================================</info>');
        $output->writeln('<info>📦 BEDROCK SNAPSHOT / STAGING CLONE ORCHESTRATOR</info>');
        $output->writeln('<info>=======================================================</info>');

        if (!$source && !$remote) {
            $output->writeln('<error>✗ Debes especificar un origen local (--source=/path) o remoto (--remote=host).</error>');
            return Command::FAILURE;
        }

        if (is_dir($name)) {
            if (!$force && !$dryRun) {
                $output->writeln("<error>✗ El directorio '{$name}' ya existe. Usa --force para sobrescribir.</error>");
                return Command::FAILURE;
            }
            if ($force && !$dryRun) {
                $output->writeln("<comment>Desmantelando instalación y contenedores previos de '{$name}' (--force)...</comment>");
                if (file_exists("{$name}/docker-compose.yml")) {
                    $down = new Process(['docker', 'compose', 'down', '-v'], $name);
                    $down->setTimeout(120);
                    $down->run();
                }
                $cleanProc = new Process(['docker', 'rm', '-f', "{$name}_web", "{$name}_nginx", "{$name}_mysql", "{$name}_redis", "{$name}_worker"]);
                $cleanProc->run();
            }
        }

        // FASE 1: Detección y Asignación de Puertos
        $output->writeln('<comment>[FASE 1/6] Asignando puertos TCP disponibles...</comment>');
        $httpPort = $input->getOption('http-port')
            ? (int) $input->getOption('http-port')
            : $this->portFinder->findAvailablePort(8080);

        $mysqlPort = $input->getOption('mysql-port')
            ? (int) $input->getOption('mysql-port')
            : $this->portFinder->findAvailablePort(3306 === $httpPort ? 3307 : 3306);

        $redisPort = $input->getOption('redis-port')
            ? (int) $input->getOption('redis-port')
            : $this->portFinder->findAvailablePort(6379 === $httpPort || 6379 === $mysqlPort ? 6380 : 6379);

        $output->writeln("<info>  → HTTP Port:  {$httpPort}</info>");
        $output->writeln("<info>  → MySQL Port: {$mysqlPort}</info>");
        $output->writeln("<info>  → Redis Port: {$redisPort}</info>");

        $dbName = $input->getOption('db-name') ?: str_replace('-', '_', $name);
        $dbUser = $input->getOption('db-user');
        $dbPass = $input->getOption('db-pass');

        // Formar URL objetivo
        $targetUrl = $domain
            ? (str_starts_with($domain, 'http') ? $domain : ($autoIngress ? "https://{$domain}" : "http://{$domain}:{$httpPort}"))
            : "http://localhost:{$httpPort}";

        if ($dryRun) {
            $output->writeln('<comment>[DRY-RUN] Simulación de clonado completada con éxito.</comment>');
            return Command::SUCCESS;
        }

        // FASE 2: Scaffolding Bedrock con NewCommand
        $output->writeln('<comment>[FASE 2/6] Generando estructura Bedrock aislada...</comment>');
        $newCommand = $this->getApplication()->find('new');
        $newArgs = [
            'command' => 'new',
            'name' => $name,
            '--http-port' => (string) $httpPort,
            '--mysql-port' => (string) $mysqlPort,
            '--redis-port' => (string) $redisPort,
            '--db-name' => $dbName,
            '--db-user' => $dbUser,
            '--db-pass' => $dbPass,
            '--no-acorn' => true,
        ];
        if ($force) {
            $newArgs['--force'] = true;
        }

        $res = $newCommand->run(new ArrayInput($newArgs), $output);
        if ($res !== Command::SUCCESS && !is_dir($name)) {
            $output->writeln('<error>✗ Falló la creación de la estructura Bedrock.</error>');
            return Command::FAILURE;
        }

        // Extraer configuración del origen (credenciales y table_prefix)
        $sourceConfig = $this->extractSourceConfig($source, $remote, $remotePath, $output);
        $tablePrefix = $sourceConfig['table_prefix'] ?? 'wp_';

        // Actualizar variables de entorno de Bedrock (.env)
        $this->updateEnvFile($name, $targetUrl, $tablePrefix);

        // FASE 3: Zero-Disk Media Proxy
        if ($proxyUploads) {
            $output->writeln("<comment>[FASE 3/6] Habilitando Zero-Disk Media Proxy contra {$proxyUploads}...</comment>");
            $nginxConf = "{$name}/docker/nginx/default.conf";
            $this->assetSync->enableNginxUploadsProxy($nginxConf, $proxyUploads);
            $output->writeln('<info>✓ Proxy de imágenes Nginx activo (0 MB de disco consumidos en uploads)</info>');
        } else {
            $output->writeln('<comment>[FASE 3/6] Omitiendo proxy de uploads.</comment>');
        }

        // FASE 4: Sincronización Quirúrgica de Temas y Plugins
        if (!$skipAssets) {
            $output->writeln('<comment>[FASE 4/6] Sincronizando temas y plugins...</comment>');
            $sourceWp = $source ?: ($remote ? null : null);

            if ($source && is_dir($source)) {
                $targetThemes = "{$name}/web/app/themes";
                $targetPlugins = "{$name}/web/app/plugins";
                @mkdir($targetThemes, 0755, true);
                @mkdir($targetPlugins, 0755, true);

                $this->assetSync->syncThemes($source, $targetThemes, ['motta', 'motta-child'], $output);
                $this->assetSync->syncPlugins($source, $targetPlugins, $output);
                $this->assetSync->syncEssentialUploads($source, $name, $output);
                $this->assetSync->syncLanguages($source, $name, $output);
            } elseif ($remote) {
                $output->writeln("<comment>Sincronización remota desde {$remote}:{$remotePath} vía rsync/scp...</comment>");
                // rsync temas y plugins omitiendo uploads
                $this->syncRemoteAssets($remote, $remotePath, $name, $output);
            }
        } else {
            $output->writeln('<comment>[FASE 4/6] Omitiendo sincronización de assets (--skip-assets).</comment>');
        }

        // FASE 5: Levantar Contenedores Docker
        $output->writeln('<comment>[FASE 5/6] Levantando contenedores Docker...</comment>');
        $composeCmd = $this->getComposeCommand();
        $upProcess = new Process(array_merge($composeCmd, ['up', '-d']), $name);
        $upProcess->setTimeout(300);
        $upProcess->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$upProcess->isSuccessful()) {
            $output->writeln('<error>✗ Error al levantar los contenedores Docker.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Contenedores en ejecución</info>');
        $this->fixContainerPermissions($name, $output);

        // FASE 6: Base de Datos y Search-Replace
        if (!$skipDb) {
            $output->writeln('<comment>[FASE 6/6] Migrando base de datos y search-replace...</comment>');
            $this->migrateDatabase($name, $source, $remote, $remotePath, $targetUrl, $dbName, $dbUser, $dbPass, $output);
        } else {
            $output->writeln('<comment>[FASE 6/6] Omitiendo base de datos (--skip-db).</comment>');
        }

        // INGRESS: Configurar proxy reverso Nginx en el host si fue solicitado
        if ($autoIngress && $domain && $this->ingressService->isCpanelNginxHost()) {
            $this->ingressService->configureSubdomainProxy($domain, $httpPort, $output);
        }

        $output->writeln('');
        $output->writeln('<info>=======================================================</info>');
        $output->writeln("<info>🎉 SNAPSHOT CLONE COMPLETADO CON ÉXITO</info>");
        $output->writeln("<info>  → URL del Sitio: {$targetUrl}</info>");
        $output->writeln("<info>  → Directorio:    {$name}</info>");
        $output->writeln("<info>  → HTTP Port:     {$httpPort}</info>");
        $output->writeln("<info>  → MySQL Port:    {$mysqlPort}</info>");
        $output->writeln('<info>=======================================================</info>');

        return Command::SUCCESS;
    }

    public function extractSourceConfig(?string $source, ?string $remote, string $remotePath, OutputInterface $output): array
    {
        $config = [
            'db_name' => '',
            'db_user' => 'root',
            'db_pass' => '',
            'db_host' => 'localhost',
            'table_prefix' => 'wp_',
        ];

        $content = '';

        if ($source && is_dir($source)) {
            $output->writeln('<comment>Extrayendo configuración desde wp-config.php local...</comment>');
            $wpConfig = rtrim($source, '/\\') . '/wp-config.php';
            if (file_exists($wpConfig)) {
                $content = file_get_contents($wpConfig) ?: '';
            }
        } elseif ($remote) {
            $output->writeln("<comment>Extrayendo configuración desde host remoto {$remote}:{$remotePath}/wp-config.php...</comment>");
            $readConfProc = new Process(['ssh', $remote, "cat {$remotePath}/wp-config.php"]);
            $readConfProc->setTimeout(60);
            $readConfProc->run();
            if ($readConfProc->isSuccessful()) {
                $content = $readConfProc->getOutput();
            }
        }

        if ($content !== '') {
            if (preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\);/", $content, $m)) {
                $config['db_name'] = $m[1];
            }
            if (preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\);/", $content, $m)) {
                $config['db_user'] = $m[1];
            }
            if (preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\);/", $content, $m)) {
                $config['db_pass'] = $m[1];
            }
            if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\);/", $content, $m)) {
                $config['db_host'] = $m[1];
            }
            if (preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $m)) {
                $config['table_prefix'] = $m[1];
            }
        }

        $output->writeln("<info>  → Origen DB Name:      {$config['db_name']}</info>");
        $output->writeln("<info>  → Origen Table Prefix: {$config['table_prefix']}</info>");

        return $config;
    }

    public function updateEnvFile(string $projectDir, string $targetUrl, string $tablePrefix): void
    {
        $envPath = "{$projectDir}/.env";
        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return;
        }

        // Configurar URLs exactas de Bedrock
        $content = preg_replace('/^WP_HOME=.*$/m', "WP_HOME='{$targetUrl}'", $content);
        $content = preg_replace('/^WP_SITEURL=.*$/m', "WP_SITEURL='{$targetUrl}/wp'", $content);

        // Si es un dominio real / staging, configurar WP_ENV='staging' para suprimir warnings en API
        if (!str_contains($targetUrl, 'localhost') && !str_contains($targetUrl, '127.0.0.1')) {
            $content = preg_replace('/^WP_ENV=.*$/m', "WP_ENV='staging'", $content);
        }

        // Configurar DB_PREFIX
        if (preg_match('/^DB_PREFIX=.*$/m', $content)) {
            $content = preg_replace('/^DB_PREFIX=.*$/m', "DB_PREFIX='{$tablePrefix}'", $content);
        } else {
            $content .= "\n# Prefix\nDB_PREFIX='{$tablePrefix}'\n";
        }

        file_put_contents($envPath, $content);

        // Asegurar permisos en directorio de contenido y uploads
        $appDir = "{$projectDir}/web/app";
        $uploadsDir = "{$appDir}/uploads";
        $backupsDir = "{$appDir}/ai1wm-backups";

        @chmod($appDir, 0777);
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0777, true);
        }
        @chmod($uploadsDir, 0777);

        if (!is_dir($backupsDir)) {
            @mkdir($backupsDir, 0777, true);
        }
        @chmod($backupsDir, 0777);
    }

    private function syncRemoteAssets(string $remote, string $remotePath, string $projectDir, OutputInterface $output): void
    {
        $targetThemes = "{$projectDir}/web/app/themes";
        $targetPlugins = "{$projectDir}/web/app/plugins";
        $targetUploads = "{$projectDir}/web/app/uploads";
        @mkdir($targetThemes, 0755, true);
        @mkdir($targetPlugins, 0755, true);
        @mkdir($targetUploads, 0777, true);
        @chmod($targetUploads, 0777);

        // Copiar temas específicos de forma explícita (evita fallo de wildcards en OpenSSH 9 SFTP)
        foreach (['motta', 'motta-child'] as $theme) {
            $output->write("<comment>Descargando tema '{$theme}' desde {$remote}...</comment> ");
            $scpTheme = new Process(['scp', '-r', "{$remote}:{$remotePath}/wp-content/themes/{$theme}", $targetThemes]);
            $scpTheme->setTimeout(300);
            $scpTheme->run();
            $output->writeln($scpTheme->isSuccessful() ? '<info>✓ OK</info>' : '<comment>(Tema no encontrado o advertencia)</comment>');
        }

        // Copiar plugins
        $output->write("<comment>Descargando plugins desde {$remote}...</comment> ");
        $rsyncProc = new Process(['rsync', '-avz', '--exclude=*.zip', '--exclude=cache', "{$remote}:{$remotePath}/wp-content/plugins/", $targetPlugins]);
        $rsyncProc->setTimeout(600);
        $rsyncProc->run();
        if (!$rsyncProc->isSuccessful()) {
            // Fallback a tar stream sobre SSH
            $streamProc = new Process([
                'ssh', $remote,
                "tar -czf - -C {$remotePath}/wp-content/plugins --exclude='*.zip' --exclude='cache' ."
            ]);
            $streamProc->setTimeout(600);
            $fp = popen('tar -xzf - -C ' . escapeshellarg($targetPlugins), 'wb');
            if ($fp) {
                $streamProc->run(function ($type, $buf) use ($fp) {
                    if ($type === Process::OUT) {
                        fwrite($fp, $buf);
                    }
                });
                pclose($fp);
            }
        }
        $output->writeln('<info>✓ Plugins sincronizados</info>');

        // Descargar caché CSS de Elementor si existe
        $output->write("<comment>Descargando caché visual de Elementor desde {$remote}...</comment> ");
        $scpElementor = new Process(['scp', '-r', "{$remote}:{$remotePath}/wp-content/uploads/elementor", "{$targetUploads}/elementor"]);
        $scpElementor->setTimeout(180);
        $scpElementor->run();
        $output->writeln($scpElementor->isSuccessful() ? '<info>✓ OK</info>' : '<comment>(Elementor no presente)</comment>');

        // Descargar paquetes de idioma y traducciones si existen
        $targetLanguages = "{$projectDir}/web/app/languages";
        @mkdir($targetLanguages, 0755, true);
        $output->write("<comment>Descargando traducciones e idiomas desde {$remote}...</comment> ");
        $scpLang = new Process(['scp', '-r', "{$remote}:{$remotePath}/wp-content/languages/*", $targetLanguages]);
        $scpLang->setTimeout(180);
        $scpLang->run();
        $output->writeln($scpLang->isSuccessful() ? '<info>✓ OK</info>' : '<comment>(Traducciones no presentes)</comment>');
    }

    private function migrateDatabase(
        string $projectDir,
        ?string $source,
        ?string $remote,
        string $remotePath,
        string $targetUrl,
        string $dbName,
        string $dbUser,
        string $dbPass,
        OutputInterface $output
    ): void {
        $dumpFile = "{$projectDir}/database/snapshots/clone-source.sql";
        @mkdir(dirname($dumpFile), 0755, true);

        $srcConfig = $this->extractSourceConfig($source, $remote, $remotePath, $output);
        $srcDbName = $srcConfig['db_name'];
        $srcDbUser = $srcConfig['db_user'];
        $srcDbPass = $srcConfig['db_pass'];
        $srcDbHost = $srcConfig['db_host'];

        if (!$srcDbName) {
            $output->writeln('<error>✗ No se pudo determinar el nombre de la base de datos de origen en wp-config.php.</error>');
            return;
        }

        if ($source && is_dir($source)) {
            // Caso A: Mismo servidor / Local
            $output->writeln("<comment>Ejecutando mysqldump local sobre '{$srcDbName}'...</comment>");
            $dumpCmd = [
                'mysqldump',
                "-u{$srcDbUser}",
                $srcDbName,
                '--default-character-set=utf8mb4',
                '--single-transaction',
                '--quick',
                '--no-tablespaces'
            ];
            if ($srcDbHost && $srcDbHost !== 'localhost') {
                $dumpCmd[] = "-h{$srcDbHost}";
            }

            $dumpProc = new Process($dumpCmd);
            if ($srcDbPass) {
                $dumpProc->setEnv(['MYSQL_PWD' => $srcDbPass]);
            }
            $dumpProc->setTimeout(600);
            $fp = fopen($dumpFile, 'wb');
            if ($fp) {
                $dumpProc->run(function ($type, $buf) use ($fp) {
                    if ($type === Process::OUT) {
                        fwrite($fp, $buf);
                    }
                });
                fclose($fp);
            }

            if (!$dumpProc->isSuccessful() || !file_exists($dumpFile) || filesize($dumpFile) < 1000) {
                $output->writeln('<error>✗ Error en mysqldump local: ' . trim($dumpProc->getErrorOutput()) . '</error>');
                return;
            }
        } elseif ($remote) {
            // Caso B: Remoto vía SSH Streaming
            $output->writeln("<comment>Descargando volcado SQL desde {$remote} vía streaming mysqldump...</comment>");
            $remoteDumpScript = sprintf(
                'MYSQL_PWD=%s mysqldump -u %s %s --default-character-set=utf8mb4 --single-transaction --quick --no-tablespaces',
                escapeshellarg($srcDbPass),
                escapeshellarg($srcDbUser),
                escapeshellarg($srcDbName)
            );
            $dumpProc = new Process(['ssh', $remote, $remoteDumpScript]);
            $dumpProc->setTimeout(1200);
            $fp = fopen($dumpFile, 'wb');
            if ($fp) {
                $dumpProc->run(function ($type, $buf) use ($fp) {
                    if ($type === Process::OUT) {
                        fwrite($fp, $buf);
                    }
                });
                fclose($fp);
            }

            if (!$dumpProc->isSuccessful() || !file_exists($dumpFile) || filesize($dumpFile) < 1000) {
                $output->writeln('<error>✗ Error al descargar volcado remoto: ' . trim($dumpProc->getErrorOutput()) . '</error>');
                return;
            }
        }

        // Esperar disponibilidad real de MySQL y autenticación en el contenedor
        if (!$this->waitForMysql($projectDir, $dbUser, $dbPass, $dbName, $output)) {
            $output->writeln('<error>✗ No se pudo conectar a MySQL en el contenedor.</error>');
            return;
        }

        // Importar a contenedor si existe dump
        $sizeMb = round(filesize($dumpFile) / (1024 * 1024), 2);
        $output->writeln("<info>Importando volcado SQL ({$sizeMb} MB) al contenedor MySQL...</info>");
        $compose = $this->getComposeCommand();
        $importCmd = array_merge($compose, ['exec', '-T', '-e', "MYSQL_PWD={$dbPass}", 'mysql', 'mysql', "-u{$dbUser}", $dbName]);
        $importProc = new Process($importCmd, $projectDir);
        $importProc->setTimeout(1200);
        $fp = fopen($dumpFile, 'rb');
        if ($fp) {
            $importProc->setInput($fp);
            $importProc->run();
            fclose($fp);
        }

        if ($importProc->isSuccessful()) {
            $output->writeln('<info>✓ Importación a MySQL exitosa</info>');
            // Ejecutar search-replace
            $sourcePath = $source ?: $remotePath;
            $this->runSearchReplace($projectDir, $targetUrl, $sourcePath, $output);
            $this->fixContainerPermissions($projectDir, $output);
        } else {
            $output->writeln('<error>✗ Error importando datos al contenedor MySQL: ' . trim($importProc->getErrorOutput()) . '</error>');
        }
    }

    private function fixContainerPermissions(string $projectDir, OutputInterface $output): void
    {
        $compose = $this->getComposeCommand();
        $output->writeln('<comment>Asegurando permisos en contenedor web (app, uploads, storage, cache, backups)...</comment>');
        $chmodProc = new Process(array_merge($compose, [
            'exec', '-T', 'web',
            'sh', '-c', 'chmod 777 /var/www/html/web/app 2>/dev/null || true; mkdir -p /var/www/html/web/app/ai1wm-backups /var/www/html/web/app/uploads 2>/dev/null || true; chmod -R 777 /var/www/html/web/app/uploads /var/www/html/web/app/ai1wm-backups 2>/dev/null || true; find /var/www/html/web/app -type d \( -name storage -o -name cache \) -exec chmod -R 777 {} + 2>/dev/null || true'
        ]), $projectDir);
        $chmodProc->setTimeout(60);
        $chmodProc->run();
    }

    private function runSearchReplace(string $projectDir, string $targetUrl, ?string $sourcePath, OutputInterface $output): void
    {
        $compose = $this->getComposeCommand();
        $output->writeln('<comment>Detectando URL original para search-replace...</comment>');

        // Obtener siteurl actual del contenedor
        $getUrlProc = new Process(array_merge($compose, ['exec', '-T', 'web', 'wp', 'option', 'get', 'siteurl', '--allow-root']), $projectDir);
        $getUrlProc->run();
        $sourceUrl = trim($getUrlProc->getOutput());
        $cleanSource = preg_replace('#/wp$#', '', $sourceUrl) ?: 'https://detodo24.com';

        $output->writeln("<info>Reemplazando URL: {$cleanSource} -> {$targetUrl}...</info>");
        $srProc = new Process(array_merge($compose, [
            'exec', '-T', 'web',
            'wp', 'search-replace', $cleanSource, $targetUrl,
            '--all-tables', '--skip-columns=guid', '--allow-root'
        ]), $projectDir);
        $srProc->setTimeout(600);
        $srProc->run(function ($type, $buf) use ($output) {
            $output->write($buf);
        });

        // Reemplazar rutas de disco absolutas si se conoce el path original
        if ($sourcePath && !empty($sourcePath)) {
            $cleanSourcePath = rtrim($sourcePath, '/\\');
            $targetAppPath = '/var/www/html/web/app';
            $output->writeln("<info>Reemplazando ruta de assets en DB: {$cleanSourcePath}/wp-content -> {$targetAppPath}...</info>");
            $srPathProc = new Process(array_merge($compose, [
                'exec', '-T', 'web',
                'wp', 'search-replace', "{$cleanSourcePath}/wp-content", $targetAppPath,
                '--all-tables', '--skip-columns=guid', '--allow-root'
            ]), $projectDir);
            $srPathProc->setTimeout(600);
            $srPathProc->run();
        }

        // Asegurar siteurl y home
        $siteurlProc = new Process(array_merge($compose, [
            'exec', '-T', 'web',
            'wp', 'option', 'update', 'siteurl', rtrim($targetUrl, '/') . '/wp', '--allow-root'
        ]), $projectDir);
        $siteurlProc->run();

        $homeProc = new Process(array_merge($compose, [
            'exec', '-T', 'web',
            'wp', 'option', 'update', 'home', $targetUrl, '--allow-root'
        ]), $projectDir);
        $homeProc->run();

        // Limpieza de caché y reescritura de reglas
        $output->writeln('<comment>Vaciando caché y regenerando permalinks...</comment>');
        $flushCache = new Process(array_merge($compose, ['exec', '-T', 'web', 'wp', 'cache', 'flush', '--allow-root']), $projectDir);
        $flushCache->run();

        $flushRewrite = new Process(array_merge($compose, ['exec', '-T', 'web', 'wp', 'rewrite', 'flush', '--allow-root']), $projectDir);
        $flushRewrite->run();

        // Regenerar caché de Elementor si el plugin está presente
        $flushElementor = new Process(array_merge($compose, ['exec', '-T', 'web', 'wp', 'elementor', 'flush-css', '--allow-root']), $projectDir);
        $flushElementor->run();

        $output->writeln('<info>✓ URLs y caché actualizadas en la base de datos</info>');
    }

    private function waitForMysql(string $projectDir, string $dbUser, string $dbPass, string $dbName, OutputInterface $output): bool
    {
        $compose = $this->getComposeCommand();
        $output->write('<comment>Esperando disponibilidad real de MySQL en el contenedor...</comment> ');
        $maxSeconds = 90;
        $start = time();
        while ((time() - $start) < $maxSeconds) {
            $proc = new Process(array_merge($compose, [
                'exec', '-T', '-e', "MYSQL_PWD={$dbPass}", 'mysql',
                'mysql', "-u{$dbUser}", '-e', 'SELECT 1;', $dbName
            ]), $projectDir);
            $proc->run();
            if ($proc->isSuccessful()) {
                $output->writeln('<info>✓ Listo (autenticación y BD operativas)</info>');
                return true;
            }
            sleep(2);
        }
        $output->writeln('<error>✗ Timeout esperando MySQL</error>');
        return false;
    }

    private function getComposeCommand(): array
    {
        $proc = new Process(['docker', 'compose', 'version']);
        $proc->run();
        return $proc->isSuccessful() ? ['docker', 'compose'] : ['docker-compose'];
    }
}
