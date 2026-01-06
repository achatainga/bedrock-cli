<?php

namespace BedrockCli\Plugin\API;

use WP_REST_Request;
use WP_REST_Response;

class PluginController
{
    public function registerRoutes(): void
    {
        register_rest_route('bedrock-cli/v1', '/plugins', [
            'methods' => 'GET',
            'callback' => [$this, 'listPlugins'],
            'permission_callback' => fn() => current_user_can('install_plugins')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchPlugins'],
            'permission_callback' => fn() => current_user_can('install_plugins')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/install', [
            'methods' => 'POST',
            'callback' => [$this, 'installPlugin'],
            'permission_callback' => fn() => current_user_can('install_plugins')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activatePlugin'],
            'permission_callback' => fn() => current_user_can('activate_plugins')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivatePlugin'],
            'permission_callback' => fn() => current_user_can('activate_plugins')
        ]);
    }

    public function listPlugins(): WP_REST_Response
    {
        $composerPath = dirname(ABSPATH, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);
        
        $plugins = [];
        foreach ($composer['require'] ?? [] as $package => $version) {
            if (strpos($package, 'wpackagist-plugin/') === 0) {
                $slug = str_replace('wpackagist-plugin/', '', $package);
                $plugins[] = [
                    'slug' => $slug,
                    'version' => $version,
                    'active' => is_plugin_active($slug . '/' . $slug . '.php')
                ];
            }
        }

        return new WP_REST_Response(['success' => true, 'plugins' => $plugins], 200);
    }

    public function searchPlugins(WP_REST_Request $request): WP_REST_Response
    {
        $query = $request->get_param('q');
        
        $response = wp_remote_get("https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[search]={$query}&request[per_page]=10");
        
        if (is_wp_error($response)) {
            return new WP_REST_Response(['success' => false, 'error' => $response->get_error_message()], 500);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        return new WP_REST_Response(['success' => true, 'plugins' => $data['plugins'] ?? []], 200);
    }

    public function installPlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $version = $request->get_param('version') ?: '*';

        // 1. Agregar a composer.json
        $composerPath = dirname(ABSPATH, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);
        $composer['require']["wpackagist-plugin/{$slug}"] = $version;
        file_put_contents($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Ejecutar composer require
        $output = [];
        $returnCode = 0;
        exec("cd " . dirname(ABSPATH, 2) . " && composer require wpackagist-plugin/{$slug}:{$version} --no-interaction 2>&1", $output, $returnCode);

        return new WP_REST_Response([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? "Plugin {$slug} instalado" : "Error instalando plugin",
            'output' => implode("\n", $output)
        ], 200);
    }

    public function activatePlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        
        $output = [];
        exec("wp plugin activate {$slug} 2>&1", $output, $returnCode);

        return new WP_REST_Response([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? "Plugin activado" : "Error activando plugin",
            'output' => implode("\n", $output)
        ], 200);
    }

    public function deactivatePlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        
        $output = [];
        exec("wp plugin deactivate {$slug} 2>&1", $output, $returnCode);

        return new WP_REST_Response([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? "Plugin desactivado" : "Error desactivando plugin",
            'output' => implode("\n", $output)
        ], 200);
    }
}
