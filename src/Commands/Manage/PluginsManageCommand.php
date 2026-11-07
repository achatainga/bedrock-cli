<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\PluginManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\CachedPackageInstallTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\Table;

class PluginsManageCommand extends Command
{
    use InteractiveSearchTrait;
    use CachedPackageInstallTrait;

    private ContextDetector $contextDetector;
    private ManagementService $management;
    private PluginManager $pluginManager;
    private DependencyManager $dependencyManager;
    private WordPressApiService $wpApi;

    public function __construct()
    {
        parent::__construct();
        $this->contextDetector = new ContextDetector();
        $this->management = new ManagementService($this->contextDetector);
        $this->pluginManager = new PluginManager($this->management);
        $this->dependencyManager = new DependencyManager($this->management);
        $this->wpApi = new WordPressApiService();
    }

    protected function configure(): void
    {
        $this->setName('manage:plugins')
             ->setDescription('Gestión de plugins del proyecto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->management->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        while (true) {
            $result = $this->showMenu($input, $output);
            if ($result === 'exit') {
                return Command::SUCCESS;
            }
        }
    }



    private array $pendingPlugins = [];

    private function showMenu(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $plugins = $this->pluginManager->list();

        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>   <fg=yellow;options=bold>🔌 GESTIÓN DE PLUGINS</><fg=magenta;options=bold>             ║</>');
        $output->writeln('<fg=magenta;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        if (!empty($plugins)) {
            $output->writeln("<fg=cyan>Plugins instalados (" . count($plugins) . "):</>");
            $output->writeln('');
            
            $index = 1;
            foreach ($plugins as $plugin) {
                $output->writeln(" <fg=cyan>[{$index}]</> {$plugin['slug']} <fg=gray>({$plugin['version']})</>");
                $index++;
            }
            $output->writeln('');
            $output->writeln(' <fg=yellow>[A]</> 🔍 Buscar e instalar (uno o varios)');
            $output->writeln(' <fg=yellow>[I]</> 📦 Importar .zip local');
            $output->writeln(' <fg=yellow>[O]</> 🔢 Orden de activación');
            $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        } else {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            $output->writeln('');
            $output->writeln(' <fg=yellow>[A]</> 🔍 Buscar e instalar (uno o varios)');
            $output->writeln(' <fg=yellow>[I]</> 📦 Importar .zip local');
            $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        }
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-' . count($plugins) . ', A, I, O]: </>', '0');
        $choice = strtoupper($helper->ask($input, $output, $question));

