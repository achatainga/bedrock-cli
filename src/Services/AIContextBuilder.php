<?php
namespace Roots\BedrockCli\Services;

class AIContextBuilder
{
    public function buildContext(): array
    {
        $projectPath = getcwd();
        $stateDetector = new StateDetectorService();
        $state = $stateDetector->detectProjectState();
        
        return [
            'project' => [
                'name' => basename($projectPath),
                'type' => 'bedrock',
                'path' => $projectPath
            ],
            'state' => [
                'docker_running' => $state['docker_running'],
                'wp_installed' => $state['wp_installed'],
                'acorn_configured' => $state['acorn_configured'] ?? false,
            ],
            'config' => $state['config']['env'] ?? []
        ];
    }
}