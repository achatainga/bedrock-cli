<?php

namespace BedrockCli\Plugin\Core;

class UpdateBlocker
{
    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        // Bloquear actualizaciones automáticas
        add_filter('automatic_updater_disabled', '__return_true');

        // Mostrar mensaje en páginas de actualización
        add_action('admin_notices', [$this, 'showUpdateBlockedNotice']);

        // Remover botones de actualización con CSS/JS
        add_action('admin_head', [$this, 'hideUpdateButtons']);
        
        // Bloquear requests de actualización
        add_action('wp_ajax_update-plugin', [$this, 'blockUpdateRequest'], 1);
        add_action('wp_ajax_update-theme', [$this, 'blockUpdateRequest'], 1);
        add_filter('pre_site_transient_update_plugins', [$this, 'preserveUpdateInfo']);
        add_filter('pre_site_transient_update_themes', [$this, 'preserveUpdateInfo']);
    }

    public function blockUpdateRequest(): void
    {
        wp_die('🔒 Actualizaciones bloqueadas por seguridad', 'Actualización Bloqueada', ['response' => 403]);
    }

    public function preserveUpdateInfo($value)
    {
        // Mantener la información de actualizaciones pero bloquear ejecución
        return $value;
    }

    public function showUpdateBlockedNotice(): void
    {
        $screen = get_current_screen();
        if (in_array($screen->id, ['update-core', 'plugins', 'themes'])) {
            echo '<div class="notice notice-warning"><p><strong>🔒 Actualizaciones bloqueadas</strong> - Solo lectura para prevenir cambios no autorizados</p></div>';
        }
    }

    public function hideUpdateButtons(): void
    {
        echo '<style>
            .update-now, .button-primary[value*="update"], 
            input[name="upgrade"], input[name="update-selected"],
            .update-plugins .button-primary { 
                display: none !important; 
            }
            .update-message { opacity: 0.7; }
        </style>';
    }
}