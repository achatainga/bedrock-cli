<?php
// src/API/LanguageController.php
namespace BedrockCli\Plugin\API;

use BedrockCli\Plugin\Core\BedrockCliProxy;
use WP_REST_Request;
use WP_REST_Response;

class LanguageController
{
    public function registerRoutes(): void
    {
        register_rest_route('bedrock-cli/v1', '/languages', [
            'methods' => 'GET',
            'callback' => [$this, 'listLanguages'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('bedrock-cli/v1', '/languages/install', [
            'methods' => 'POST',
            'callback' => [$this, 'installLanguage'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    public function listLanguages(): WP_REST_Response
    {
        $current = get_locale();
        
        $common = [
            'en_US' => 'English (US)',
            'es_ES' => 'Español (España)',
            'es_VE' => 'Español (Venezuela)',
            'es_MX' => 'Español (México)',
            'fr_FR' => 'Français',
            'de_DE' => 'Deutsch'
        ];

        return new WP_REST_Response([
            'success' => true,
            'current' => $current,
            'available' => $common
        ], 200);
    }

    public function installLanguage(WP_REST_Request $request): WP_REST_Response
    {
        $locale = $request->get_param('locale');
        $proxy = BedrockCliProxy::getInstance();

        $result = $proxy->run('language:install', [$locale]);

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['success'] ? "Idioma cambiado a {$locale}" : "Error cambiando idioma"
        ], 200);
    }
}
