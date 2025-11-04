<?php

namespace Roots\BedrockCli\Services;

class AIContextBuilder
{
    public function buildContext(): array
    {
        $projectPath = getcwd();
        
        return [
            'project' => $this->getProjectInfo($projectPath),
            'state' => $this->getProjectState($projectPath),
            'config' => $this->getConfiguration($projectPath),
            'architecture' => $this->getArchitecture($projectPath)
        ];
    }

    private function getProjectInfo(string $path): array
    {
        return [
            'name' => basename($path),
            'type' => 'bedrock',
            'path' => $path
        ];
    }

    private function getProjectState(string $path): array
    {
        $stateDetector = new StateDetectorService();
        $state = $stateDetector->detectProjectState();
        
        return [
            'docker_running' => $state['docker_running'],
            'wp_installed' => $state['wp_installed'],
            'acorn_configured' => $state['acorn_configured'] ?? false,
            'containers_running' => $state['containers_running']
        ];
    }

    private function getConfiguration(string $path): array
    {
        $config = [];
        
        $envFile = "{$path}/.env";
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            preg_match('/WP_HOME=(.+)/', $envContent, $matches);
            $config['url'] = $matches[1] ?? '';
            preg_match('/DB_NAME=(.+)/', $envContent, $matches);
            $config['db_name'] = $matches[1] ?? '';
        }
        
        return $config;
    }

    private function getArchitecture(string $path): ?array
    {
        $archFile = "{$path}/.io/ARCHITECTURE.md";
        if (!file_exists($archFile)) {
            return null;
        }
        
        $content = file_get_contents($archFile);
        $lines = explode("\n", $content);
        $summary = implode("\n", array_slice($lines, 0, 20));
        
        return ['summary' => $summary];
    }
}
