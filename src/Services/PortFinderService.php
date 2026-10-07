<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services;

class PortFinderService
{
    /**
     * Comprueba si un puerto TCP está disponible en localhost.
     */
    public function isPortAvailable(int $port, string $host = '127.0.0.1'): bool
    {
        if ($port < 1 || $port > 65535) {
            return false;
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, 0.3);
        if (is_resource($connection)) {
            fclose($connection);
            return false; // Puerto ocupado
        }
        return true; // Puerto disponible
    }

    /**
     * Encuentra el siguiente puerto TCP libre a partir de $startPort.
     */
    public function findAvailablePort(int $startPort, int $maxAttempts = 50, string $host = '127.0.0.1'): int
    {
        if ($startPort < 1 || $startPort > 65535) {
            throw new \InvalidArgumentException("Puerto inicial inválido: {$startPort}. Debe estar entre 1 y 65535.");
        }

        $port = $startPort;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            if ($this->isPortAvailable($port, $host)) {
                return $port;
            }
            $port++;
            $attempts++;
        }

        throw new \RuntimeException("No se encontró ningún puerto disponible a partir de {$startPort} tras {$maxAttempts} intentos.");
    }

    /**
     * Asigna automáticamente una terna de puertos libres para HTTP, MySQL y Redis.
     */
    public function findStackPorts(
        int $httpStart = 8080,
        int $mysqlStart = 3306,
        int $redisStart = 6379,
        string $host = '127.0.0.1'
    ): array {
        $http = $this->findAvailablePort($httpStart, 50, $host);
        
        // Evitar colisión si los rangos se cruzan
        $mysqlCandidate = $mysqlStart === $http ? $mysqlStart + 1 : $mysqlStart;
        $mysql = $this->findAvailablePort($mysqlCandidate, 50, $host);

        $redisCandidate = ($redisStart === $http || $redisStart === $mysql) ? max($http, $mysql) + 1 : $redisStart;
        $redis = $this->findAvailablePort($redisCandidate, 50, $host);

        return [
            'http' => $http,
            'mysql' => $mysql,
            'redis' => $redis,
        ];
    }
}
