<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;

/**
 * Docker Compose Version Detector
 * 
 * Detecta automáticamente si el sistema usa:
 * - docker compose (v2) - Recomendado
 * - docker-compose (v1) - Legacy
 * 
 * Implementa patrón Singleton para evitar múltiples detecciones
 */
class DockerComposeDetector
{
    private static ?string $command = null;
    private static ?string $version = null;

    /**
     * Obtiene el comando Docker Compose disponible
     * 
     * @return string 'docker compose' o 'docker-compose'
     * @throws RuntimeException si no encuentra Docker Compose
     */
    public static function getCommand(): string
    {
        if (self::$command === null) {
            self::detectCommand();
        }
        return self::$command;
    }

    /**
     * Obtiene la versión de Docker Compose
     * 
     * @return string Versión detectada (ej: "2.24.1")
     */
    public static function getVersion(): string
    {
        if (self::$version === null) {
            self::detectCommand();
        }
        return self::$version ?? 'unknown';
    }

    /**
     * Verifica si está usando Docker Compose v2
     * 
     * @return bool true si es v2, false si es v1
     */
    public static function isV2(): bool
    {
        return self::getCommand() === 'docker compose';
    }

    private static function detectCommand(): void
    {
        // Priorizar docker compose (V2) - Recomendado por Docker
        if (self::commandExists('docker compose version')) {
            self::$command = 'docker compose';
            self::$version = self::extractVersion('docker compose version');
        } elseif (self::commandExists('docker-compose --version')) {
            self::$command = 'docker-compose';
            self::$version = self::extractVersion('docker-compose --version');
        } else {
            throw new RuntimeException(
                'Docker Compose no encontrado. Instala Docker Desktop o docker-compose-plugin'
            );
        }
    }

    private static function commandExists(string $command): bool
    {
        $output = shell_exec("$command 2>/dev/null");
        return $output !== null && trim($output) !== '';
    }

    private static function extractVersion(string $command): string
    {
        $output = shell_exec("$command 2>/dev/null");
        if (preg_match('/v?(\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Reset para testing
     * @internal
     */
    public static function reset(): void
    {
        self::$command = null;
        self::$version = null;
    }
}