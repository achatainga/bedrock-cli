<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\PluginManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
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
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  🔌 GESTIÓN DE PLUGINS          </> <fg=cyan;options=bold>    ║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        if (!empty($plugins)) {
            $output->writeln("<fg=cyan>✓ Plugins instalados (" . count($plugins) . "):</>");
            $output->writeln('');
            
            $table = new Table($output);
            $table->setHeaders(['<fg=cyan>Slug</>', '<fg=cyan>Versión</>']);
            foreach ($plugins as $plugin) {
                $table->addRow([$plugin['slug'], $plugin['version']]);
            }
            $table->render();
            $output->writeln('');
        }

        if (!empty($this->pendingPlugins)) {
            $output->writeln("<fg=yellow>⏳ Plugins pendientes de instalar (" . count($this->pendingPlugins) . "):</>");
            foreach ($this->pendingPlugins as $slug => $version) {
                $output->writeln("  • {$slug} ({$version})");
            }
            $output->writeln('');
        }

        $output->writeln('<fg=yellow>¿Qué deseas hacer?</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 🔍 Buscar y agregar a cola');
        $output->writeln(' <fg=cyan>[2]</> ⚡ Instalar plugins pendientes');
        $output->writeln(' <fg=cyan>[3]</> 🗑️  Desinstalar plugin');
        $output->writeln(' <fg=cyan>[4]</> 📊 Ver detalles de plugin');
        $output->writeln(' <fg=cyan>[5]</> 🧹 Limpiar cola');
        $output->writeln(' <fg=cyan>[6]</> 📦 Importar .zip local');
        $output->writeln(' <fg=cyan>[7]</> 🔢 Orden de activación');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción:</> ', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->searchAndQueue($input, $output);
                return 'continue';
            case '2':
                $this->installPending($input, $output);
                return 'continue';
            case '3':
                $this->uninstall($input, $output, $plugins);
                return 'continue';
            case '4':
                $this->showDetails($input, $output, $plugins);
                return 'continue';
            case '5':
                $this->pendingPlugins = [];
                $output->writeln('<info>✓ Cola limpiada</info>');
                $this->waitForEnter($input, $output);
                return 'continue';
            case '6':
                $this->importZipPlugin($input, $output);
                return 'continue';
            case '7':
                $this->activationOrderMenu($input, $output);
                return 'continue';
            case '0':
                return 'exit';
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
                return 'continue';
        }
    }

    private function searchAndQueue(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $question = new Question('<fg=yellow>Buscar plugin:</> ');
        $query = $helper->ask($input, $output, $question);

        if (!$query) {
            return;
        }

        $output->writeln('<info>Buscando...</info>');
        $response = $this->wpApi->searchPlugins($query);
        $plugins = $response['plugins'] ?? [];

        if (empty($plugins)) {
            $output->writeln('<comment>No se encontraron plugins</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $output->writeln('');
        $output->writeln('<comment>Resultados:</comment>');
        $output->writeln('');
        
        $this->displayPluginsTable(array_slice($plugins, 0, 10), $output);
        
        $output->writeln('');
        $question = new Question('<fg=yellow>Seleccionar número (o Enter para cancelar):</> ');
        $selection = $helper->ask($input, $output, $question);
        
        if (empty($selection)) {
            return;
        }
        
        $index = (int)$selection - 1;
        if (!isset($plugins[$index])) {
            $output->writeln('<error>Selección inválida</error>');
            $this->waitForEnter($input, $output);
            return;
        }
        
        $slug = $plugins[$index]['slug'];
        $version = $this->selectPluginVersion($slug, $helper, $input, $output);
        
        $this->pendingPlugins[$slug] = $version;
        
        $output->writeln("<info>✓ Plugin '{$slug}' ({$version}) agregado a la cola</info>");
        $output->writeln('');
        
        $question = new ConfirmationQuestion('<fg=yellow>¿Buscar otro plugin? (Y/n):</> ', true);
        if (!$helper->ask($input, $output, $question)) {
            return;
        }
        
        $this->searchAndQueue($input, $output);
    }

    private function uninstall(InputInterface $input, OutputInterface $output, array $plugins): void
    {
        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins para desinstalar</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $helper = $this->getHelper('question');
        $choices = [];
        
        foreach ($plugins as $plugin) {
            $choices[$plugin['slug']] = "{$plugin['slug']} ({$plugin['version']})";
        }
        $choices['cancel'] = 'Cancelar';

        $question = new ChoiceQuestion('Selecciona plugin a desinstalar:', $choices, 'cancel');
        $selected = $helper->ask($input, $output, $question);

        if ($selected === 'Cancelar') {
            return;
        }

        $slug = array_search($selected, $choices, true);
        if ($slug === 'cancel' || $slug === false) {
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Desinstalando plugin: {$slug}</info>");
        
        $this->pluginManager->remove($slug);
        
        $exitCode = $this->dependencyManager->update(
            ["wpackagist-plugin/{$slug}"],
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

    private function showDetails(InputInterface $input, OutputInterface $output, array $plugins): void
    {
        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $helper = $this->getHelper('question');
        $choices = [];
        
        foreach ($plugins as $plugin) {
            $choices[$plugin['slug']] = $plugin['slug'];
        }
        $choices['cancel'] = 'Cancelar';

        $question = new ChoiceQuestion('Selecciona plugin:', $choices, 'cancel');
        $selected = $helper->ask($input, $output, $question);

        if ($selected === 'Cancelar') {
            return;
        }

        $slug = array_search($selected, $choices);
        $info = $this->wpApi->getPluginInfo($slug);

        if (!$info) {
            $output->writeln('<error>No se pudo obtener información del plugin</error>');
            $this->waitForEnter($input, $output);
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

        $this->waitForEnter($input, $output);
    }

    private function installPending(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->pendingPlugins)) {
            $output->writeln('<comment>No hay plugins pendientes</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $output->writeln('');
        $output->writeln('<info>Instalando ' . count($this->pendingPlugins) . ' plugin(s)...</info>');
        $output->writeln('');

        foreach ($this->pendingPlugins as $slug => $version) {
            $output->writeln("<fg=cyan>➤ Instalando {$slug} ({$version})...</>");
            
            $this->pluginManager->add($slug, 'wpackagist-plugin', $version);
            
            $exitCode = $this->dependencyManager->require(
                "wpackagist-plugin/{$slug}",
                $version === '*' ? null : $version,
                false,
                function($buffer) use ($output) {
                    $output->write($buffer);
                }
            );

            if ($exitCode === 0) {
                $output->writeln("<fg=green>✓ {$slug} instalado</>");
            } else {
                $output->writeln("<fg=red>✗ Error al instalar {$slug}</>");
            }
            $output->writeln('');
        }

        $this->pendingPlugins = [];
        $output->writeln('<info>✓ Instalación completada</info>');
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
                
                $exitCode = $this->dependencyManager->requireWithRepository(
                    "cached/{$name}",
                    $version,
                    [
                        'type' => 'path',
                        'url' => $cachePath,
                        'options' => ['symlink' => true]
                    ],
                    false,
                    function($buffer) use ($output) {
                        $output->write($buffer);
                    }
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
                $this->runOrderCommand('plugins:order:build', $output);
                break;
            case '2':
                $this->runOrderCommand('plugins:order save', $output);
                break;
            case '3':
                $this->runOrderCommand('plugins:order list', $output);
                break;
            case '4':
                $this->runOrderCommand('plugins:order activate', $output);
                break;
            case '0':
                return;
            default:
                $output->writeln('<error>Opción inválida</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function runOrderCommand(string $command, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln("<info>➤ Ejecutando: bedrock {$command}</info>");
        $output->writeln('');

        $process = new \Symfony\Component\Process\Process(
            array_merge(['bedrock'], explode(' ', $command))
        );
        $process->setTty(true);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<error>✗ Error al ejecutar comando</error>');
        }
    }
}

