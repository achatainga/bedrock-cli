<?php
// src/API/DashboardController.php
namespace BedrockCli\Plugin\API;

use BedrockCli\Plugin\Core\ServiceBridge;
use WP_REST_Request;
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
        try {
            $diagnostic = ServiceBridge::getInstance()->get('ProjectDiagnosticService');
            $validation = ServiceBridge::getInstance()->get('ProjectValidationService');
            
            $path = defined('BEDROCK_APP_PATH') ? BEDROCK_APP_PATH : dirname(ABSPATH, 2);
            
            $data = $diagnostic->generateDiagnosticReport();
            
            // Agregar validaciones en tiempo real
            $data['validations'] = [
                'docker' => $validation->validateDocker($path),
                'database' => $validation->validateDatabase($path),
                'acorn' => $validation->validateAcorn($path)
            ];

            return new WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
