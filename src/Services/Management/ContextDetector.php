<?php

namespace Roots\BedrockCli\Services\Management;

class ContextDetector
{
    private ?string $projectRoot = null;
    private ?array $profileData = null;

    public function isBedrockProject(?string $path = null): bool
    {
        $path = $path ?? getcwd();
        
        if (file_exists($path . '/composer.json')) {
            $composer = json_decode(file_get_contents($path . '/composer.json'), true);
            return isset($composer['require']['roots/bedrock']) || 
                   isset($composer['require']['roots/wordpress']);
        }
        
        return false;
    }

    public function getProjectRoot(?string $path = null): ?string
    {
        if ($this->projectRoot !== null) {
            return $this->projectRoot;
        }

        $path = $path ?? getcwd();
        $maxDepth = 5;
        $current = $path;

        for ($i = 0; $i < $maxDepth; $i++) {
            if ($this->isBedrockProject($current)) {
                $this->projectRoot = $current;
                return $current;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return null;
    }

    public function hasActiveProfile(?string $path = null): bool
    {
        $root = $this->getProjectRoot($path);
        if (!$root) {
            return false;
        }

        return file_exists($root . '/.bedrock/profile.json');
    }

    public function getActiveProfile(?string $path = null): ?array
    {
        if ($this->profileData !== null) {
            return $this->profileData;
        }

        $root = $this->getProjectRoot($path);
        if (!$root || !$this->hasActiveProfile($path)) {
            return null;
        }

        $content = file_get_contents($root . '/.bedrock/profile.json');
        $this->profileData = json_decode($content, true);

        return $this->profileData;
    }

    public function getContext(?string $path = null): string
    {
        if (!$this->isBedrockProject($path)) {
            return 'none';
        }

        return $this->hasActiveProfile($path) ? 'project_with_profile' : 'project_without_profile';
    }

    public function getComposerJson(?string $path = null): ?array
    {
        $root = $this->getProjectRoot($path);
        if (!$root) {
            return null;
        }

        $composerPath = $root . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        return json_decode(file_get_contents($composerPath), true);
    }
}
