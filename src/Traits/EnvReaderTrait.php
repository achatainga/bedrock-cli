<?php

namespace Roots\BedrockCli\Traits;

trait EnvReaderTrait
{
    /**
     * Lee un archivo .env y devuelve un array asociativo.
     * Maneja comentarios y comillas básicas.
     */
    protected function readEnvFile(string $projectPath): array
    {
        $envPath = $projectPath . '/.env';
        
        if (!file_exists($envPath)) {
            return [];
        }

        $env = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Quitar comillas si existen
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                $env[$key] = $value;
            }
        }

        return $env;
    }
}
