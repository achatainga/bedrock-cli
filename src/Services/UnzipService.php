<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

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

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $output->writeln("<error>No se pudo abrir el archivo ZIP: {$zipPath}</error>");
            return false;
        }

        if (!is_dir($destination)) {
            $this->filesystem->mkdir($destination);
        }

        $zip->extractTo($destination);
        $zip->close();

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
