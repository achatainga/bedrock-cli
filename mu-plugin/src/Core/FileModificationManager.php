<?php

namespace BedrockCli\Plugin\Core;

class FileModificationManager 
{
    private array $allowed_contexts = [
        'loco_translate' => true,
        'download_language_pack' => true,
        'theme_editor' => true,
        'plugin_editor' => false,
        'capability_update_core' => true,  // Loco Translate necesita esto
        'can_install_language_pack' => true,  // Para instalar paquetes de idioma
        'woocommerce' => true,  // Para traducciones de WooCommerce
    ];
    
    private array $allowed_paths = [
        // Permitir edición de child themes (se preservan en updates)
        '/themes/.*-child/',
        // Permitir archivos de traducción en ubicaciones seguras
        '/languages/loco/',
        '/languages/themes/',
        '/languages/plugins/',
    ];
    
    public function __construct()
    {
        // Interceptar DISALLOW_FILE_EDIT antes de que WordPress lo use
        add_action('muplugins_loaded', [$this, 'override_file_edit_constant'], 1);
        
        add_filter('loco_file_mod_allowed_context', [$this, 'filter_loco_context'], 10, 2);
        add_filter('file_mod_allowed', [$this, 'filter_file_mod'], 10, 2);
        add_filter('wp_max_upload_size', [$this, 'increase_upload_limits']);
        add_action('admin_init', [$this, 'set_php_limits']);
    }
    
    public function filter_loco_context($context, $file) {
        // Permitir modificaciones para contextos de traducción
        if (in_array($context, ['download_language_pack', 'loco_translate'])) {
            return 'loco_translate';
        }
        return $context;
    }
    
    public function filter_file_mod($allowed, $context) {
        // Verificar si el contexto está permitido
        if (isset($this->allowed_contexts[$context])) {
            return $this->allowed_contexts[$context];
        }
        
        // Para edición de temas, verificar que sea child theme
        if ($context === 'theme_editor' && isset($_GET['file'])) {
            $file = $_GET['file'];
            foreach ($this->allowed_paths as $pattern) {
                if (preg_match('#' . $pattern . '#', $file)) {
                    return true;
                }
            }
            return false;
        }
        
        return $allowed;
    }
    
    public function increase_upload_limits($size) {
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
    
    public function set_php_limits(): void {
        if ((isset($_GET['page']) && in_array($_GET['page'], ['loco-plugin', 'theme-editor']))) {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', 300);
            @ini_set('post_max_size', '50M');
            @ini_set('upload_max_filesize', '50M');
        }
    }
    
    public function override_file_edit_constant(): void {
        if (is_admin() && current_user_can('edit_themes')) {
            // Redefinir la constante para permitir edición
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', false);
            }
        }
    }
}