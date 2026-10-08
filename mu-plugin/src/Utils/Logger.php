<?php

namespace BedrockCli\Plugin\Utils;

class Logger
{
    private string $logDir;
    private string $logFile;
    private int $maxSize = 10485760; // 10MB

    public function __construct()
    {
        $this->logDir = dirname(__DIR__, 2) . '/logs';
        $this->logFile = $this->logDir . '/bedrock-cli.log';
        
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
        
        // Fallback a /tmp si no se puede escribir en logs/
        if (!is_writable($this->logDir)) {
            $this->logDir = sys_get_temp_dir();
            $this->logFile = $this->logDir . '/bedrock-cli.log';
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $this->rotateIfNeeded();
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) >= $this->maxSize) {
            $backup = $this->logFile . '.' . date('YmdHis');
            rename($this->logFile, $backup);
            
            // Mantener solo últimos 5 backups
            $backups = glob($this->logDir . '/bedrock-cli.log.*');
            if (count($backups) > 5) {
                usort($backups, fn($a, $b) => filemtime($a) <=> filemtime($b));
                foreach (array_slice($backups, 0, -5) as $old) {
                    unlink($old);
                }
            }
        }
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    public function getLogs(int $lines = 100): array
    {
        if (!file_exists($this->logFile) || !is_readable($this->logFile)) {
            return [];
        }

        $lines = max(1, min($lines, 1000));
        $fp = @fopen($this->logFile, 'rb');
        if (!$fp) {
            return [];
        }

        try {
            $buffer = '';
            fseek($fp, 0, SEEK_END);
            $pos = ftell($fp);
            $lineCount = 0;
            $chunkSize = 4096;

            while ($pos > 0 && $lineCount <= $lines) {
                $seek = max(0, $pos - $chunkSize);
                $readLength = $pos - $seek;
                fseek($fp, $seek);
                $chunk = fread($fp, $readLength);
                $buffer = $chunk . $buffer;
                $pos = $seek;
                $lineCount = substr_count($buffer, "\n");
            }

            $allLines = array_filter(array_map('trim', explode("\n", $buffer)), fn($l) => $l !== '');
            return array_slice($allLines, -$lines);
        } finally {
            fclose($fp);
        }
    }
}
