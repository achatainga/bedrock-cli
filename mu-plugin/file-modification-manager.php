<?php
/**
 * Plugin Name: File Modification Manager
 * Description: Gestiona permisos de modificación de archivos para plugins y temas específicos
 * Version: 1.0.0
 */

// Configuración de plugins/contextos permitidos
class FileModificationManager {
    
    private static $allowed_contexts = [
        'loco_translate' => true,
        'download_language_pack' => true,
        'theme_editor' => true,  // Para edición de temas
        'plugin_editor' => false, // Mantener plugins bloqueados
    ];
    
    private static $allowed_paths = [
        // Permitir edición de child themes (se preservan en updates)
        '/themes/.*-child/',
        // Permitir archivos de traducción en ubicaciones seguras
        '/languages/loco/',
        '/languages/themes/',
        '/languages/plugins/',
    ];
    
    public static function init() {
        error_log("[FileModificationManager] Initializing File Modification Manager");
        add_filter('loco_file_mod_allowed_context', [self::class, 'filter_loco_context'], 10, 2);
        add_filter('file_mod_allowed', [self::class, 'filter_file_mod'], 10, 2);
        add_filter('wp_max_upload_size', [self::class, 'increase_upload_limits']);
        add_action('admin_init', [self::class, 'set_php_limits']);
    }
    
    public static function filter_loco_context($context, $file) {
        error_log("[FileModificationManager] filter_loco_context called: context=$context, file=$file");
        // Permitir modificaciones para contextos de traducción
        if (in_array($context, ['download_language_pack', 'loco_translate'])) {
            error_log("[FileModificationManager] Allowing loco context");
            return 'loco_translate';
        }
        return $context;
    }
    
    public static function filter_file_mod($allowed, $context) {
        error_log("[FileModificationManager] filter_file_mod called: allowed=$allowed, context=$context");
        // Verificar si el contexto está permitido
        if (isset(self::$allowed_contexts[$context])) {
            error_log("[FileModificationManager] Context found, returning: " . (self::$allowed_contexts[$context] ? 'true' : 'false'));
            return self::$allowed_contexts[$context];
        }
        
        // Para edición de temas, verificar que sea child theme
        if ($context === 'theme_editor' && isset($_GET['file'])) {
            $file = $_GET['file'];
            foreach (self::$allowed_paths as $pattern) {
                if (preg_match('#' . $pattern . '#', $file)) {
                    return true;
                }
            }
            return false;
        }
        
        return $allowed;
    }
    
    public static function increase_upload_limits($size) {
        // Solo para admin y requests específicos
        if (is_admin() && (
            (isset($_GET['page']) && $_GET['page'] === 'loco-plugin') ||
            (isset($_POST['action']) && strpos($_POST['action'], 'loco') !== false) ||
            (isset($_GET['page']) && $_GET['page'] === 'theme-editor')
        )) {
            return 50 * 1024 * 1024; // 50MB
        }
        return $size;
    }
    
    public static function set_php_limits() {
        if ((isset($_GET['page']) && in_array($_GET['page'], ['loco-plugin', 'theme-editor']))) {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', 300);
            @ini_set('post_max_size', '50M');
            @ini_set('upload_max_filesize', '50M');
        }
    }
}

// Inicializar el manager
FileModificationManager::init();

// Información sobre preservación de archivos
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'theme-editor') {
        echo '<div class="notice notice-info"><p><strong>Información:</strong> Los child themes se preservan durante las actualizaciones. Los temas padre se sobrescriben.</p></div>';
    }
});