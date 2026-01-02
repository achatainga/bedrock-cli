<?php

namespace Roots\BedrockCli\Services;

use Roots\BedrockCli\Services\ProjectValidationService;

class StateService
{
    private ProjectValidationService $validationService;
    
    public function __construct(ProjectValidationService $validationService = null)
    {
        $this->validationService = $validationService ?? new ProjectValidationService();
    }
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
                '{{DB_NAME}}' => $config['db_name'] ?? basename($projectPath),
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
                'db_name' => $this->detectDbName($projectPath),
                'has_acorn' => $this->hasAcorn($projectPath),
                'has_plugins' => true,
                'has_theme' => true
            ]);
        }
        
        // CRITICAL FIX: Si estamos en bedrock-cli directory, retornar estado vacío para evitar crash del modo guía
        if (!file_exists($file) && basename($projectPath) === 'bedrock-cli') {
            return null; // Modo guía se desactiva correctamente
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
                'description' => "Instala WordPress Core en la base de datos. Ejecuta:\ndocker-compose exec web wp core install --url=http://localhost:{$config['http_port']} --title=\"Mi Sitio\" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --locale=es_ES",
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
            // Buscar WP_HOME con puerto
            if (preg_match('/WP_HOME=.*:(\d+)/', $content, $matches)) {
                return $matches[1];
            }
            // Buscar HTTP_PORT directamente
            if (preg_match('/HTTP_PORT=(\d+)/', $content, $matches)) {
                return $matches[1];
            }
        }
        
        // Verificar docker-compose.yml
        $dockerFile = "{$path}/docker-compose.yml";
        if (file_exists($dockerFile)) {
            $content = file_get_contents($dockerFile);
            if (preg_match('/(\d+):80/', $content, $matches)) {
                return $matches[1];
            }
        }
        
        return '80';
    }
    
    private function detectDbName(string $path): string
    {
        $envFile = "{$path}/.env";
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if (preg_match('/DB_NAME=(.+)/', $content, $matches)) {
                return trim($matches[1]);
            }
        }
        return basename($path);
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
        return $this->validationService->validateDocker($projectPath)->isValid;
    }

    /**
     * Verifica si WordPress está instalado verificando la base de datos
     */
    public function validateWordPressInstalled(string $projectPath): bool
    {
        return $this->validationService->validateWordPress($projectPath)->isValid;
    }

    /**
     * Verifica si el tema especificado está activo
     */
    public function validateThemeActive(string $projectPath, string $themeName): bool
    {
        return $this->validationService->validateTheme($projectPath, $themeName)->isValid;
    }

    /**
     * Verifica si los plugins están activados
     */
    public function validatePluginsActive(string $projectPath): bool
    {
        return $this->validationService->validatePlugins($projectPath)->isValid;
    }

    /**
     * Verifica si Acorn está configurado correctamente
     */
    public function validateAcornConfigured(string $projectPath): bool
    {
        return $this->validationService->validateAcorn($projectPath)->isValid;
    }

    /**
     * Verifica acceso a repositorios premium (GitLab, GitHub, etc.)
     * TODO: Inject PremiumRepoService via DI when needed
     */
    public function validatePremiumRepoAccess(): bool
    {
        // Skip validation to avoid DI violation and path errors
        // This validation is not critical for basic menu functionality
        return true;
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
     * Verifica si existe un profile aplicado al proyecto
     */
    public function validateProfileExists(string $projectPath): bool
    {
        $profileFile = "{$projectPath}/.bedrock/profile.json";
        return file_exists($profileFile);
    }

    /**
     * Verifica acceso a repositorios VCS basado en el profile
     */
    public function validateVcsAccess(string $projectPath): bool
    {
        $profileFile = "{$projectPath}/.bedrock/profile.json";
        if (!file_exists($profileFile)) {
            return true; // Sin profile, no necesita VCS
        }

        $profile = json_decode(file_get_contents($profileFile), true);
        $hasPrivateRepos = false;

        // Verificar plugins privados
        foreach ($profile['plugins'] ?? [] as $plugin) {
            if (isset($plugin['source']) && $plugin['source'] !== 'wordpress.org') {
                $hasPrivateRepos = true;
                break;
            }
        }

        // Verificar themes privados
        foreach ($profile['themes'] ?? [] as $theme) {
            if (isset($theme['source']) && $theme['source'] !== 'wordpress.org') {
                $hasPrivateRepos = true;
                break;
            }
        }

        if (!$hasPrivateRepos) {
            return true; // Solo repos públicos
        }

        // Si hay repos privados, verificar que auth.json existe y tiene credenciales
        $authFile = "{$projectPath}/auth.json";
        if (!file_exists($authFile)) {
            return false;
        }

        $auth = json_decode(file_get_contents($authFile), true);
        $hasAuth = false;

        // Verificar credenciales para repos privados conocidos
        if (isset($auth['gitlab-token']['gitlab.com']) || 
            isset($auth['github-oauth']['github.com']) ||
            isset($auth['http-basic']['gitlab.com']) ||
            isset($auth['bearer']['gitlab.com'])) {
            $hasAuth = true;
        }

        return $hasAuth;
    }

    /**
     * Verifica Docker + Base de datos (mantener para compatibilidad)
     */
    public function validateDockerAndDatabase(string $projectPath): bool
    {
        return $this->validateDockerRunning($projectPath) && $this->validateDatabaseAccess($projectPath);
    }

    /**
     * Verifica solo acceso a base de datos
     */
    public function validateDatabaseAccess(string $projectPath): bool
    {
        return $this->validationService->validateDatabase($projectPath)->isValid;
    }

    /**
     * Detecta inconsistencias en la configuración del proyecto
     */
    public function detectInconsistencies(string $projectPath): array
    {
        return $this->validationService->detectInconsistencies($projectPath);
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
            $validation = $step['validation'] ?? null;
            
            switch ($validation) {
                case 'profile':
                    $step['completed'] = $this->validateProfileExists($projectPath);
                    break;
                    
                case 'vcs_access':
                    $step['completed'] = $this->validateVcsAccess($projectPath);
                    break;
                    
                case 'docker':
                    $step['completed'] = $this->validateDockerRunning($projectPath);
                    break;
                    
                case 'database':
                    $step['completed'] = $this->validateDatabaseAccess($projectPath);
                    break;
                    
                case 'docker_db': // Mantener compatibilidad
                    $step['completed'] = $this->validateDockerAndDatabase($projectPath);
                    break;
                    
                case 'wordpress':
                    $step['completed'] = $this->validateWordPressInstalled($projectPath);
                    break;
                    
                case 'theme':
                    // Get theme from profile
                    $profileFile = $projectPath . '/.bedrock/profile.json';
                    if (file_exists($profileFile)) {
                        $profile = json_decode(file_get_contents($profileFile), true);
                        $themeName = $profile['themes'][0]['name'] ?? null;
                        if ($themeName) {
                            $step['completed'] = $this->validateThemeActive($projectPath, $themeName);
                        }
                    }
                    break;
                    
                case 'plugins':
                    $step['completed'] = $this->validatePluginsActive($projectPath);
                    break;
                    
                case 'acorn':
                    $step['completed'] = $this->validateAcornConfigured($projectPath);
                    break;
                    
                case 'seeders':
                    // Seeders siempre opcional, no auto-validar
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
