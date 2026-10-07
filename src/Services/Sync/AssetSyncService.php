<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\Sync;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class AssetSyncService
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Copia recursivamente un directorio omitiendo archivos o carpetas excluidas.
     */
    public function copyDirectory(string $source, string $target, array $excludes = []): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($target)) {
            $this->filesystem->mkdir($target, 0755);
        }

        $items = scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $excludes, true)) {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $dstPath = $target . DIRECTORY_SEPARATOR . $item;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath, $excludes);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        return true;
    }

    /**
     * Sincroniza temas específicos (ej. motta y motta-child) desde un WordPress original.
     */
    public function syncThemes(
        string $sourceWpPath,
        string $targetThemesPath,
        array $themeSlugs,
        OutputInterface $output
    ): int {
        $sourceThemesPath = rtrim($sourceWpPath, '/\\') . '/wp-content/themes';
        if (!is_dir($sourceThemesPath)) {
            $output->writeln("<error>Directorio de temas no encontrado en: {$sourceThemesPath}</error>");
            return 0;
        }

        $synced = 0;
        foreach ($themeSlugs as $slug) {
            if (empty($slug) || basename($slug) !== $slug || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $slug)) {
                continue;
            }

            $src = $sourceThemesPath . '/' . $slug;
            $dst = rtrim($targetThemesPath, '/\\') . '/' . $slug;

            if (is_dir($src)) {
                $output->write("<comment>Sincronizando tema '{$slug}'...</comment> ");
                $this->copyDirectory($src, $dst);
                $output->writeln('<info>✓ OK</info>');
                $synced++;
            } else {
                $output->writeln("<comment>Tema '{$slug}' no existe en el origen (omitido).</comment>");
            }
        }

        return $synced;
    }

    /**
     * Sincroniza plugins desde un WordPress original omitiendo uploads o zips huérfanos.
     */
    public function syncPlugins(
        string $sourceWpPath,
        string $targetPluginsPath,
        OutputInterface $output,
        ?array $activePlugins = null
    ): int {
        $sourcePluginsPath = rtrim($sourceWpPath, '/\\') . '/wp-content/plugins';
        if (!is_dir($sourcePluginsPath)) {
            $output->writeln("<error>Directorio de plugins no encontrado en: {$sourcePluginsPath}</error>");
            return 0;
        }

        $items = scandir($sourcePluginsPath);
        if ($items === false) {
            return 0;
        }

        $synced = 0;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }

            $src = $sourcePluginsPath . '/' . $item;
            if (!is_dir($src)) {
                continue; // Omitir archivos sueltos como index.php o zips
            }

            // Si hay lista de activos y este plugin no está activo, omitir
            if ($activePlugins !== null && !empty($activePlugins)) {
                $isMatch = false;
                foreach ($activePlugins as $active) {
                    if (str_starts_with($active, $item . '/') || $active === $item) {
                        $isMatch = true;
                        break;
                    }
                }
                if (!$isMatch) {
                    continue;
                }
            }

            $dst = rtrim($targetPluginsPath, '/\\') . '/' . $item;
            $output->write("<comment>Sincronizando plugin '{$item}'...</comment> ");
            $this->copyDirectory($src, $dst);
            $output->writeln('<info>✓ OK</info>');
            $synced++;
        }

        return $synced;
    }

    /**
     * Descomenta y configura el proxy transparente de uploads en el archivo Nginx de Bedrock.
     */
    public function enableNginxUploadsProxy(string $nginxConfPath, string $productionUrl): bool
    {
        if (!file_exists($nginxConfPath)) {
            return false;
        }

        $content = file_get_contents($nginxConfPath);
        if ($content === false) {
            return false;
        }

        if (preg_match('/[\r\n\t;]/', $productionUrl)) {
            throw new \InvalidArgumentException("URL contiene caracteres de control o inyección no permitidos: '{$productionUrl}'");
        }

        $parsedUrl = parse_url($productionUrl);
        $host = $parsedUrl['host'] ?? null;
        if (!$host || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
            throw new \InvalidArgumentException("Host inválido para proxy de uploads: '{$productionUrl}'");
        }
        $scheme = in_array($parsedUrl['scheme'] ?? '', ['http', 'https'], true) ? $parsedUrl['scheme'] : 'https';
        $fullOrigin = "{$scheme}://{$host}";

        $proxyBlock = <<<NGINX
    # Zero-Disk Media Proxy (Lazy loaded from production)
    location ~* ^/(app|wp-content)/uploads/(.*)$ {
        try_files \$uri @production_uploads;
    }
    location @production_uploads {
        resolver 8.8.8.8 1.1.1.1 valid=300s ipv6=off;
        proxy_pass {$fullOrigin};
        proxy_set_header Host {$host};
        proxy_ssl_server_name on;
        proxy_ssl_name {$host};
        proxy_ssl_protocols TLSv1.2 TLSv1.3;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-Proto https;
        proxy_cache_valid 200 30d;
        expires 30d;
    }
NGINX;

        // Reemplazar bloque comentado o insertar antes de location ~ \.php$
        if (str_contains($content, 'location ~* ^/(app|wp-content)/uploads/(.*)$')) {
            // Reemplazar todo el bloque comentado de uploads
            $pattern = '/\s*#\s*Proxy missing uploads.*?location @production_uploads\s*\{.*?\}/s';
            $content = preg_replace($pattern, "\n" . $proxyBlock, $content);
        } else {
            // Insertar antes del bloque php
            $content = str_replace('location ~ \.php$', $proxyBlock . "\n\n    location ~ \\.php$", $content);
        }

        return file_put_contents($nginxConfPath, $content) !== false;
    }
}
