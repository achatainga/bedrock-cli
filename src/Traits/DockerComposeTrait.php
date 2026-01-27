<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\Services\DockerComposeDetector;
use Symfony\Component\Process\Process;

/**
 * Docker Compose Trait
 * 
 * Proporciona métodos reutilizables para interactuar con Docker Compose
 * Compatible con v1 (docker-compose) y v2 (docker compose)
 */
trait DockerComposeTrait
{
    /**
     * Obtiene el comando Docker Compose correcto para el sistema
     */
    protected function getDockerComposeCommand(): string
    {
        return DockerComposeDetector::getCommand();
    }

    /**
     * Ejecuta comando Docker Compose con passthru (output directo)
     */
    protected function dockerComposeExec(string $args, int &$exitCode = null): void
    {
        $command = $this->getDockerComposeCommand() . ' ' . $args;
        passthru($command, $exitCode);
    }

    /**
     * Ejecuta comando Docker Compose y retorna Process
     * Útil para capturar output o manejar errores
     */
    protected function dockerComposeProcess(string $args, ?string $cwd = null): Process
    {
        $command = explode(' ', $this->getDockerComposeCommand() . ' ' . $args);
        return new Process($command, $cwd);
    }

    /**
     * Verifica si los contenedores están corriendo
     */
    protected function areDockerContainersRunning(?string $projectPath = null): bool
    {
        $cwd = $projectPath ?? getcwd();
        $process = $this->dockerComposeProcess('ps -q', $cwd);
        $process->run();
        
        return $process->isSuccessful() && !empty(trim($process->getOutput()));
    }

    /**
     * Obtiene información de versión de Docker Compose
     */
    protected function getDockerComposeInfo(): array
    {
        return [
            'command' => DockerComposeDetector::getCommand(),
            'version' => DockerComposeDetector::getVersion(),
            'is_v2' => DockerComposeDetector::isV2(),
        ];
    }
}