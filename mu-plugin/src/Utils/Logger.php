<?php

namespace BedrockCli\Plugin\Utils;

class Logger
{
    private string $logDir;
    private string $logFile;
    private int $maxSize = 10485760; // 10MB

    public function __construct()
    {
        $targetDir = null;
        if (function_exists('wp_upload_dir')) {
            $upload = wp_upload_dir();
            if (!empty($upload['basedir'])) {
                $targetDir = $upload['basedir'] . '/logs/bedrock-cli';
            }
        }
        if (!$targetDir) {
            $targetDir = dirname(__DIR__, 2) . '/logs';
        }

        $this->logDir = $targetDir;
        $this->logFile = $this->logDir . '/bedrock-cli.log';

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }

        // Fallback a sys_get_temp_dir() si no se puede escribir
        if (!is_dir($this->logDir) || !is_writable($this->logDir)) {
            $this->logDir = sys_get_temp_dir() . '/bedrock-cli-logs';
            if (!is_dir($this->logDir)) {
                @mkdir($this->logDir, 0750, true);
            }
            $this->logFile = $this->logDir . '/bedrock-cli.log';
        }

        $this->secureLogDirectory($this->logDir);
    }

    private function secureLogDirectory(string $dir): void
    {
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Apache 2.4+\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n# Apache 2.2\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $content);
        }

        $indexPhp = $dir . '/index.php';
        if (!file_exists($indexPhp)) {
            @file_put_contents($indexPhp, "<?php\nhttp_response_code(403);\nexit;\n");
        }

        $indexHtml = $dir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>\n");
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
