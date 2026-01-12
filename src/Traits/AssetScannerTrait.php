<?php

namespace Roots\BedrockCli\Traits;

use RuntimeException;

trait AssetScannerTrait
{
    protected function getHeaders(string $file, array $fields): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file, false, null, 0, 8192);
        $headers = [];

        foreach ($fields as $key => $field) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi', $content, $match)) {
                $headers[$key] = trim($match[1]);
            }
        }

        return $headers;
    }

    protected function scanAssets(string $path, string $type): array
    {
        // $type: 'plugin' o 'theme'
        $mainFileName = $type === 'plugin' ? null : 'style.css';
        $fields = $type === 'plugin' ? [
            'Name' => 'Plugin Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author'
        ] : [
            'Name' => 'Theme Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author'
        ];

        // Zip case
        if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            return [
                $slug => [
                    'slug' => $slug,
                    'name' => $slug,
                    'version' => 'N/A',
                    'description' => ucfirst($type) . ' desde archivo ZIP',
                    'path' => $path,
                    'source' => 'zip'
                ]
            ];
        }

        if (!is_dir($path)) {
            throw new RuntimeException("Path '{$path}' no existe o no es un directorio válido");
        }

        $assets = [];
        
        // Single asset case (path itself is the asset)
        if ($this->isAsset($path, $type, $fields, $mainFileName)) {
            $slug = basename($path);
            $assetData = $this->getAssetData($path, $slug, $type, $fields, $mainFileName);
            return [$slug => $assetData];
        }

        // Multiple assets case (path is a directory containing assets)
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $assetPath = $path . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($assetPath)) continue;

            if ($this->isAsset($assetPath, $item, $fields, $mainFileName)) {
                $assets[$item] = $this->getAssetData($assetPath, $item, $type, $fields, $mainFileName);
            }
        }

        return $assets;
    }

    private function isAsset(string $path, string $slug, array $fields, ?string $mainFileName): bool
    {
        $file = $this->findMainFile($path, $slug, $mainFileName);
        if (!$file) return false;

        $headers = $this->getHeaders($file, $fields);
        if (empty($headers['Name'])) return false;

        // Special check for plugins with composer.json
        if ($mainFileName === null) { // is plugin
            $composerFile = $path . '/composer.json';
            if (file_exists($composerFile)) {
                $composerData = json_decode(file_get_contents($composerFile), true);
                if (empty($composerData['name'])) return false;
            }
        }

        return true;
    }

    private function getAssetData(string $path, string $slug, string $type, array $fields, ?string $mainFileName): array
    {
        $file = $this->findMainFile($path, $slug, $mainFileName);
        $headers = $this->getHeaders($file, $fields);

        return [
            'slug' => $slug,
            'name' => $headers['Name'],
            'version' => $headers['Version'] ?? 'N/A',
            'description' => $headers['Description'] ?? '',
            'path' => $path
        ];
    }

    private function findMainFile(string $path, string $slug, ?string $mainFileName): ?string
    {
        if ($mainFileName) {
            $file = $path . DIRECTORY_SEPARATOR . $mainFileName;
            return file_exists($file) ? $file : null;
        }

        // Plugin logic: slug.php or any php with "Plugin Name:"
        $file = $path . DIRECTORY_SEPARATOR . $slug . '.php';
        if (file_exists($file)) return $file;

        $phpFiles = glob($path . DIRECTORY_SEPARATOR . '*.php');
        foreach ($phpFiles as $f) {
            $content = file_get_contents($f, false, null, 0, 8192);
            if (strpos($content, 'Plugin Name:') !== false) return $f;
        }

        return null;
    }
}
