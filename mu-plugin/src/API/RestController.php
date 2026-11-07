<?php

namespace BedrockCli\Plugin\API;

class RestController
{
    private const TOKEN_OPTION = 'bedrock_cli_token';
    private const NAMESPACE = 'bedrock-cli/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        $this->ensureToken();
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/plugins/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activatePlugins'],
            'permission_callback' => [$this, 'validateToken'],
            'args' => [
                'plugins' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ]);
    }

    public function health(): array
    {
        return [
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => time(),
        ];
    }

    public function activatePlugins(\WP_REST_Request $request): \WP_REST_Response
    {
        $plugins = $request->get_param('plugins');
        $results = [];

        foreach ($plugins as $plugin) {
            $pluginFile = $this->findPluginFile($plugin);
            
            if (!$pluginFile) {
                $results[$plugin] = [
                    'success' => false,
                    'error' => 'Plugin file not found',
                ];
                continue;
            }

            if (is_plugin_active($pluginFile)) {
                $results[$plugin] = [
                    'success' => true,
                    'already_active' => true,
                ];
                continue;
            }

            $result = activate_plugin($pluginFile, '', false, true);
            
            $results[$plugin] = [
                'success' => is_null($result),
                'error' => is_wp_error($result) ? $result->get_error_message() : null,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'results' => $results,
        ], 200);
    }

    public function validateToken(\WP_REST_Request $request): bool
    {
        $token = $request->get_header('X-Bedrock-Token');
        $storedToken = get_option(self::TOKEN_OPTION);

        return $token && $storedToken && hash_equals($storedToken, $token);
    }

    private function ensureToken(): void
    {
        if (!get_option(self::TOKEN_OPTION)) {
            $token = bin2hex(random_bytes(32));
            update_option(self::TOKEN_OPTION, $token, false);
        }
    }

    private function findPluginFile(string $slug): ?string
    {
        if (str_contains($slug, '..') || str_contains($slug, '/') || str_contains($slug, '\\') || str_contains($slug, "\0")) {
            return null;
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (!file_exists($pluginDir)) {
            return null;
        }

        // Try standard naming first
        $standardFile = $slug . '/' . $slug . '.php';
        if (file_exists(WP_PLUGIN_DIR . '/' . $standardFile)) {
            return $standardFile;
        }

        // Search for any PHP file with Plugin Name header
        $files = glob($pluginDir . '/*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file, false, null, 0, 8192);
            if (preg_match('/Plugin Name:/i', $content)) {
                return $slug . '/' . basename($file);
            }
        }

        return null;
    }
}
