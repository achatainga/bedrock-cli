<?php
namespace Roots\BedrockCli\Services;

class AIContextBuilder
{
    private StateService $stateService;

    public function __construct(StateService $stateService)
    {
        $this->stateService = $stateService;
    }

    public function buildContext(): array
    {
        $projectPath = getcwd();
        $state = [
            'docker_running' => $this->stateService->validateDockerRunning($projectPath),
            'wp_installed' => $this->stateService->validateWordPressInstalled($projectPath),
            'acorn_configured' => $this->stateService->validateAcornConfigured($projectPath),
            // ... otros datos relevantes
        ];
        
        return [
            'project' => [
                'name' => basename($projectPath),
                'type' => 'bedrock',
                'path' => $projectPath
            ],
            'state' => $state,
            'config' => $this->stateService->getProjectConfiguration($projectPath)['env'] ?? []
        ];
    }
}