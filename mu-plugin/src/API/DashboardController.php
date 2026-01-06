<?php
// src/API/DashboardController.php
namespace BedrockCli\Plugin\API;

use BedrockCli\Plugin\Core\BedrockCliProxy;
use WP_REST_Response;

class DashboardController
{
    public function registerRoutes(): void
    {
        register_rest_route('bedrock-cli/v1', '/system/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getSystemStatus'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
    }

    public function getSystemStatus(): WP_REST_Response
    {
        $proxy = BedrockCliProxy::getInstance();
        $result = $proxy->run('info');

        $data = [
            'system' => [
                'os' => PHP_OS,
                'php_version' => PHP_VERSION
            ],
            'cli_status' => $result['success'] ? 'Connected' : 'Error',
            'cli_output' => $result['output']
        ];

        return new WP_REST_Response(['success' => true, 'data' => $data], 200);
    }
}
