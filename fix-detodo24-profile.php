<?php
// Script temporal para fix DeTodo24-Develop profile

$profilePath = getenv('USERPROFILE') . '/.bedrock-cli/profiles/DeTodo24-Develop.json';
$profile = json_decode(file_get_contents($profilePath), true);

// Remover paquetes VCS incorrectos de require
$vcsPackages = [];
foreach ($profile['plugins']['premium'] as $plugin) {
    if ($plugin['source'] === 'vcs') {
        $vcsPackages[] = $plugin['name'];
    }
}

foreach (array_keys($profile['require']) as $package) {
    // Remover si empieza con detodo24dev/
    if (str_starts_with($package, 'detodo24dev/')) {
        unset($profile['require'][$package]);
        echo "Removed: $package\n";
    }
}

// Guardar
file_put_contents($profilePath, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\n✓ Profile fixed!\n";
echo "VCS repos will be resolved by composer from their composer.json\n";
