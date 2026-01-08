<?php
/**
 * Plugin Name: Loco Translate File Mod Allow
 * Description: Permite modificaciones de archivos para Loco Translate manteniendo seguridad
 * Version: 1.0.0
 */

// Permitir modificaciones de archivos específicamente para Loco Translate
add_filter('loco_file_mod_allowed_context', function($context, $file) {
    // Permitir modificaciones para contextos de traducción
    if (in_array($context, ['download_language_pack', 'loco_translate'])) {
        return 'loco_translate';
    }
    return $context;
}, 10, 2);

// Permitir modificaciones de archivos para contextos de Loco Translate
add_filter('file_mod_allowed', function($allowed, $context) {
    // Permitir solo para Loco Translate
    if ($context === 'loco_translate' || $context === 'download_language_pack') {
        return true;
    }
    return $allowed;
}, 10, 2);

// Aumentar límites para archivos de traducción grandes
add_filter('wp_max_upload_size', function($size) {
    // Solo para admin y requests de Loco Translate
    if (is_admin() && (
        (isset($_GET['page']) && $_GET['page'] === 'loco-plugin') ||
        (isset($_POST['action']) && strpos($_POST['action'], 'loco') !== false)
    )) {
        return 50 * 1024 * 1024; // 50MB
    }
    return $size;
});

// Configurar límites PHP para Loco Translate
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'loco-plugin') {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 300);
        @ini_set('post_max_size', '50M');
        @ini_set('upload_max_filesize', '50M');
    }
});