<?php

$fixes = [
    'src/Commands/Options/MenuCommand.php' => ['OptionsCommand', 'MenuCommand'],
    'src/Commands/Options/PullCommand.php' => ['OptionsPullCommand', 'PullCommand'],
    'src/Commands/Options/PushCommand.php' => ['OptionsPushCommand', 'PushCommand'],
    'src/Commands/Options/ListCommand.php' => ['OptionsListCommand', 'ListCommand'],
    'src/Commands/Options/ManageCommand.php' => ['OptionsManageCommand', 'ManageCommand'],
    'src/Commands/Plugins/MenuCommand.php' => ['PluginsCommand', 'MenuCommand'],
    'src/Commands/Plugins/ListCommand.php' => ['PluginsListCommand', 'ListCommand'],
    'src/Commands/Plugins/ActivateCommand.php' => ['PluginsActivateCommand', 'ActivateCommand'],
    'src/Commands/Plugins/DeactivateCommand.php' => ['PluginsDeactivateCommand', 'DeactivateCommand'],
    'src/Commands/Plugins/CompressCommand.php' => ['PluginsCompressCommand', 'CompressCommand'],
    'src/Commands/Plugins/StatusCommand.php' => ['PluginsStatusCommand', 'StatusCommand'],
    'src/Commands/Plugins/OrderCommand.php' => ['PluginsOrderCommand', 'OrderCommand'],
    'src/Commands/Plugins/OrderMenuCommand.php' => ['PluginsOrderMenuCommand', 'OrderMenuCommand'],
    'src/Commands/Plugins/OrderBuilderCommand.php' => ['PluginsOrderBuilderCommand', 'OrderBuilderCommand'],
    'src/Commands/Themes/MenuCommand.php' => ['ThemesCommand', 'MenuCommand'],
    'src/Commands/Themes/ListCommand.php' => ['ThemesListCommand', 'ListCommand'],
    'src/Commands/Themes/ActivateCommand.php' => ['ThemesActivateCommand', 'ActivateCommand'],
    'src/Commands/Themes/CompressCommand.php' => ['ThemesCompressCommand', 'CompressCommand'],
    'src/Commands/Themes/StatusCommand.php' => ['ThemesStatusCommand', 'StatusCommand'],
];

$baseDir = dirname(__DIR__);

foreach ($fixes as $file => [$oldClass, $newClass]) {
    $path = $baseDir . '/' . $file;
    
    if (!file_exists($path)) {
        echo "⚠ No encontrado: $file\n";
        continue;
    }
    
    $content = file_get_contents($path);
    $content = str_replace("class $oldClass extends Command", "class $newClass extends Command", $content);
    file_put_contents($path, $content);
    
    echo "✓ $file: $oldClass → $newClass\n";
}

echo "\n✅ Nombres de clase corregidos\n";
