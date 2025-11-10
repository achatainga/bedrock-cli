<?php

require_once 'src/Services/VcsValidator.php';

$profilePath = 'C:\\Users\\achat\\.bedrock-cli\\profiles\\DeTodo24-Develop.json';
$profile = json_decode(file_get_contents($profilePath), true);

$validator = new \Roots\BedrockCli\Services\VcsValidator();

echo "🔍 Validando VCS plugins en DeTodo24-Develop...\n\n";

$updated = 0;
$failed = 0;

foreach ($profile['plugins']['premium'] as $plugin) {
    if ($plugin['source'] === 'vcs') {
        echo "Validando: {$plugin['url']}\n";
        $info = $validator->getPackageInfo($plugin['url']);
        
        if ($info) {
            echo "  ✓ Package: {$info['name']} (rama: {$info['branch']})\n";
            $profile['require'][$info['name']] = "dev-{$info['branch']}";
            $updated++;
        } else {
            echo "  ✗ No se pudo validar (repo privado sin token?)\n";
            $failed++;
        }
        echo "\n";
    }
}

if ($updated > 0) {
    file_put_contents($profilePath, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "✅ Profile actualizado: {$updated} packages agregados\n";
}

if ($failed > 0) {
    echo "⚠️  {$failed} plugins no pudieron validarse\n";
    echo "\nPara repos privados de GitLab, configura:\n";
    echo "  set GITLAB_TOKEN=tu_token_aqui\n";
}
