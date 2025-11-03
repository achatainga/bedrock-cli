<?php

/**
 * Script de reorganización de comandos en carpetas
 * Mueve archivos, actualiza namespaces y regenera Application.php
 */

$baseDir = dirname(__DIR__);
$commandsDir = $baseDir . '/src/Commands';

// Mapa de reorganización: [archivo_actual => [carpeta_destino, nuevo_nombre, namespace]]
$reorganization = [
    // Database
    'DatabaseCommand.php' => ['Database', 'MenuCommand.php', 'Database'],
    'DbCleanCommand.php' => ['Database', 'CleanCommand.php', 'Database'],
    'SnapshotCommand.php' => ['Database', 'SnapshotCommand.php', 'Database'],
    'MigrateCommand.php' => ['Database', 'MigrateCommand.php', 'Database'],
    
    // Docker
    'DockerCommand.php' => ['Docker', 'DockerCommand.php', 'Docker'],
    
    // Options
    'OptionsCommand.php' => ['Options', 'MenuCommand.php', 'Options'],
    'OptionsPullCommand.php' => ['Options', 'PullCommand.php', 'Options'],
    'OptionsPushCommand.php' => ['Options', 'PushCommand.php', 'Options'],
    'OptionsListCommand.php' => ['Options', 'ListCommand.php', 'Options'],
    'OptionsManageCommand.php' => ['Options', 'ManageCommand.php', 'Options'],
    
    // Plugins
    'PluginsCommand.php' => ['Plugins', 'MenuCommand.php', 'Plugins'],
    'PluginsListCommand.php' => ['Plugins', 'ListCommand.php', 'Plugins'],
    'PluginsActivateCommand.php' => ['Plugins', 'ActivateCommand.php', 'Plugins'],
    'PluginsDeactivateCommand.php' => ['Plugins', 'DeactivateCommand.php', 'Plugins'],
    'PluginsCompressCommand.php' => ['Plugins', 'CompressCommand.php', 'Plugins'],
    'PluginsStatusCommand.php' => ['Plugins', 'StatusCommand.php', 'Plugins'],
    'PluginsOrderCommand.php' => ['Plugins', 'OrderCommand.php', 'Plugins'],
    'PluginsOrderMenuCommand.php' => ['Plugins', 'OrderMenuCommand.php', 'Plugins'],
    'PluginsOrderBuilderCommand.php' => ['Plugins', 'OrderBuilderCommand.php', 'Plugins'],
    
    // Themes
    'ThemesCommand.php' => ['Themes', 'MenuCommand.php', 'Themes'],
    'ThemesListCommand.php' => ['Themes', 'ListCommand.php', 'Themes'],
    'ThemesActivateCommand.php' => ['Themes', 'ActivateCommand.php', 'Themes'],
    'ThemesCompressCommand.php' => ['Themes', 'CompressCommand.php', 'Themes'],
    'ThemesStatusCommand.php' => ['Themes', 'StatusCommand.php', 'Themes'],
    
    // Acorn
    'AcornCommand.php' => ['Acorn', 'AcornCommand.php', 'Acorn'],
    
    // Setup
    'SetupCommand.php' => ['Setup', 'SetupCommand.php', 'Setup'],
    'NewCommand.php' => ['Setup', 'NewCommand.php', 'Setup'],
    'InitCommand.php' => ['Setup', 'InitCommand.php', 'Setup'],
    
    // System
    'MainMenuCommand.php' => ['System', 'MainMenuCommand.php', 'System'],
    'InitMenuCommand.php' => ['System', 'InitMenuCommand.php', 'System'],
    'SearchMenuCommand.php' => ['System', 'SearchMenuCommand.php', 'System'],
    'InfoCommand.php' => ['System', 'InfoCommand.php', 'System'],
    'DoctorCommand.php' => ['System', 'DoctorCommand.php', 'System'],
    'BackupCommand.php' => ['System', 'BackupCommand.php', 'System'],
    'ReinstallCommand.php' => ['System', 'ReinstallCommand.php', 'System'],
    'SeedCommand.php' => ['System', 'SeedCommand.php', 'System'],
    'ExportConfigCommand.php' => ['System', 'ExportConfigCommand.php', 'System'],
    'ImportCoreCommand.php' => ['System', 'ImportCoreCommand.php', 'System'],
];

echo "🔄 REORGANIZACIÓN DE COMANDOS\n";
echo "================================\n\n";

// Fase 1: Crear directorios
echo "📁 FASE 1: Creando directorios...\n";
$folders = array_unique(array_column($reorganization, 0));
foreach ($folders as $folder) {
    $path = $commandsDir . '/' . $folder;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "  ✓ Creado: $folder/\n";
    } else {
        echo "  ⊙ Ya existe: $folder/\n";
    }
}
echo "\n";

// Fase 2: Mover y actualizar archivos
echo "📦 FASE 2: Moviendo archivos y actualizando namespaces...\n";
foreach ($reorganization as $oldFile => [$folder, $newFile, $namespace]) {
    $oldPath = $commandsDir . '/' . $oldFile;
    $newPath = $commandsDir . '/' . $folder . '/' . $newFile;
    
    if (!file_exists($oldPath)) {
        echo "  ⚠ No encontrado: $oldFile\n";
        continue;
    }
    
    // Leer contenido
    $content = file_get_contents($oldPath);
    
    // Actualizar namespace
    $oldNamespace = 'namespace Roots\\BedrockCli\\Commands;';
    $newNamespace = "namespace Roots\\BedrockCli\\Commands\\$namespace;";
    $content = str_replace($oldNamespace, $newNamespace, $content);
    
    // Guardar en nueva ubicación
    file_put_contents($newPath, $content);
    
    // Eliminar archivo original
    unlink($oldPath);
    
    echo "  ✓ $oldFile → $folder/$newFile\n";
}
echo "\n";

// Fase 3: Generar nuevo Application.php
echo "🔧 FASE 3: Generando Application.php...\n";

$imports = [];
$registrations = [];

// Generar imports y registrations
foreach ($reorganization as $oldFile => [$folder, $newFile, $namespace]) {
    $className = str_replace('.php', '', $newFile);
    $fullNamespace = "Roots\\BedrockCli\\Commands\\$namespace";
    
    // Alias para evitar conflictos (MenuCommand, ListCommand, etc.)
    $alias = $folder . $className;
    
    $imports[] = "use $fullNamespace\\$className as $alias;";
    $registrations[] = "            new $alias(),";
}

// Ordenar alfabéticamente
sort($imports);
sort($registrations);

$applicationContent = <<<PHP
<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
{implode("\n", $imports)}

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('bedrock', '1.0.0');

        \$this->addCommands([
{implode("\n", $registrations)}
        ]);
    }

    public function getHelp(): string
    {
        return parent::getHelp() . "\\n\\n<comment>Abrir menu interactivo</comment>\\n\\n  <info>menu</info>           Abre el menú interactivo";
    }
}

PHP;

file_put_contents($baseDir . '/src/Application.php', $applicationContent);
echo "  ✓ Application.php regenerado\n\n";

// Resumen
echo "✅ REORGANIZACIÓN COMPLETADA\n";
echo "================================\n";
echo "Archivos movidos: " . count($reorganization) . "\n";
echo "Carpetas creadas: " . count($folders) . "\n\n";
echo "📝 Próximos pasos:\n";
echo "  1. Revisar cambios: git status\n";
echo "  2. Probar: php bin/bedrock list\n";
echo "  3. Commit: git add . && git commit -m 'refactor: reorganize commands into folders'\n";
