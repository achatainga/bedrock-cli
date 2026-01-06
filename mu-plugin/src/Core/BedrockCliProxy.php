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

    private function commandExists(string $cmd): bool
    {
        $return = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($cmd)));
        return !empty($return);
    }
}
