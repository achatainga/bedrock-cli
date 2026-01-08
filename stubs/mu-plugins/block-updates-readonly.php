<?php
/**
 * Plugin Name: Block Updates (Read Only)
 * Description: Permite ver actualizaciones disponibles pero impide ejecutarlas
 */

// Bloquear actualizaciones automáticas
add_filter('automatic_updater_disabled', '__return_true');

// Bloquear actualizaciones manuales pero mantener la vista
add_filter('user_has_cap', function($caps, $cap, $args) {
    // Bloquear capacidades de actualización
    if (in_array($cap[0], ['update_plugins', 'update_themes', 'update_core'])) {
        $caps[$cap[0]] = false;
    }
    return $caps;
}, 10, 3);

// Mostrar mensaje en páginas de actualización
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if (in_array($screen->id, ['update-core', 'plugins', 'themes'])) {
        echo '<div class="notice notice-warning"><p><strong>🔒 Actualizaciones bloqueadas</strong> - Solo lectura para prevenir cambios no autorizados</p></div>';
    }
});

// Remover botones de actualización con CSS
add_action('admin_head', function() {
    echo '<style>
        .update-now, .button-primary[value*="update"], 
        input[name="upgrade"], input[name="update-selected"],
        .update-plugins .button-primary { 
            display: none !important; 
        }
        .update-message { opacity: 0.7; }
    </style>';
});