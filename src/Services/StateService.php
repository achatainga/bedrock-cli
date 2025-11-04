<?php

namespace Roots\BedrockCli\Services;

class StateService
{
    public function generateInitialState(string $projectPath, array $config): void
    {
        $state = [
            'version' => '1.0',
            'project_name' => basename($projectPath),
            'created_at' => date('Y-m-d H:i:s'),
            'current_step' => 1,
            'wizard_mode' => true,
            'steps' => $this->buildSteps($config)
        ];

        file_put_contents(
            "{$projectPath}/bedrock_state.json",
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function loadState(string $projectPath): ?array
    {
        $file = "{$projectPath}/bedrock_state.json";
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
}
