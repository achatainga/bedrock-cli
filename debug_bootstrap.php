<?php

// Debug script para identificar fuente exacta de errores PATH
echo "=== DEBUG BOOTSTRAP ===\n";

// 1. Test PHP básico
echo "1. PHP básico: OK\n";

// 2. Test autoloader
echo "2. Cargando autoloader...\n";
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadPaths as $file) {
    if (file_exists($file)) {
        echo "   Encontrado: $file\n";
        require $file;
        echo "   Cargado: OK\n";
        break;
    }
}

// 3. Test Application class
echo "3. Cargando Application class...\n";
use Roots\BedrockCli\Application;

echo "4. Creando Application instance...\n";
$app = new Application();

echo "5. Application creada: OK\n";
echo "=== FIN DEBUG ===\n";