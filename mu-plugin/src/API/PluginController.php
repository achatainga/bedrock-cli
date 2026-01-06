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
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchPlugins'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('bedrock-cli/v1', '/plugins/install', [
            'methods' => 'POST',
            'callback' => [$this, 'installPlugin'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => ['slug' => ['required' => true]]
        ]);

        register_rest_route('bedrock-cli/v1', '/plugin/uninstall', [
            'methods' => 'POST',
            'callback' => [$this, 'uninstallPlugin'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => ['slug' => ['required' => true]]
        ]);

        register_rest_route('bedrock-cli/v1', '/plugin/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activatePlugin'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => ['slug' => ['required' => true]]
        ]);

        register_rest_route('bedrock-cli/v1', '/plugin/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivatePlugin'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => ['slug' => ['required' => true]]
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
                
                // Patrones comunes para archivos principales
                $patterns = [
                    $slug . '/' . $slug . '.php',
                    $slug . '/index.php', 
                    $slug . '/wp-' . $slug . '.php',
                    $slug . '/' . str_replace('-translate', '', $slug) . '.php', // loco-translate -> loco.php
                    $slug . '/' . explode('-', $slug)[0] . '.php' // primer-palabra.php
                ];
                
                $isActive = false;
                foreach ($patterns as $pattern) {
                    if (is_plugin_active($pattern)) {
                        $isActive = true;
                        break;
                    }
                }
                
                $plugins[] = [
                    'slug' => $slug,
                    'version' => $version,
                    'active' => $isActive
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

        $projectRoot = dirname(ABSPATH, 2);
        $composerJson = $projectRoot . '/composer.json';
        $output = [];
        $returnCode = 0;
        
        // 1. Configurar git safe.directory
        exec("git config --global --add safe.directory {$projectRoot} 2>&1");
        
        // 2. Modificar composer.json manualmente
        $composerJson = $projectRoot . '/composer.json';
        $composerLock = $projectRoot . '/composer.lock';
        
        $originalJsonPerms = fileperms($composerJson);
        $originalLockPerms = fileperms($composerLock);
        
        chmod($composerJson, 0666);
        chmod($composerLock, 0666);
        
        $composer = json_decode(file_get_contents($composerJson), true);
        $composer['require']["wpackagist-plugin/{$slug}"] = $version;
        file_put_contents($composerJson, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        chmod($composerJson, $originalJsonPerms);
        chmod($composerLock, $originalLockPerms);
        
        // 3. Ejecutar composer update (solo lee composer.json, no lo modifica)
        $pluginsDir = dirname(ABSPATH) . '/plugins';
        
        // Asegurar que el directorio plugins existe y tiene permisos
        if (!file_exists($pluginsDir)) {
            mkdir($pluginsDir, 0755, true);
        }
        $originalPluginsPerms = fileperms($pluginsDir);
        chmod($pluginsDir, 0777);
        
        $composerBin = file_exists('/usr/local/bin/composer') ? '/usr/local/bin/composer' : 'composer';
        exec("cd {$projectRoot} && {$composerBin} update wpackagist-plugin/{$slug} --no-interaction 2>&1", $output, $returnCode);
        
        // Restaurar permisos
        chmod($pluginsDir, $originalPluginsPerms);

        // 4. Activar con wp-cli
        if ($returnCode === 0) {
            exec("wp plugin activate {$slug} 2>&1", $activateOutput);
        }

        return new WP_REST_Response([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? "Plugin {$slug} instalado y activado" : "Error instalando plugin",
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

    public function uninstallPlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');

        $projectRoot = dirname(ABSPATH, 2);
        $composerJson = $projectRoot . '/composer.json';
        $output = [];
        $returnCode = 0;
        
        // Desactivar primero
        exec("wp plugin deactivate {$slug} 2>&1");
        
        // Configurar git safe.directory
        exec("git config --global --add safe.directory {$projectRoot} 2>&1");
        
        // Modificar composer.json manualmente
        $composerJson = $projectRoot . '/composer.json';
        $composerLock = $projectRoot . '/composer.lock';
        
        $originalJsonPerms = fileperms($composerJson);
        $originalLockPerms = fileperms($composerLock);
        
        chmod($composerJson, 0666);
        chmod($composerLock, 0666);
        
        $composer = json_decode(file_get_contents($composerJson), true);
        unset($composer['require']["wpackagist-plugin/{$slug}"]);
        file_put_contents($composerJson, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        chmod($composerJson, $originalJsonPerms);
        chmod($composerLock, $originalLockPerms);
        
        // Ejecutar composer update para remover
        $composerBin = file_exists('/usr/local/bin/composer') ? '/usr/local/bin/composer' : 'composer';
        exec("cd {$projectRoot} && {$composerBin} remove wpackagist-plugin/{$slug} --no-interaction 2>&1", $output, $returnCode);

        return new WP_REST_Response([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? "Plugin desinstalado" : "Error desinstalando plugin",
            'output' => implode("\n", $output)
        ], 200);
    }
}
