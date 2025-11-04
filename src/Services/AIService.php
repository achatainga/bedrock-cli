<?php

namespace Roots\BedrockCli\Services;

class AIService
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
        $this->provider = $this->config['provider'] ?? 'openrouter';
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'deepseek/deepseek-chat-v3.1:free';
    }

    public function ask(string $question, array $context = []): string
    {
        if (empty($context)) {
            $contextBuilder = new AIContextBuilder();
            $context = $contextBuilder->buildContext();
        }
        
        $systemPrompt = $this->buildSystemPrompt($context);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question]
        ];

        return $this->sendRequest($messages);
    }

    public function chat(string $message, array $history = []): array
    {
        $contextBuilder = new AIContextBuilder();
        $context = $contextBuilder->buildContext();
        $systemPrompt = $this->buildSystemPrompt($context);
        
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        
        foreach ($history as $msg) {
            $messages[] = $msg;
        }
        
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->sendRequest($messages);
        
        return [
            'response' => $response,
            'history' => array_merge($history, [
                ['role' => 'user', 'content' => $message],
                ['role' => 'assistant', 'content' => $response]
            ])
        ];
    }

    private function sendRequest(array $messages): string
    {
        if ($this->provider === 'openrouter') {
            return $this->sendOpenRouterRequest($messages);
        }
        
        if ($this->provider === 'gemini') {
            return $this->sendGeminiRequest($messages);
        }

        throw new \Exception("Provider '{$this->provider}' no soportado");
    }

    private function sendOpenRouterRequest(array $messages): string
    {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://github.com/achatainga/bedrock-cli',
                'X-Title: Bedrock CLI'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->config['max_tokens'] ?? 2000,
                'temperature' => $this->config['temperature'] ?? 0.7
            ])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 0) {
            throw new \Exception("Conexión fallida: {$curlError}");
        }

        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            $errorMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
            throw new \Exception("OpenRouter: {$errorMsg}");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function sendGeminiRequest(array $messages): string
    {
        // Usar gemini-2.5-flash (estable) o gemini-flash-latest (siempre actualizado)
        $model = 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";
        
        $contents = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') continue;
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['contents' => $contents])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 0) {
            throw new \Exception("Conexión fallida: {$curlError}");
        }

        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            $errorMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
            throw new \Exception("Gemini: {$errorMsg}");
        }

        $data = json_decode($response, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function buildSystemPrompt(array $context = []): string
    {
        $prompt = "Eres un experto en Bedrock CLI, WordPress, Docker y desarrollo web.\n\n";
        
        if (!empty($context)) {
            $prompt .= "CONTEXTO DEL PROYECTO:\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "COMANDOS DISPONIBLES EN BEDROCK-CLI:\n";
        $prompt .= "- bedrock menu (menú principal)\n";
        $prompt .= "- bedrock new:wizard (crear proyecto nuevo)\n";
        $prompt .= "- bedrock doctor (verificar dependencias)\n";
        $prompt .= "- bedrock setup (instalación WordPress + Acorn)\n";
        $prompt .= "- bedrock docker (levantar/bajar contenedores)\n";
        $prompt .= "- bedrock acorn (configurar Acorn)\n";
        $prompt .= "- bedrock plugins:activate (activar plugins)\n";
        $prompt .= "- bedrock themes:activate (activar tema)\n";
        $prompt .= "- bedrock db (gestión base de datos)\n";
        $prompt .= "- bedrock manage (gestionar plugins/themes/deps)\n";
        $prompt .= "- bedrock profile:menu (perfiles de configuración)\n";
        $prompt .= "- bedrock backup (crear backup)\n";
        $prompt .= "- bedrock info (estado del proyecto)\n\n";

        $prompt .= "REGLAS CRÍTICAS:\n";
        $prompt .= "1. Responde en español\n";
        $prompt .= "2. SOLO sugiere comandos de la lista anterior\n";
        $prompt .= "3. NO inventes comandos que no existen\n";
        $prompt .= "4. Usa formato: `bedrock comando` para comandos\n";
        $prompt .= "5. Si no hay comando específico, sugiere usar el menú: `bedrock menu`\n";
        $prompt .= "6. Sé conciso y práctico\n\n";

        return $prompt;
    }

    private function loadConfig(): array
    {
        $configPath = $this->getConfigPath();
        
        if (!file_exists($configPath)) {
            return [];
        }

        return json_decode(file_get_contents($configPath), true) ?? [];
    }

    public function saveConfig(array $config): void
    {
        $configPath = $this->getConfigPath();
        $configDir = dirname($configPath);

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    }

    private function getConfigPath(): string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME');
        return $home . DIRECTORY_SEPARATOR . '.bedrock' . DIRECTORY_SEPARATOR . 'ai_config.json';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
