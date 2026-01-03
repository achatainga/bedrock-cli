<?php

namespace Roots\BedrockCli\Services;

class ErrorLoggerService
{
    private string $logPath;
    
    public function __construct()
    {
        $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?? getcwd();
        $this->logPath = $homeDir . '/.bedrock-cli/error.log';
        $this->ensureLogDirectory();
    }
    
    public function logPathError(string $context, string $command, string $error): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] PATH_ERROR in {$context}: Command '{$command}' failed with: {$error}" . PHP_EOL;
        
        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
    
    public function logSystemInfo(): void
    {
        $info = [
            'PHP_OS_FAMILY' => PHP_OS_FAMILY,
            'PATH' => $_ENV['PATH'] ?? 'NOT_SET',
            'COMPOSER_HOME' => $_ENV['COMPOSER_HOME'] ?? 'NOT_SET',
            'GIT_EXECUTABLE' => $this->findExecutable('git'),
            'DOCKER_EXECUTABLE' => $this->findExecutable('docker'),
            'WP_CLI_EXECUTABLE' => $this->findExecutable('wp')
        ];
        
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] SYSTEM_INFO: " . json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;
        
        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
    
    private function findExecutable(string $name): string
    {
        $finder = new \Symfony\Component\Process\ExecutableFinder();
        return $finder->find($name) ?: 'NOT_FOUND';
    }
    
    private function ensureLogDirectory(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function getLogPath(): string
    {
        return $this->logPath;
    }
}