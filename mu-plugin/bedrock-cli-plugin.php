<?php
/**
 * Plugin Name: Bedrock CLI Plugin
 * Description: MU-Plugin for bedrock-cli operations (REST API, Logs, Admin)
 * Version: 1.0.0
 * Author: Bedrock CLI
 * Author URI: https://github.com/roots/bedrock
 */

// Validar WordPress cargado
if (!defined('ABSPATH') || !function_exists('add_action')) {
    return;
}

// Definir constantes del plugin
define('BEDROCK_CLI_PLUGIN_VERSION', '1.0.0');
define('BEDROCK_CLI_PLUGIN_FILE', __FILE__);
define('BEDROCK_CLI_PLUGIN_DIR', __DIR__);
define('BEDROCK_CLI_PLUGIN_URL', plugins_url('', __FILE__));

// Validar base de datos inicializada
global $wpdb;
if (!isset($wpdb) || !$wpdb) {
    return;
}

// Validar tabla wp_options existe
$table_check = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->options}'");
if ($table_check != $wpdb->options) {
    return;
}

// Cargar autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback: cargar clases manualmente si composer no ejecutado
    spl_autoload_register(function ($class) {
        $prefix = 'BedrockCli\\Plugin\\';
        $base_dir = __DIR__ . '/src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Inicializar plugin
use BedrockCli\Plugin\Core\Plugin;

if (class_exists('BedrockCli\\Plugin\\Core\\Plugin')) {
    new Plugin();
}
