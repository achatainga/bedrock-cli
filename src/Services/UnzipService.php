<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class UnzipService
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function detectProjectRoot(): string
    {
        $root = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
        return str_replace('\\', '/', $root);
    }

    public function listZipFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $zipFiles = [];
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $zipFiles[] = $file;
            }
        }

        sort($zipFiles);
        return $zipFiles;
    }

    public function unzip(string $zipPath, string $destination, OutputInterface $output): bool
    {
        if (!file_exists($zipPath)) {
            $output->writeln("<error>Archivo no encontrado: {$zipPath}</error>");
            return false;
        }

        if (!is_dir($destination)) {
            $this->filesystem->mkdir($destination);
        }

        // Intentar con ZipArchive (PHP local)
        if (class_exists('ZipArchive')) {
            try {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath) === true) {
                    $zip->extractTo($destination);
                    $zip->close();
                    return true;
                }
            } catch (\Exception $e) {
                // Fallar silenciosamente y probar con Docker
            }
        }

        // Fallback: detectar si existe Docker
        $projectRoot = $this->detectProjectRoot();
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        
        if (file_exists($dockerCompose)) {
            // Modo Docker: detectar contenedor y volumen
            $content = file_get_contents($dockerCompose);
            $containerName = 'bedrock_web';
            $dockerPath = '/var/www/html';
            
            if (preg_match('/container_name:\s*([^\s#]+)/m', $content, $matches)) {
                $containerName = trim($matches[1]);
            }
            if (preg_match('/- \.\/:([^\s:]+)/m', $content, $matches)) {
                $dockerPath = trim($matches[1]);
            }
            
            $dockerZipPath = str_replace($projectRoot, $dockerPath, $zipPath);
            $dockerDestination = str_replace($projectRoot, $dockerPath, $destination);
            $dockerZipPath = str_replace('\\', '/', $dockerZipPath);
            $dockerDestination = str_replace('\\', '/', $dockerDestination);
            
            $command = sprintf(
                'docker exec %s unzip -q -o "%s" -d "%s" 2>&1',
                $containerName,
                $dockerZipPath,
                $dockerDestination
            );
        } else {
            // Modo sin Docker: usar rutas normales
            $command = sprintf(
                'unzip -q -o "%s" -d "%s" 2>&1',
                $zipPath,
                $destination
            );
        }
        
        exec($command, $output_lines, $return_code);

        if ($return_code !== 0) {
            $output->writeln("<error>Error al descomprimir: " . implode("\n", $output_lines) . "</error>");
            return false;
        }

        return true;
    }

    public function getFileSize(string $filePath): string
    {
        $bytes = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
