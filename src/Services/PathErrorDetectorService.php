<?php

namespace Roots\BedrockCli\Services;

class PathErrorDetectorService
{
    public function capturePathErrors(): void
    {
        // Capturar errores de stderr durante ejecución
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    public function handleError($severity, $message, $file, $line): void
    {
        if (strpos($message, 'system cannot find the path') !== false) {
            error_log("PATH_ERROR_DETECTED: {$message} in {$file}:{$line}");
        }
    }
    
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && strpos($error['message'], 'system cannot find the path') !== false) {
            error_log("SHUTDOWN_PATH_ERROR: " . json_encode($error));
        }
    }
}