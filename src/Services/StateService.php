<?php

namespace Roots\BedrockCli\Services;

class StateService
{
    public function generateInitialState(string $projectPath, array $config): void
    {
        $stubPath = $this->getStubPath();
        
        if (!file_exists($stubPath)) {
            // Fallback: generar dinámicamente
            $state = [
                'version' => '1.0',
                'project_name' => basename($projectPath),
                'created_at' => date('Y-m-d H:i:s'),
                'current_step' => 1,
                'wizard_mode' => true,
                'steps' => $this->buildSteps($config)
            ];
        } else {
            // Usar stub y reemplazar variables
            $content = file_get_contents($stubPath);
            $vars = [
                '{{PROJECT_NAME}}' => basename($projectPath),
                '{{CREATED_AT}}' => date('Y-m-d H:i:s'),
                '{{HTTP_PORT}}' => $config['http_port'] ?? '80',
                '{{HAS_PLUGINS}}' => $config['has_plugins'] ? 'true' : 'false',
                '{{HAS_THEME}}' => $config['has_theme'] ? 'true' : 'false',
                '{{HAS_ACORN}}' => $config['has_acorn'] ? 'true' : 'false',
                '{{THEME_NAME}}' => $config['theme_name'] ?? 'twentytwentyfive'
            ];
            
            $content = str_replace(array_keys($vars), array_values($vars), $content);
            $state = json_decode($content, true);
            
            // Filtrar pasos según condiciones
            $state['steps'] = array_values(array_filter($state['steps'], function($step) {
                if (!isset($step['condition'])) return true;
                return $step['condition'] === 'true';
            }));
            
            // Reindexar IDs
            foreach ($state['steps'] as $i => &$step) {
                $step['id'] = $i + 1;
            }
        }

        file_put_contents(
            "{$projectPath}/bedrock_state.json",
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function loadState(string $projectPath): ?array
    {
        $file = "{$projectPath}/bedrock_state.json";
        
        // Si no existe y es un proyecto Bedrock, generarlo
        if (!file_exists($file) && $this->isBedrockProject($projectPath)) {
            $this->generateInitialState($projectPath, [
                'http_port' => $this->detectHttpPort($projectPath),
                'has_acorn' => $this->hasAcorn($projectPath),
                'has_plugins' => true,
                'has_theme' => true
            ]);
        }
        
        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    public function markCompleted(string $projectPath, int $stepId): void
    {
        $state = $this->loadState($projectPath);
        if (!$state) return;

        foreach ($state['steps'] as &$step) {
            if ($step['id'] === $stepId) {
                $step['completed'] = true;
                break;
            }
        }

        // Avanzar al siguiente paso no completado
        foreach ($state['steps'] as $step) {
            if (!$step['completed']) {
                $state['current_step'] = $step['id'];
                break;
            }
        }

        // Si todos completados, desactivar wizard
        if ($this->allCompleted($state['steps'])) {
            $state['wizard_mode'] = false;
        }

        file_put_contents(
            "{$projectPath}/bedrock_state.json",
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function markStepCompleted(int $stepId): void
    {
        $projectPath = getcwd();
        $this->markCompleted($projectPath, $stepId);
    }

    public function getCurrentStep(array $state): ?array
    {
        foreach ($state['steps'] as $step) {
            if ($step['id'] === $state['current_step']) {
                return $step;
            }
        }
        return null;
    }

    private function buildSteps(array $config): array
    {
        $steps = [
            [
                'id' => 1,
                'title' => 'Iniciar Docker',
                'description' => 'Levanta los contenedores (web, nginx, mysql, redis) ejecutando: docker-compose up -d',
                'command' => 'docker-compose up -d',
                'menu_item' => 'D',
                'completed' => false,
                'skippable' => false
            ],
            [
                'id' => 2,
                'title' => 'Instalar WordPress',
                'description' => "Instala WordPress Core en la base de datos. Ejecuta:\ndocker-compose exec web wp core install --url=http://localhost:{$config['http_port']} --title=\"Mi Sitio\" --admin_user=admin --admin_password=admin --admin_email=admin@example.com",
                'menu_item' => 'I',
                'completed' => false,
                'skippable' => false
            ]
        ];

        if ($config['has_plugins']) {
            $steps[] = [
                'id' => 3,
                'title' => 'Activar Plugins',
                'description' => 'Activa los plugins instalados desde el profile usando el menú [P] Plugins',
                'menu_item' => 'P',
                'completed' => false,
                'skippable' => true
            ];
        }

        if ($config['has_theme']) {
            $steps[] = [
                'id' => 4,
                'title' => 'Activar Tema',
                'description' => 'Activa el tema configurado en el profile usando el menú [T] Themes',
                'menu_item' => 'T',
                'completed' => false,
                'skippable' => true
            ];
        }

        if ($config['has_acorn']) {
            $steps[] = [
                'id' => 5,
                'title' => 'Configurar Acorn',
                'description' => "Inicializa Acorn ejecutando:\ndocker-compose exec web wp plugin activate acorn\ndocker-compose exec web wp acorn acorn:init storage\ndocker-compose exec web wp acorn vendor:publish --tag=acorn",
                'menu_item' => 'A',
                'completed' => false,
                'skippable' => true
            ];
        }

        return $steps;
    }

    private function allCompleted(array $steps): bool
    {
        foreach ($steps as $step) {
            if (!$step['completed'] && !$step['skippable']) {
                return false;
            }
        }
        return true;
    }

    private function getStubPath(): string
    {
        $reflection = new \ReflectionClass(self::class);
        $classFile = $reflection->getFileName();
        return dirname($classFile, 3) . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'bedrock_state.json.stub';
    }

    private function isBedrockProject(string $path): bool
    {
        return file_exists("{$path}/composer.json") && 
               file_exists("{$path}/config/application.php") &&
               file_exists("{$path}/web/wp");
    }

    private function detectHttpPort(string $path): string
    {
        $envFile = "{$path}/.env";
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if (preg_match('/WP_HOME=.*:(\d+)/', $content, $matches)) {
                return $matches[1];
            }
        }
        return '80';
    }

    private function hasAcorn(string $path): bool
    {
        $composerFile = "{$path}/composer.json";
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            return isset($composer['require']['roots/acorn']);
        }
        return false;
    }

    // ===== FASE 1: VALIDACIONES REALES =====

    /**
     * Verifica si Docker está corriendo y los contenedores del proyecto están activos
     */
    public function validateDockerRunning(string $projectPath): bool
    {
        // Verificar que Docker esté disponible
        $dockerCheck = shell_exec('docker --version 2>/dev/null');
        if (!$dockerCheck) {
            return false;
        }

        // Verificar contenedores del proyecto específico
        $projectName = basename($projectPath);
        $containers = shell_exec("docker ps --filter \"name={$projectName}\" --format \"{{.Names}}\" 2>/dev/null");
        
        if (!$containers) {
            return false;
        }

        // Verificar que al menos web y db estén corriendo
        $containerList = explode("\n", trim($containers));
        $hasWeb = false;
        $hasDb = false;

        foreach ($containerList as $container) {
            if (strpos($container, 'web') !== false) $hasWeb = true;
            if (strpos($container, 'mysql') !== false || strpos($container, 'db') !== false) $hasDb = true;
        }

        return $hasWeb && $hasDb;
    }

    /**
     * Verifica si WordPress está instalado verificando la base de datos
     */
    public function validateWordPressInstalled(string $projectPath): bool
    {
        // Verificar que wp-config.php existe
        $wpConfigPath = "{$projectPath}/web/wp-config.php";
        if (!file_exists($wpConfigPath)) {
            return false;
        }

        // Intentar verificar via WP-CLI si está disponible
        $projectName = basename($projectPath);
        $wpCheck = shell_exec("cd {$projectPath} && docker-compose exec -T web wp core is-installed 2>/dev/null");
        
        return $wpCheck !== null && trim($wpCheck) === '';
    }

    /**
     * Verifica si el tema especificado está activo
     */
    public function validateThemeActive(string $projectPath, string $themeName): bool
    {
        $projectName = basename($projectPath);
        $activeTheme = shell_exec("cd {$projectPath} && docker-compose exec -T web wp theme status {$themeName} 2>/dev/null");
        
        return $activeTheme && strpos($activeTheme, 'Active') !== false;
    }

    /**
     * Verifica si los plugins están activados
     */
    public function validatePluginsActive(string $projectPath): bool
    {
        $projectName = basename($projectPath);
        $plugins = shell_exec("cd {$projectPath} && docker-compose exec -T web wp plugin list --status=active --format=count 2>/dev/null");
        
        return $plugins && (int)trim($plugins) > 0;
    }

    /**
     * Verifica si Acorn está configurado correctamente
     */
    public function validateAcornConfigured(string $projectPath): bool
    {
        // Verificar que Acorn esté instalado
        if (!$this->hasAcorn($projectPath)) {
            return false;
        }

        // Verificar directorios de storage
        $storageDirs = [
            "{$projectPath}/storage/framework/cache",
            "{$projectPath}/storage/framework/views",
            "{$projectPath}/storage/logs"
        ];

        foreach ($storageDirs as $dir) {
            if (!is_dir($dir)) {
                return false;
            }
        }

        // Verificar que el plugin esté activo
        $projectName = basename($projectPath);
        $acornStatus = shell_exec("cd {$projectPath} && docker-compose exec -T web wp plugin status acorn 2>/dev/null");
        
        return $acornStatus && strpos($acornStatus, 'Active') !== false;
    }

    /**
     * Verifica acceso a repositorios premium (GitLab, GitHub, etc.)
     */
    public function validatePremiumRepoAccess(): bool
    {
        try {
            $repoUrl = getenv('PREMIUM_REPO_URL') ?: 'https://gitlab.com/detodo24/detodo24-premium-assets.git';
            $service = new \Roots\BedrockCli\Services\PremiumRepoService();
            $result = $service->checkAccess($repoUrl);
            return !($result['needs_auth'] ?? false);
        } catch (\Exception $e) {
            return true; // Asumir acceso OK si hay error
        }
    }

    /**
     * Verifica si hay API keys configuradas (OpenAI, Gemini, etc.)
     */
    public function validateApiKeysConfigured(): bool
    {
        $hasOpenAI = !empty(getenv('OPENAI_API_KEY'));
        $hasGemini = !empty(getenv('GEMINI_API_KEY'));
        $hasAnthropic = !empty(getenv('ANTHROPIC_API_KEY'));
        
        return $hasOpenAI || $hasGemini || $hasAnthropic;
    }

    /**
     * Verifica acceso a GitHub (token o CLI)
     */
    public function validateGitHubAccess(): bool
    {
        $hasGitHubToken = !empty(getenv('GITHUB_TOKEN'));
        $hasGitHubCLI = shell_exec('which gh 2>/dev/null') !== null;
        
        return $hasGitHubToken || $hasGitHubCLI;
    }

    /**
     * Verifica acceso a GitLab (token o CLI)
     */
    public function validateGitLabAccess(): bool
    {
        $hasGitLabToken = !empty(getenv('GITLAB_TOKEN'));
        $hasGitLabCLI = shell_exec('which glab 2>/dev/null') !== null;
        
        return $hasGitLabToken || $hasGitLabCLI;
    }

    /**
     * Cache para validaciones de servicios externos (evitar múltiples llamadas)
     */
    private static $externalValidationsCache = [];

    /**
     * Ejecuta validaciones de servicios externos con cache
     */
    public function validateExternalServices(): array
    {
        if (!empty(self::$externalValidationsCache)) {
            return self::$externalValidationsCache;
        }

        self::$externalValidationsCache = [
            'premium_repo' => $this->validatePremiumRepoAccess(),
            'api_keys' => $this->validateApiKeysConfigured(),
            'github' => $this->validateGitHubAccess(),
            'gitlab' => $this->validateGitLabAccess()
        ];

        return self::$externalValidationsCache;
    }

    /**
     * Actualiza el estado de los pasos basado en validaciones reales
     */
    public function updateStepValidations(string $projectPath): void
    {
        $state = $this->loadState($projectPath);
        if (!$state || !isset($state['steps'])) {
            return;
        }

        $updated = false;

        foreach ($state['steps'] as &$step) {
            $wasCompleted = $step['completed'] ?? false;
            
            switch ($step['id']) {
                case 1: // Docker
                    $step['completed'] = $this->validateDockerRunning($projectPath);
                    break;
                    
                case 2: // WordPress
                    $step['completed'] = $this->validateWordPressInstalled($projectPath);
                    break;
                    
                case 3: // Plugins
                    if (isset($step['condition']) && $step['condition'] === 'true') {
                        $step['completed'] = $this->validatePluginsActive($projectPath);
                    }
                    break;
                    
                case 4: // Theme
                    if (isset($step['condition']) && $step['condition'] === 'true') {
                        // Extraer nombre del tema del comando
                        if (preg_match('/wp theme activate (\w+)/', $step['command'] ?? '', $matches)) {
                            $step['completed'] = $this->validateThemeActive($projectPath, $matches[1]);
                        }
                    }
                    break;
                    
                case 5: // Acorn
                    if (isset($step['condition']) && $step['condition'] === 'true') {
                        $step['completed'] = $this->validateAcornConfigured($projectPath);
                    }
                    break;
            }
            
            if ($step['completed'] !== $wasCompleted) {
                $updated = true;
            }
        }

        // Actualizar current_step al primer paso no completado
        if ($updated) {
            foreach ($state['steps'] as $step) {
                if (!$step['completed'] && !$step['skippable']) {
                    $state['current_step'] = $step['id'];
                    break;
                }
            }

            // Si todos los pasos obligatorios están completados, desactivar wizard
            if ($this->allCompleted($state['steps'])) {
                $state['wizard_mode'] = false;
            }

            // Guardar estado actualizado
            file_put_contents(
                "{$projectPath}/bedrock_state.json",
                json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
    }
}
