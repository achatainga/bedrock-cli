<?php

namespace Roots\BedrockCli\Services;

class BlueprintService
{
    private string $stubsPath;

    public function __construct()
    {
        $this->stubsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'blueprints';
    }

    public function generateBlueprints(\Roots\BedrockCli\DTOs\Profile|array $profile, string $projectPath): void
    {
        $blueprintsDir = $projectPath . DIRECTORY_SEPARATOR . 'blueprints';
        
        if (!is_dir($blueprintsDir)) {
            mkdir($blueprintsDir, 0755, true);
        }

        $environments = ['production', 'staging', 'development'];
        $projectName = basename($projectPath);

        foreach ($environments as $env) {
            $stubFile = $this->stubsPath . DIRECTORY_SEPARATOR . "{$env}.json.stub";
            $outputFile = $blueprintsDir . DIRECTORY_SEPARATOR . "{$env}.json";

            if (!file_exists($stubFile)) {
                continue;
            }

            $content = file_get_contents($stubFile);
            $content = $this->replaceVariables($content, $profile, $projectName, $env);
            
            file_put_contents($outputFile, $content);
        }
    }

    private function replaceVariables(string $content, array $profile, string $projectName, string $env): string
    {
        $replacements = [
            '{{PROJECT_NAME}}' => $projectName,
            '{{PRODUCTION_URL}}' => "https://{$projectName}.com",
            '{{STAGING_URL}}' => "https://staging.{$projectName}.com",
            '{{DEV_URL}}' => "http://{$projectName}.test",
            '{{ADMIN_USER}}' => 'admin',
            '{{ADMIN_EMAIL}}' => "admin@{$projectName}.com",
            '{{ACF_PRO_LICENSE}}' => '${ACF_PRO_LICENSE}',
            '{{GRAVITY_FORMS_LICENSE}}' => '${GRAVITY_FORMS_LICENSE}',
            '{{THEME_LICENSE}}' => '${THEME_LICENSE}'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
