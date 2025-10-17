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

        // 1. Intentar con Python (más confiable)
        if ($this->unzipWithPython($zipPath, $destination, $output)) {
            return true;
        }

        // 2. Intentar con ZipArchive (PHP local)
        if ($this->unzipWithPhp($zipPath, $destination)) {
            return true;
        }

        // 3. Fallback: Docker
        return $this->unzipWithDocker($zipPath, $destination, $output);
    }

    private function unzipWithPython(string $zipPath, string $destination, OutputInterface $output): bool
    {
        $projectRoot = $this->detectProjectRoot();
        $pythonScript = $projectRoot . '/scripts/unzip_file.py';

        if (!file_exists($pythonScript)) {
            return false;
        }

        // Intentar Python local
        exec('python --version 2>&1', $pythonCheck, $pythonCode);
        if ($pythonCode === 0) {
            $command = sprintf('python "%s" "%s" "%s" 2>&1', $pythonScript, $zipPath, $destination);
            exec($command, $output_lines, $return_code);
            
            if ($return_code === 0) {
                return true;
            }
        }

        // Python local no disponible: sugerir instalación
        $os = PHP_OS_FAMILY;
        if ($os === 'Windows') {
            $output->writeln("<comment>Python no encontrado. Instalar con: choco install python</comment>");
        } elseif ($os === 'Linux') {
            $output->writeln("<comment>Python no encontrado. Instalar con: sudo apt install python3</comment>");
        }

        // Fallback: Python en Docker
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        if (file_exists($dockerCompose)) {
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
            $dockerScriptPath = str_replace($projectRoot, $dockerPath, $pythonScript);
            $dockerZipPath = str_replace('\\', '/', $dockerZipPath);
            $dockerDestination = str_replace('\\', '/', $dockerDestination);
            $dockerScriptPath = str_replace('\\', '/', $dockerScriptPath);
            
            $command = sprintf(
                'docker exec %s python "%s" "%s" "%s" 2>&1',
                $containerName,
                $dockerScriptPath,
                $dockerZipPath,
                $dockerDestination
            );
            
            exec($command, $output_lines, $return_code);
            return $return_code === 0;
        }

        return false;
    }

    private function unzipWithPhp(string $zipPath, string $destination): bool
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractAll($destination);
                $zip->close();
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function unzipWithDocker(string $zipPath, string $destination, OutputInterface $output): bool
    {
        $projectRoot = $this->detectProjectRoot();
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        
        if (!file_exists($dockerCompose)) {
            $output->writeln("<error>No se pudo descomprimir: Python, ZipArchive y Docker no disponibles</error>");
            return false;
        }

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