        if ($choice === '0') {
            return 'exit';
        } elseif ($choice === 'A') {
            $this->searchAndInstall($input, $output);
            return 'continue';
        } elseif ($choice === 'I') {
            $this->importZipPlugin($input, $output);
            return 'continue';
        } elseif ($choice === 'O') {
            $this->activationOrderMenu($input, $output);
            return 'continue';
        } elseif (is_numeric($choice) && $choice > 0 && $choice <= count($plugins)) {
            $pluginsList = array_values($plugins);
            $selectedPlugin = $pluginsList[$choice - 1];
            $this->managePlugin($input, $output, $selectedPlugin);
            return 'continue';
        } else {
            $output->writeln('<error>Opción inválida</error>');
            $this->waitForEnter($input, $output);
            return 'continue';
        }
    }

    private function managePlugin(InputInterface $input, OutputInterface $output, array $plugin): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln("<fg=magenta;options=bold>  🔌 {$plugin['slug']}</>");
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln('');
        $output->writeln("<info>Versión:</info> {$plugin['version']}");
        $output->writeln("<info>Tipo:</info> {$plugin['type']}");
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> ✅ Activar plugin');
        $output->writeln(' <fg=cyan>[2]</> ❌ Desactivar plugin');
        $output->writeln(' <fg=cyan>[3]</> 📊 Ver detalles');
        $output->writeln(' <fg=cyan>[4]</> 🗑️  Desinstalar (composer)');
        $output->writeln(' <fg=cyan>[5]</> 🗑️  Eliminar carpeta (filesystem)');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
        
        $question = new Question('<fg=yellow>Opción [0-5]: </>', '0');
        $choice = $helper->ask($input, $output, $question);
        
        switch ($choice) {
            case '1':
                $this->activatePlugin($output, $plugin['slug']);
                $this->waitForEnter($input, $output);
                break;
            case '2':
                $this->deactivatePlugin($output, $plugin['slug']);
                $this->waitForEnter($input, $output);
                break;
            case '3':
                $this->showPluginDetails($output, $plugin['slug']);
                $this->waitForEnter($input, $output);
                break;
            case '4':
                $this->uninstallPlugin($input, $output, $plugin);
                break;
            case '5':
                $this->deletePluginFolder($input, $output, $plugin['slug']);
                break;
        }
    }
    
    private function activatePlugin(OutputInterface $output, string $slug): void
    {
        $output->writeln('');
        $output->writeln("<info>Activando plugin: {$slug}</info>");
        
        $process = new \Symfony\Component\Process\Process([
            'docker-compose', 'exec', '-T', 'web', 'wp', 'plugin', 'activate', $slug
        ]);
        $process->setTimeout(30);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin activado correctamente</info>');
        } else {
            $output->writeln('<error>✗ Error al activar plugin</error>');
            $output->writeln('<comment>' . $process->getErrorOutput() . '</comment>');
        }
    }
    
    private function deactivatePlugin(OutputInterface $output, string $slug): void
    {
        $output->writeln('');
        $output->writeln("<info>Desactivando plugin: {$slug}</info>");
        
        $process = new \Symfony\Component\Process\Process([
            'docker-compose', 'exec', '-T', 'web', 'wp', 'plugin', 'deactivate', $slug
        ]);
        $process->setTimeout(30);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desactivado correctamente</info>');
        } else {
            $output->writeln('<error>✗ Error al desactivar plugin</error>');
            $output->writeln('<comment>' . $process->getErrorOutput() . '</comment>');
        }
    }
    
    private function showPluginDetails(OutputInterface $output, string $slug): void
    {
        $info = $this->wpApi->getPluginInfo($slug);
        
        if (!$info) {
            $output->writeln('<error>No se pudo obtener información del plugin</error>');
            return;
        }
        
        $output->writeln('');
        $output->writeln("<fg=yellow;options=bold>{$info['name']}</>");
        $output->writeln('');
        $output->writeln("<info>Slug:</info> {$info['slug']}");
        $output->writeln("<info>Versión:</info> {$info['version']}");
        $output->writeln("<info>Autor:</info> {$info['author']}");
        $output->writeln("<info>Rating:</info> {$info['rating']}/100");
        $output->writeln("<info>Instalaciones:</info> " . number_format($info['active_installs']) . "+");
        $output->writeln('');
        $output->writeln("<info>Descripción:</info>");
        $output->writeln(wordwrap(strip_tags($info['short_description'] ?? 'Sin descripción'), 70));
    }
    
    private function uninstallPlugin(InputInterface $input, OutputInterface $output, array $plugin): void
    {
        $helper = $this->getHelper('question');
        
        $question = new ConfirmationQuestion(
            "<fg=yellow>⚠ ¿Seguro que deseas desinstalar {$plugin['slug']}? (y/N):</> ",
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Desinstalando plugin: {$plugin['slug']}</info>");
        
        $this->pluginManager->remove($plugin['slug']);
        
        $exitCode = $this->dependencyManager->update(
            [$plugin['package']],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );
        
        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Plugin desinstalado correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al desinstalar plugin</error>');
        }
        
        $this->waitForEnter($input, $output);
    }
    
    private function deletePluginFolder(InputInterface $input, OutputInterface $output, string $slug): void
    {
        $helper = $this->getHelper('question');
        $pluginsDir = getcwd() . '/web/app/plugins';
        $pluginPath = $pluginsDir . '/' . $slug;
        
        if (!is_dir($pluginPath)) {
            $output->writeln('');
            $output->writeln("<error>Carpeta no encontrada: {$pluginPath}</error>");
            $this->waitForEnter($input, $output);
            return;
        }
        
        $output->writeln('');
        $output->writeln('<fg=red;options=bold>⚠️  ADVERTENCIA: Eliminación directa del filesystem</>');
        $output->writeln('<fg=yellow>Esto eliminará la carpeta sin pasar por composer</>');
        $output->writeln("<fg=yellow>Ruta: {$pluginPath}</>");
        $output->writeln('');
        
        $question = new Question('<fg=red>Escribe "ELIMINAR" para confirmar:</> ');
        $confirmation = $helper->ask($input, $output, $question);
        
        if ($confirmation !== 'ELIMINAR') {
            $output->writeln('<comment>Operación cancelada</comment>');
            $this->waitForEnter($input, $output);
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Eliminando carpeta {$slug}...</info>");
        
        if ($this->removeDirectory($pluginPath)) {
            $output->writeln("<info>✓ Carpeta eliminada: {$pluginPath}</info>");
        } else {
            $output->writeln('<error>✗ Error al eliminar la carpeta</error>');
        }
        
        $this->waitForEnter($input, $output);
    }
    
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

    private function searchAndInstall(InputInterface $input, OutputInterface $output): void
    {
        $this->pendingPlugins = [];
        
        while (true) {
            $helper = $this->getHelper('question');
            
            $output->writeln('');
            $question = new Question('<fg=yellow>Buscar plugin:</> ');
            $query = $helper->ask($input, $output, $question);

            if (!$query) {
                if (!empty($this->pendingPlugins)) {
                    $this->installPendingPlugins($input, $output);
                }
                return;
            }

            $output->writeln('<info>Buscando...</info>');
            $response = $this->wpApi->searchPlugins($query);
            $plugins = $response['plugins'] ?? [];

            if (empty($plugins)) {
                $output->writeln('<comment>No se encontraron plugins</comment>');
                continue;
            }

            $output->writeln('');
            $output->writeln('<comment>Resultados:</comment>');
            $output->writeln('');
            
            $this->displayPluginsTable(array_slice($plugins, 0, 10), $output);
            
            $output->writeln('');
            $question = new Question('<fg=yellow>Seleccionar número (o Enter para cancelar):</> ');
            $selection = $helper->ask($input, $output, $question);
            
            if (empty($selection)) {
                if (!empty($this->pendingPlugins)) {
                    $this->installPendingPlugins($input, $output);
                }
                return;
            }
            
            $index = (int)$selection - 1;
            if (!isset($plugins[$index])) {
                $output->writeln('<error>Selección inválida</error>');
                continue;
            }
            
            $slug = $plugins[$index]['slug'];
            $version = $this->selectPluginVersion($slug, $helper, $input, $output);
            
            $this->pendingPlugins[$slug] = $version;
            $output->writeln('');
            $output->writeln("<info>✓ {$slug} agregado</info>");
            
            if (count($this->pendingPlugins) === 1) {
                $output->writeln('');
                $question = new ConfirmationQuestion('<fg=yellow>¿Instalar ahora o buscar más plugins? (I=instalar, Enter=buscar más):</> ', false);
                if ($helper->ask($input, $output, $question)) {
                    $this->installPendingPlugins($input, $output);
                    return;
                }
            } else {
                $output->writeln("<comment>Plugins seleccionados: " . count($this->pendingPlugins) . "</comment>");
                $output->writeln('');
                $question = new ConfirmationQuestion('<fg=yellow>¿Instalar ahora o buscar más? (I=instalar, Enter=buscar más):</> ', false);
                if ($helper->ask($input, $output, $question)) {
                    $this->installPendingPlugins($input, $output);
                    return;
                }
            }
        }
    }
    
    private function installPendingPlugins(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->pendingPlugins)) {
            return;
        }
        
        $output->writeln('');
        $output->writeln('<info>Instalando ' . count($this->pendingPlugins) . ' plugin(s)...</info>');
        $output->writeln('');
        
        foreach ($this->pendingPlugins as $slug => $version) {
            $this->pluginManager->add($slug, 'wpackagist-plugin', $version);
        }
        
        $packages = [];
        foreach ($this->pendingPlugins as $slug => $version) {
            $packages[] = "wpackagist-plugin/{$slug}" . ($version === '*' ? '' : ":{$version}");
        }
        
        $exitCode = $this->dependencyManager->requireMultiple(
            $packages,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );
        
        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Plugins instalados correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al instalar plugins</error>');
        }
        
        $this->pendingPlugins = [];
        $this->waitForEnter($input, $output);
    }



    private function waitForEnter(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');
        $output->write('<comment>Presiona Enter para continuar...</comment>');
        if ($input->isInteractive()) {
            fgets(STDIN);
        }
    }

    private function importZipPlugin(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<info>📦 Importar plugin desde .zip local</info>');
        $output->writeln('');
        
        $question = new Question('<fg=yellow>Path al archivo .zip:</> ');
        $zipPath = $helper->ask($input, $output, $question);
        
        if (empty($zipPath) || !file_exists($zipPath)) {
            $output->writeln('<error>Archivo no encontrado</error>');
            $this->waitForEnter($input, $output);
            return;
        }
        
        $cacheService = new \Roots\BedrockCli\Services\PremiumCacheService();
        
        try {
            $output->writeln('  ⏳ Extrayendo metadata...');
            $metadata = $cacheService->extractMetadataFromZip($zipPath, 'plugin');
            
            $name = $metadata['name'];
            $version = $metadata['version'];
            
            if (!$name) {
                $question = new Question('  ❓ Ingresa el nombre del plugin: ');
                $name = $helper->ask($input, $output, $question);
            } else {
                $output->writeln("  ✓ Nombre detectado: {$name}");
            }
            
            if (!$version) {
                $question = new Question('  ❓ Ingresa la versión (o Enter para "imported-zip"): ', 'imported-zip');
                $version = $helper->ask($input, $output, $question);
            } else {
                $output->writeln("  ✓ Versión detectada: {$version}");
            }
            
            $output->writeln('  ⏳ Importando a cache...');
            $cacheService->importToCache($zipPath, $name, $version, 'plugin');
            
            $output->writeln('');
            $output->writeln("<info>✓ {$name} {$version} importado a cache</info>");
            $output->writeln('');
            
            $question = new ConfirmationQuestion('<fg=yellow>¿Agregar al proyecto ahora? (Y/n):</> ', true);
            if ($helper->ask($input, $output, $question)) {
                $this->pluginManager->add($name, 'cached', $version);
                
                $cachePath = $cacheService->getCachePath($name, $version);
                
                $exitCode = $this->installCachedPackage(
                    "cached/{$name}",
                    $version,
                    $cachePath,
                    $output
                );
                
                if ($exitCode === 0) {
                    $output->writeln('');
                    $output->writeln("<info>✓ Plugin {$name} instalado en el proyecto</info>");
                } else {
                    $output->writeln('');
                    $output->writeln('<error>✗ Error al instalar plugin</error>');
                }
            }
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
        }
        
        $this->waitForEnter($input, $output);
    }

    private function activationOrderMenu(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');

        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</> <fg=yellow;options=bold>🔢 ORDEN DE ACTIVACIÓN</><fg=magenta;options=bold>              ║</>');
        $output->writeln('<fg=magenta;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<fg=cyan>¿Qué deseas hacer?</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 🎯 Wizard interactivo (build)');
        $output->writeln(' <fg=cyan>[2]</> 💾 Guardar orden actual (save)');
        $output->writeln(' <fg=cyan>[3]</> 📊 Ver orden guardado (list)');
        $output->writeln(' <fg=cyan>[4]</> ⚡ Aplicar orden (activate)');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción:</> ', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->runOrderCommand('plugins:order:build', $input, $output);
                break;
            case '2':
                $this->runOrderCommand('plugins:order save', $input, $output);
                break;
            case '3':
                $this->runOrderCommand('plugins:order list', $input, $output);
                break;
            case '4':
                $this->runOrderCommand('plugins:order activate', $input, $output);
                break;
            case '0':
                return;
            default:
                $output->writeln('<error>Opción inválida</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function runOrderCommand(string $command, InputInterface $input, OutputInterface $output): void
    {
        $parts = explode(' ', $command);
        $commandName = $parts[0];
        $action = $parts[1] ?? null;

        $app = $this->getApplication();
        if (!$app) {
            $output->writeln('<error>No se pudo obtener la aplicación</error>');
            return;
        }

        try {
            $cmd = $app->find($commandName);
            $cmdInput = new \Symfony\Component\Console\Input\ArrayInput(
                $action ? ['command' => $commandName, 'action' => $action] : ['command' => $commandName]
            );
            $cmdInput->setInteractive(true);
            $cmd->run($cmdInput, $output);
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
        }
    }
}

