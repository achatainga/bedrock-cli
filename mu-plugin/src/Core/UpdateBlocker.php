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

        // Bloquear actualizaciones manuales pero mantener la vista
        add_filter('user_has_cap', [$this, 'blockUpdateCapabilities'], 10, 3);

        // Mostrar mensaje en páginas de actualización
        add_action('admin_notices', [$this, 'showUpdateBlockedNotice']);

        // Remover botones de actualización con CSS
        add_action('admin_head', [$this, 'hideUpdateButtons']);
    }

    public function blockUpdateCapabilities($caps, $cap, $args): array
    {
        // Bloquear capacidades de actualización
        if (in_array($cap[0], ['update_plugins', 'update_themes', 'update_core'])) {
            $caps[$cap[0]] = false;
        }
        return $caps;
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