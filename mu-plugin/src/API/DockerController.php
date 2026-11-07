<?php
// src/API/DockerController.php
namespace BedrockCli\Plugin\API;

use BedrockCli\Plugin\Core\BedrockCliProxy;
use WP_REST_Request;
use WP_REST_Response;

class DockerController
{
    public function registerRoutes(): void
    {
        register_rest_route('bedrock-cli/v1', '/docker/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);

        register_rest_route('bedrock-cli/v1', '/docker/control', [
            'methods' => 'POST',
            'callback' => [$this, 'controlContainer'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    public function getStatus(): WP_REST_Response
    {
        $proxy = BedrockCliProxy::getInstance();
        $result = $proxy->run('docker:status');

        return new WP_REST_Response([
            'success' => $result['success'],
            'output' => $result['output']
        ], 200);
    }

    public function controlContainer(WP_REST_Request $request): WP_REST_Response
    {
        $action = $request->get_param('action');
        $proxy = BedrockCliProxy::getInstance();

        if ($action === 'restart') {
            $result = $proxy->run('docker:restart');
            
            return new WP_REST_Response([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Contenedores reiniciados' : 'Error al reiniciar',
                'output' => $result['output']
            ], 200);
        }

        return new WP_REST_Response(['success' => false, 'error' => 'Acción no permitida o peligrosa'], 403);
    }
}
