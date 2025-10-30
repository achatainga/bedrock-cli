<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;

class StateDetectorService
{
    public function detectProjectState(): array
    {
        return [
            'is_bedrock' => $this->isBedrockProject(),
            'docker_installed' => $this->isDockerInstalled(),
            'docker_running' => $this->isDockerRunning(),
            'wp_installed' => $this->isWordPressInstalled(),
            'acorn_installed' => $this->isAcornInstalled(),
            'acorn_configured' => $this->isAcornConfigured(),
            'env_exists' => file_exists(getcwd() . '/.env'),
            'containers_running' => $this->areContainersRunning(),
        ];
    }
    
    private function isBedrockProject(): bool
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $content = file_get_contents($composerFile);
        return str_contains($content, 'roots/bedrock') || str_contains($content, 'roots/wordpress');
    }
    
    private function isDockerInstalled(): bool
    {
        $process = Process::fromShellCommandline('docker --version');
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isDockerRunning(): bool
    {
        $process = Process::fromShellCommandline('docker info');
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isWordPressInstalled(): bool
    {
        if (!$this->areContainersRunning()) {
            return false;
        }
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'core', 'is-installed']);
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isAcornInstalled(): bool
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $composerJson = json_decode(file_get_contents($composerFile), true);
        
        return isset($composerJson['require']['roots/acorn']);
    }
    
    private function isAcornConfigured(): bool
    {
        $storageExists = is_dir(getcwd() . '/storage/framework');
        $configExists = is_dir(getcwd() . '/config');
        $bootExists = file_exists(getcwd() . '/web/app/mu-plugins/acorn-boot.php');
        
        return $storageExists && $configExists && $bootExists;
    }
    
    private function areContainersRunning(): bool
    {
        $process = Process::fromShellCommandline('docker-compose ps --services --filter "status=running"');
        $process->run();
        
        if (!$process->isSuccessful()) {
            return false;
        }
        
        $output = trim($process->getOutput());
        return !empty($output);
    }
}
