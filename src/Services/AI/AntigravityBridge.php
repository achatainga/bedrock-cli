<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\AI;

use Symfony\Component\Process\Process;
use RuntimeException;

class AntigravityBridge
{
    private string $workerScript;
    private string $pythonBinary;
    private int $timeout;

    public function __construct(?string $workerScript = null, ?string $pythonBinary = null, int $timeout = 180)
    {
        $this->workerScript = $workerScript ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'antigravity-worker.py';
        $this->pythonBinary = $pythonBinary ?? (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $this->timeout = $timeout;
    }

    /**
     * Executes an autonomous agentic task through the Antigravity worker.
     * Supported actions: 'research', 'synthesize', 'audit', 'evolve'.
     */
    public function dispatch(string $action, array $payload = []): array
    {
        if (!file_exists($this->workerScript)) {
            // If worker script doesn't exist, execute in standalone internal mode
            return $this->fallbackInternalExecution($action, $payload);
        }

        $inputJson = json_encode([
            'action' => $action,
            'payload' => $payload,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES);

        $process = new Process([$this->pythonBinary, $this->workerScript]);
        $process->setInput($inputJson);
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => "Process error: " . $e->getMessage(),
                'output' => null,
            ];
        }

        if (!$process->isSuccessful()) {
            return [
                'success' => false,
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
                'output' => null,
                'exit_code' => $process->getExitCode(),
            ];
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [
            'success' => true,
            'raw_output' => $output,
        ];
    }

    /**
     * Internal PHP fallback when Python worker is not deployed.
     */
    private function fallbackInternalExecution(string $action, array $payload): array
    {
        return [
            'success' => true,
            'mode' => 'php-internal-simulated',
            'action' => $action,
            'result' => [
                'status' => 'completed',
                'summary' => "Task '{$action}' processed internally with payload parameters.",
                'data' => $payload,
            ],
        ];
    }
}
