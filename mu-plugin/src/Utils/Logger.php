<?php
namespace BedrockCli\Plugin\Utils;

class Logger
{
    private static $log_file;
    private static $debug_mode;
    private static $max_file_size = 10485760; // 10MB

    public static function init()
    {
        if (self::$log_file) return;

        $log_dir = dirname(dirname(dirname(__FILE__))) . '/logs';
        self::$log_file = $log_dir . '/bedrock-cli-plugin.log';
        self::$debug_mode = get_option('bedrock_cli_debug_mode', false);
    }

    public static function log($message, $type = 'info', $context = [])
    {
        self::init();

        if (!self::$debug_mode && !in_array($type, ['error', 'critical'])) {
            return;
        }

        self::rotate_log_if_needed();

        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        $user_info = $user_id ? " [User: $user_id]" : " [User: Guest]";

        $context_str = !empty($context) ? "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';

        $log_message = sprintf(
            "[%s] %s%s: %s%s\n",
            $timestamp,
            strtoupper($type),
            $user_info,
            $message,
            $context_str
        );

        error_log($log_message, 3, self::$log_file);
    }

    private static function rotate_log_if_needed()
    {
        if (!file_exists(self::$log_file)) return;

        if (filesize(self::$log_file) > self::$max_file_size) {
            $backup = self::$log_file . '.old';
            if (file_exists($backup)) unlink($backup);
            rename(self::$log_file, $backup);
        }
    }

    public static function info($message, $context = []) { self::log($message, 'info', $context); }
    public static function error($message, $context = []) { self::log($message, 'error', $context); }
    public static function warning($message, $context = []) { self::log($message, 'warning', $context); }
    public static function debug($message, $context = []) { self::log($message, 'debug', $context); }
    public static function api($message, $context = []) { self::log($message, 'api', $context); }
    public static function critical($message, $context = []) { self::log($message, 'critical', $context); }

    public static function get_logs($lines = 1000)
    {
        self::init();
        if (!file_exists(self::$log_file)) return '';

        if ($lines <= 0) return file_get_contents(self::$log_file);

        $file = new \SplFileObject(self::$log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;

        $offset = max(0, $total_lines - $lines);
        $file->seek($offset);
        $content = '';

        while ($lines-- > 0 && !$file->eof()) {
            $content .= $file->current();
            $file->next();
        }

        return $content;
    }

    public static function clear_logs()
    {
        self::init();
        return file_exists(self::$log_file) ? file_put_contents(self::$log_file, '') !== false : true;
    }

    public static function get_log_stats()
    {
        self::init();
        if (!file_exists(self::$log_file)) {
            return ['size' => 0, 'lines' => 0, 'last_modified' => null];
        }

        $file = new \SplFileObject(self::$log_file, 'r');
        $file->seek(PHP_INT_MAX);

        return [
            'size' => filesize(self::$log_file),
            'lines' => $file->key() + 1,
            'last_modified' => filemtime(self::$log_file)
        ];
    }

    public static function is_debug_mode() { self::init(); return self::$debug_mode; }

    public static function set_debug_mode($enabled)
    {
        self::init();
        self::$debug_mode = $enabled;
        update_option('bedrock_cli_debug_mode', $enabled);
    }
}
