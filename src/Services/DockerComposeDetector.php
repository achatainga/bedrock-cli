<?php

namespace BedrockCli\Services;

use RuntimeException;

class DockerComposeDetector
{
    private static ?string $command = null;

    public static function getCommand(): string
    {
        if (self::$command === null) {
            // Priorizar docker compose (V2)
            if (self::commandExists('docker compose version')) {
                self::$command = 'docker compose';
            } elseif (self::commandExists('docker-compose --version')) {
                self::$command = 'docker-compose';
            } else {
                throw new RuntimeException('Docker Compose no encontrado');
            }
        }
        return self::$command;
    }

    private static function commandExists(string $command): bool
    {
        $output = shell_exec("$command 2>/dev/null");
        return $output !== null;
    }
}