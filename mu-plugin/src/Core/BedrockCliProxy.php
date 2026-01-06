<?php

namespace BedrockCli\Plugin\Core;

class BedrockCliProxy
{
    private static $instance = null;
    private string $binPath;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $possiblePaths = [
            getenv('HOME') . '/.config/composer/vendor/bin/bedrock',
            getenv('HOME') . '/.composer/vendor/bin/bedrock',
            '/usr/local/bin/bedrock',
            'bedrock'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) || $this->commandExists($path)) {
                $this->binPath = $path;
                break;
            }
        }
    }

    public function run(string $command, array $args = []): array
    {
        // En Docker, no podemos ejecutar comandos del host
        // Devolvemos datos usando funciones de WordPress directamente
        $inDocker = file_exists('/.dockerenv');
        
        if ($inDocker) {
            return $this->runInDocker($command, $args);
        }
        
        // Ejecución directa en host
        if (empty($this->binPath)) {
            return ['success' => false, 'error' => 'Bedrock CLI binary not found'];
        }

        $cmd = escapeshellcmd($this->binPath . ' ' . $command);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        
        $output = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'command' => $cmd
        ];
    }

    private function runInDocker(string $command, array $args): array
    {
        // Implementar comandos usando WP-CLI o funciones nativas
        switch ($command) {
            case 'info':
                return [
                    'success' => true,
                    'output' => 'Bedrock CLI (Docker mode)'
                ];
            
            case 'docker:status':
                return [
                    'success' => true,
                    'output' => 'Docker is running (inside container)'
                ];
            
            case 'docker:restart':
                return [
                    'success' => false,
                    'output' => 'Cannot restart Docker from inside container'
                ];
            
            case 'language:install':
                $locale = $args[0] ?? 'en_US';
                // Usar WP-CLI directamente
                exec("wp language core install {$locale} --activate 2>&1", $output, $code);
                return [
                    'success' => $code === 0,
                    'output' => implode("\n", $output)
                ];
            
            default:
                return [
                    'success' => false,
                    'output' => "Command '{$command}' not supported in Docker mode"
                ];
        }
    }

    private function commandExists(string $cmd): bool
    {
        $return = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($cmd)));
        return !empty($return);
    }
}
