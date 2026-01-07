<?php

namespace BedrockCli\Plugin\Admin;

class AdminMenu
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu'], 5);
    }

    public function registerMenu(): void
    {
        // Menú principal
        add_menu_page(
            'Bedrock CLI',
            'Bedrock CLI',
            'manage_options',
            'bedrock-cli',
            [$this, 'renderDashboard'],
            'dashicons-admin-tools',
            2
        );

        // Submenú: Dashboard
        add_submenu_page(
            'bedrock-cli',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'bedrock-cli',
            [$this, 'renderDashboard']
        );

        // Submenú: Plugins
        add_submenu_page(
            'bedrock-cli',
            'Plugins',
            'Plugins',
            'manage_options',
            'bedrock-cli-plugins',
            [$this, 'renderPlugins']
        );

        // Submenú: Logs
        add_submenu_page(
            'bedrock-cli',
            'Logs',
            'Logs',
            'manage_options',
            'bedrock-cli-logs',
            [$this, 'renderLogs']
        );

        // Submenú: REST API
        add_submenu_page(
            'bedrock-cli',
            'REST API',
            'REST API',
            'manage_options',
            'bedrock-cli-api',
            [$this, 'renderApiInfo']
        );
    }

    public function renderDashboard(): void
    {
        require_once __DIR__ . '/Views/dashboard.php';
    }

    public function renderPlugins(): void
    {
        require_once __DIR__ . '/Views/plugins.php';
    }

    public function renderLogs(): void
    {
        // Delegado a LogViewer existente
        $logViewer = new \BedrockCli\Plugin\Admin\LogViewer(
            new \BedrockCli\Plugin\Utils\Logger()
        );
        $logViewer->renderPage();
    }

    public function renderApiInfo(): void
    {
        $token = get_option('bedrock_cli_token');
        $baseUrl = rest_url('bedrock-cli/v1');
        ?>
        <div class="wrap">
            <h1>Bedrock CLI - REST API</h1>

            <div class="card">
                <h2>Información de la API</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Base URL:</strong></td>
                        <td><code><?php echo esc_html($baseUrl); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Token:</strong></td>
                        <td>
                            <?php if ($token): ?>
                                <code><?php echo esc_html($token); ?></code>
                            <?php else: ?>
                                <span style="color: red;">❌ No configurado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>Endpoints Disponibles</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th>Endpoint</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code>/activate-plugins</code></td>
                            <td>Activar plugins en batch</td>
                        </tr>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code>/activate-themes</code></td>
                            <td>Activar tema</td>
                        </tr>
                        <tr>
                            <td><code>GET</code></td>
                            <td><code>/status</code></td>
                            <td>Estado del sistema</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Ejemplo de Uso</h2>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">
curl -X POST <?php echo esc_html($baseUrl); ?>/activate-plugins \
  -H "Content-Type: application/json" \
  -H "X-Bedrock-Token: <?php echo $token ? esc_html($token) : 'YOUR_TOKEN'; ?>" \
  -d '{
    "plugins": ["plugin-slug-1", "plugin-slug-2"]
  }'
                </pre>
            </div>
        </div>
        <?php
    }
}
