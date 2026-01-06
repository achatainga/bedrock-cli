<?php
// src/API/DockerController.php
namespace BedrockCli\Plugin\API;

use BedrockCli\Plugin\Core\ServiceBridge;
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
        $docker = ServiceBridge::getInstance()->get('DockerService');
        if (!$docker->isRunning()) {
            return new WP_REST_Response(['success' => false, 'error' => 'Docker no está corriendo'], 200);
        }

        // Obtener estado real usando el servicio
        $process = $docker->status();
        $process->run();
        
        return new WP_REST_Response([
            'success' => true, 
            'output' => $process->getOutput()
        ], 200);
    }

    public function controlContainer(WP_REST_Request $request): WP_REST_Response
    {
        $action = $request->get_param('action');
        $docker = ServiceBridge::getInstance()->get('DockerService');

        // SEGURIDAD: Lista blanca estricta
        if ($action === 'restart') {
            $process = $docker->restart();
            $process->run();
            
            return new WP_REST_Response([
                'success' => $process->isSuccessful(),
                'message' => $process->isSuccessful() ? 'Contenedores reiniciados' : 'Error al reiniciar',
                'output' => $process->getOutput() . $process->getErrorOutput()
            ], 200);
        }

        return new WP_REST_Response(['success' => false, 'error' => 'Acción no permitida o peligrosa'], 403);
    }
}
