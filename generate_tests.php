<?php
$commands = [
    ['Themes', 'ListCommand', 'themes:list', 'Listar temas instalados'],
    ['Themes', 'StatusCommand', 'themes:status', 'Ver estado de temas'],
    ['Themes', 'MenuCommand', 'themes', 'Menú de gestión de temas'],
    ['Themes', 'CompressCommand', 'themes:compress', 'Comprimir temas'],
    ['Options', 'ManageCommand', 'options:manage', 'Gestionar opciones de WordPress'],
    ['Options', 'MenuCommand', 'options', 'Menú de opciones'],
    ['Options', 'PullCommand', 'options:pull', 'Descargar opciones desde WordPress'],
    ['Options', 'PushCommand', 'options:push', 'Subir opciones a WordPress'],
    ['Auth', 'ListCommand', 'auth:list', 'Listar credenciales guardadas'],
    ['Auth', 'RemoveCommand', 'auth:remove', 'Eliminar credenciales'],
    ['Auth', 'MenuCommand', 'auth', 'Menú de autenticación'],
    ['Manage', 'PluginsManageCommand', 'manage:plugins', 'Gestionar plugins del proyecto'],
    ['Manage', 'ThemesManageCommand', 'manage:themes', 'Gestionar temas del proyecto'],
    ['Manage', 'DependenciesManageCommand', 'manage:dependencies', 'Gestionar dependencias Composer'],
    ['Add', 'PluginCommand', 'add:plugin', 'Agregar plugin al proyecto'],
    ['Add', 'ThemeCommand', 'add:theme', 'Agregar tema al proyecto'],
    ['Add', 'DependencyCommand', 'add:dependency', 'Agregar dependencia Composer'],
    ['Remove', 'PluginCommand', 'remove:plugin', 'Remover plugin del proyecto'],
    ['Remove', 'ThemeCommand', 'remove:theme', 'Remover tema del proyecto'],
    ['Remove', 'DependencyCommand', 'remove:dependency', 'Remover dependencia Composer'],
    ['Plugin', 'SearchCommand', 'plugin:search', 'Buscar plugins en WordPress.org'],
    ['Plugin', 'InfoCommand', 'plugin:info', 'Ver información de un plugin'],
    ['Theme', 'SearchCommand', 'theme:search', 'Buscar temas en WordPress.org'],
    ['Theme', 'InfoCommand', 'theme:info', 'Ver información de un tema'],
    ['System', 'InfoCommand', 'info', 'Información del sistema'],
    ['System', 'ReinstallCommand', 'reinstall', 'Reinstalar WordPress'],
    ['System', 'SeedCommand', 'seed', 'Ejecutar seeders'],
    ['System', 'ExportConfigCommand', 'export-config', 'Exportar configuración'],
    ['System', 'ImportCoreCommand', 'import-core', 'Importar WordPress core'],
    ['System', 'MainMenuCommand', 'menu', 'Menú principal'],
    ['System', 'InitMenuCommand', 'init-menu', 'Menú de inicialización'],
    ['System', 'SearchMenuCommand', 'search', 'Menú de búsqueda']
];

foreach ($commands as $cmd) {
    [$folder, $class, $name, $desc] = $cmd;
    $namespace = "Tests\\Unit\\Commands\\$folder";
    $useClass = "Roots\\BedrockCli\\Commands\\$folder\\$class";
    
    $content = "<?php

namespace $namespace;

use PHPUnit\\Framework\\TestCase;
use $useClass;
use Symfony\\Component\\Console\\Application;

class {$class}Test extends TestCase
{
    public function testCommandConfiguration(): void
    {
        \$command = new $class();
        
        \$this->assertEquals('$name', \$command->getName());
        \$this->assertEquals('$desc', \$command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        \$application = new Application();
        \$application->add(new $class());
        
        \$this->assertTrue(\$application->has('$name'));
    }
}
";
    
    $file = "tests/Unit/Commands/$folder/{$class}Test.php";
    file_put_contents($file, $content);
    echo "Created: $file\n";
}

echo "\nTotal: " . count($commands) . " files created\n";
