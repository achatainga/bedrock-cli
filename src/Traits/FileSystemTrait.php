<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Filesystem\Filesystem;

trait FileSystemTrait
{
    /**
     * Elimina un directorio de forma recursiva
     */
    protected function removeDirectory(string $dir): bool
    {
        $fs = new Filesystem();
        if ($fs->exists($dir)) {
            $fs->remove($dir);
            return true;
        }
        return false;
    }

    /**
     * Escanea un directorio buscando subcarpetas (plugins/themes)
     */
    protected function scanDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        return array_values(array_filter(scandir($dir), function($item) use ($dir) {
            return $item !== '.' && $item !== '..' && is_dir($dir . '/' . $item);
        }));
    }

    /**
     * Detecta el root del proyecto Bedrock
     */
    protected function detectProjectRoot(): string
    {
        $currentDir = getcwd();
        while ($currentDir !== '/' && !file_exists($currentDir . '/composer.json')) {
            $currentDir = dirname($currentDir);
        }
        return $currentDir;
    }
}
