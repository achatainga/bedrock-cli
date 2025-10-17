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
        return dirname(dirname(dirname(dirname(dirname(__DIR__)))));
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

        // Fallback: detectar contenedor PHP desde docker-compose.yml
        $projectRoot = $this->detectProjectRoot();
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        
        $containerName = 'bedrock_web'; // default
        if (file_exists($dockerCompose)) {
            $content = file_get_contents($dockerCompose);
            if (preg_match('/container_name:\s*([^\s#]+)/m', $content, $matches)) {
                $containerName = trim($matches[1]);
            }
        }
        
        $zipPath = str_replace('\\', '/', $zipPath);
        $destination = str_replace('\\', '/', $destination);
        
        $command = sprintf(
            'docker exec %s unzip -q -o "%s" -d "%s" 2>&1',
            $containerName,
            $zipPath,
            $destination
        );
        
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
