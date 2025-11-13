<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\UnzipService;
use Roots\BedrockCli\Services\ZipService;

class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('plugins')
            ->setDescription('Gestión de plugins')
            ->addArgument('plugin', InputArgument::OPTIONAL, 'Nombre del plugin')
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Eliminar carpeta del plugin (filesystem)')
            ->addOption('compress', null, InputOption::VALUE_NONE, 'Comprimir plugin a ZIP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        $plugin = $input->getArgument('plugin');
        
        if ($plugin && $input->getOption('delete')) {
            return $this->deletePluginFolderDirect($output, $plugin);
        }
        
        if ($plugin && $input->getOption('compress')) {
            return $this->compressPluginDirect($output, $plugin);
        }
        
        while (true) {
            $output->writeln('');
            $output->writeln('<cyan>╔═══════════════════════════════════════╗</cyan>');
            $output->writeln('<cyan>║</cyan>   🔌 PLUGINS - Gestión              <cyan>║</cyan>');
            $output->writeln('<cyan>╚═══════════════════════════════════════╝</cyan>');
            $output->writeln('');
            
            $output->writeln(' <cyan>[1]</cyan> ⚙️  Gestionar plugin específico');
            $output->writeln(' <cyan>[2]</cyan> 📋 Listar desde WordPress');
            $output->writeln(' <cyan>[3]</cyan> ⬇️  Instalar desde repositorio');
            $output->writeln(' <cyan>[4]</cyan> 🔄 Actualizar todos');
            $output->writeln(' <cyan>[5]</cyan> 📦 Descomprimir ZIPs');
            $output->writeln(' <cyan>[6]</cyan> 🔢 Orden de activación');
            $output->writeln(' <cyan>[7]</cyan> 🛠️  Constructor de orden');
            $output->writeln(' <cyan>[0]</cyan> ❌ Volver');
            $output->writeln('');
            
            $choices = [
                '1' => 'Gestionar',
                '2' => 'Listar',
                '3' => 'Instalar',
                '4' => 'Actualizar',
                '5' => 'Descomprimir',
                '6' => 'Orden',
                '7' => 'Constructor',
                '0' => 'Volver',
            ];
            
            $question = new ChoiceQuestion('', $choices, '1');
            $question->setAutocompleterValues(null);

            $index = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            if ($index === '0') {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case '1':
                    $this->managePlugin($input, $output, $wpcli);
                    break;
                case '2':
                    $this->listFromWordPress($wpcli, $output);
                    break;
                case '3':
                    $plugin = $helper->ask($input, $output, new Question('<cyan>Slug del plugin:</cyan> '));
                    $this->install($wpcli, $output, $plugin);
                    break;
                case '4':
                    $this->update($wpcli, $output);
                    break;
                case '5':
                    $this->unzipPlugins($input, $output);
                    break;
                case '6':
                    $command = $this->getApplication()->find('plugins:order:menu');
                    $command->run($input, $output);
                    break;
                case '7':
                    $command = $this->getApplication()->find('plugins:order:build');
                    $command->run($input, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function listFromWordPress(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>   Plugins desde WordPress (WP-CLI)  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        try {
            $process = $wpcli->custom('plugin list --format=table');
            $this->runWithLoader($process, $output, 'Consultando plugins desde WordPress');
            
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                $output->writeln('');
                $output->writeln('<error>✗ Error al consultar WordPress</error>');
                $output->writeln('');
                
                // Detectar tipo de error
                if (str_contains($errorOutput, 'Error establishing a database connection')) {
                    $output->writeln('<fg=red>⚠️  Error de conexión a la base de datos</>');
                    $output->writeln('<comment>Posibles causas:</comment>');
                    $output->writeln('  1. Docker no está corriendo');
                    $output->writeln('  2. Contenedor MySQL no está activo');
                    $output->writeln('  3. Credenciales de DB incorrectas en .env');
                    $output->writeln('');
                    $output->writeln('<fg=cyan>Solución sugerida:</>');
                    $output->writeln('  vendor/bin/bedrock docker --up');
                } elseif (str_contains($errorOutput, 'WordPress is not installed')) {
                    $output->writeln('<fg=red>⚠️  WordPress no está instalado</>');
                    $output->writeln('');
                    $output->writeln('<fg=cyan>Solución sugerida:</>');
                    $output->writeln('  vendor/bin/bedrock install');
                } elseif (str_contains($errorOutput, 'docker-compose') || str_contains($errorOutput, 'Cannot connect')) {
                    $output->writeln('<fg=red>⚠️  Docker no está disponible</>');
                    $output->writeln('<comment>Posibles causas:</comment>');
                    $output->writeln('  1. Docker Desktop no está corriendo');
                    $output->writeln('  2. Contenedores no están levantados');
                    $output->writeln('');
                    $output->writeln('<fg=cyan>Solución sugerida:</>');
                    $output->writeln('  vendor/bin/bedrock doctor');
                } else {
                    $output->writeln('<fg=yellow>Detalles del error:</>');
                    $output->writeln('<comment>' . trim($errorOutput) . '</comment>');
                }
                
                $output->writeln('');
                return Command::FAILURE;
            }
            
            // Mostrar output exitoso
            $output->writeln('');
            $output->write($process->getOutput());
            $output->writeln('');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Error inesperado: ' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }

    private function listPlugins(WpCliService $wpcli, OutputInterface $output): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            $output->writeln("<error>Carpeta de plugins no encontrada: {$pluginsDir}</error>");
            return Command::FAILURE;
        }

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Plugins Instalados:</>');
        $output->writeln('');

        foreach ($plugins as $plugin) {
            $output->writeln("  <fg=green>•</> {$plugin}");
        }

        $output->writeln('');
        $output->writeln("<info>Total: " . count($plugins) . " plugins</info>");

        return Command::SUCCESS;
    }

    private function install(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $process = $wpcli->pluginInstall($plugin);
        $this->runWithLoader($process, $output, "Instalando plugin: {$plugin}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin instalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $process = $wpcli->pluginActivate($plugin);
        $this->runWithLoader($process, $output, "Activando plugin: {$plugin}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function deactivate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $process = $wpcli->pluginDeactivate($plugin);
        $this->runWithLoader($process, $output, "Desactivando plugin: {$plugin}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desactivado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function uninstall(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $process = $wpcli->pluginUninstall($plugin);
        $this->runWithLoader($process, $output, "Desinstalando plugin: {$plugin}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desinstalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->pluginUpdate();
        $this->runWithLoader($process, $output, 'Actualizando todos los plugins');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugins actualizados</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function unzipPlugins(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $unzipService = new UnzipService();
        
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsZipDir = $projectRoot . '/plugins';
        $pluginsInstallDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsZipDir)) {
            $output->writeln("<error>Carpeta de plugins no encontrada: {$pluginsZipDir}</error>");
            $customPath = $helper->ask($input, $output, 
                new Question('<question>Ingrese la ruta a la carpeta de plugins: </question>')
            );
            
            if (!$customPath || !is_dir($customPath)) {
                $output->writeln('<error>Ruta inválida. Cancelando.</error>');
                return;
            }
            
            $pluginsZipDir = $customPath;
        }

        $zipFiles = $unzipService->listZipFiles($pluginsZipDir);

        if (empty($zipFiles)) {
            $output->writeln("<comment>No se encontraron archivos .zip en: {$pluginsZipDir}</comment>");
            return;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Plugins Disponibles para Instalar  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = ['1' => '<fg=green>Todos</> - Descomprimir todos los plugins'];
        
        foreach ($zipFiles as $index => $zipFile) {
            $zipPath = $pluginsZipDir . '/' . $zipFile;
            $size = $unzipService->getFileSize($zipPath);
            $choices[(string)($index + 2)] = "<fg=green>{$zipFile}</> ({$size})";
        }
        
        $choices['0'] = '<fg=yellow>Volver</>';

        $question = new ChoiceQuestion(
            '<fg=yellow>Seleccione un plugin para descomprimir:</>', 
            $choices, 
            '0'
        );
        $question->setAutocompleterValues(null);

        $choice = $helper->ask($input, $output, $question);
        
        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();

        if ($choice === '<fg=yellow>Volver</>') {
            return;
        }

        $filesToUnzip = [];
        
        if ($choice === '<fg=green>Todos</> - Descomprimir todos los plugins') {
            $filesToUnzip = $zipFiles;
            $output->writeln('<info>Descomprimiendo todos los plugins...</info>');
        } else {
            preg_match('/<fg=green>(.*?)<\/>/  ', $choice, $matches);
            $selectedFile = $matches[1] ?? null;
            
            if ($selectedFile && in_array($selectedFile, $zipFiles)) {
                $filesToUnzip = [$selectedFile];
            }
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($filesToUnzip as $zipFile) {
            $zipPath = $pluginsZipDir . '/' . $zipFile;
            $output->writeln("<info>Descomprimiendo {$zipFile}...</info>");
            
            if ($unzipService->unzip($zipPath, $pluginsInstallDir, $output)) {
                $output->writeln("<info>✓ {$zipFile} descomprimido correctamente</info>");
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $output->writeln('');
        $output->writeln("<info>Resumen: {$successCount} exitosos, {$failCount} fallidos</info>");
    }

    private function managePlugin(InputInterface $input, OutputInterface $output, WpCliService $wpcli): void
    {
        $helper = $this->getHelper('question');
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            $output->writeln("<error>Carpeta de plugins no encontrada: {$pluginsDir}</error>");
            return;
        }

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            return;
        }

        $choices = [];
        foreach (array_values($plugins) as $index => $plugin) {
            $choices[(string)($index + 1)] = "<fg=green>{$plugin}</>";
        }
        $choices['0'] = '<fg=yellow>Volver</>';

        $question = new ChoiceQuestion('<fg=yellow>Seleccione un plugin:</>', $choices, '0');
        $question->setAutocompleterValues(null);
        $choice = $helper->ask($input, $output, $question);

        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();

        if ($choice === '<fg=yellow>Volver</>') {
            return;
        }

        preg_match('/<fg=green>(.*?)<\/>/  ', $choice, $matches);
        $selectedPlugin = $matches[1] ?? null;

        if (!$selectedPlugin) {
            return;
        }

        $this->pluginActions($input, $output, $wpcli, $selectedPlugin, $pluginsDir);
    }

    private function pluginActions(InputInterface $input, OutputInterface $output, WpCliService $wpcli, string $plugin, string $pluginsDir): void
    {
        $helper = $this->getHelper('question');

        while (true) {
            $output->writeln('');
            $output->writeln("<fg=cyan;options=bold>Plugin: {$plugin}</>");
            $output->writeln('');

            $choices = [
                1 => '<fg=green>Activar</> plugin',
                2 => '<fg=yellow>Desactivar</> plugin',
                3 => '<fg=red>Desinstalar</> plugin (WP-CLI)',
                4 => '<fg=cyan>Estado / información</> (WP-CLI)',
                5 => '<fg=red;options=bold>Eliminar carpeta</> (filesystem)',
                6 => '<fg=cyan>Comprimir</> a ZIP',
                0 => '<fg=yellow>Volver</>',
            ];

            $question = new ChoiceQuestion('<fg=cyan>Selecciona una acción:</>', $choices, 0);
            $question->setAutocompleterValues(null);
            $answer = $helper->ask($input, $output, $question);

            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();

            $index = is_numeric($answer) ? (int)$answer : array_search($answer, $choices);

            if ($index === 0) {
                return;
            }

            $output->writeln('');

            switch ($index) {
                case 1:
                    $this->activate($wpcli, $output, $plugin);
                    break;
                case 2:
                    $this->deactivate($wpcli, $output, $plugin);
                    break;
                case 3:
                    $this->uninstall($wpcli, $output, $plugin);
                    return;
                case 4:
                    $process = $wpcli->custom("plugin get {$plugin}");
                    $this->runWithLoader($process, $output, "Consultando información del plugin: {$plugin}");
                    break;
                case 5:
                    $this->deletePluginFolder($input, $output, $plugin, $pluginsDir);
                    return;
                case 6:
                    $this->compressPlugin($output, $plugin, $pluginsDir);
                    break;
            }

            $output->writeln('');
        }
    }

    private function deletePluginFolder(InputInterface $input, OutputInterface $output, string $plugin, string $pluginsDir): void
    {
        $helper = $this->getHelper('question');
        $pluginPath = $pluginsDir . '/' . $plugin;

        $output->writeln('');
        $output->writeln('<fg=red;options=bold>⚠️  ADVERTENCIA: Eliminación directa del filesystem</>');  
        $output->writeln('<fg=yellow>Esto eliminará la carpeta sin pasar por WP-CLI</>');  
        $output->writeln("<fg=yellow>Ruta: {$pluginPath}</>");
        $output->writeln('');

        $question = new Question('<fg=red>Escribe "ELIMINAR" para confirmar:</> ');
        $confirmation = $helper->ask($input, $output, $question);

        if ($confirmation !== 'ELIMINAR') {
            $output->writeln('<comment>Operación cancelada</comment>');
            return;
        }

        if (!is_dir($pluginPath)) {
            $output->writeln("<error>La carpeta no existe: {$pluginPath}</error>");
            return;
        }

        $output->writeln("<info>Eliminando carpeta {$plugin}...</info>");

        if ($this->removeDirectory($pluginPath)) {
            $output->writeln("<info>✓ Carpeta eliminada: {$pluginPath}</info>");
        } else {
            $output->writeln('<error>Error al eliminar la carpeta</error>');
        }
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

    private function compressPlugin(OutputInterface $output, string $plugin, string $pluginsDir): void
    {
        $pluginPath = $pluginsDir . '/' . $plugin;
        $zipPath = dirname($pluginsDir) . '/../plugins/' . $plugin . '.zip';

        $output->writeln("<info>Comprimiendo {$plugin}...</info>");

        $zipService = new ZipService();
        if ($zipService->compress($pluginPath, $zipPath, $output)) {
            $output->writeln("<info>✓ Plugin comprimido en: {$zipPath}</info>");
        } else {
            $output->writeln('<error>Error al comprimir plugin</error>');
        }
    }

    private function deletePluginFolderDirect(OutputInterface $output, string $plugin): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';
        $pluginPath = $pluginsDir . '/' . $plugin;

        if (!is_dir($pluginPath)) {
            $output->writeln("<error>Plugin no encontrado: {$pluginPath}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Eliminando carpeta {$plugin}...</info>");

        if ($this->removeDirectory($pluginPath)) {
            $output->writeln("<info>✓ Carpeta eliminada: {$pluginPath}</info>");
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>Error al eliminar la carpeta</error>');
        return Command::FAILURE;
    }

    private function compressPluginDirect(OutputInterface $output, string $plugin): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';
        $pluginPath = $pluginsDir . '/' . $plugin;
        $zipPath = dirname($pluginsDir) . '/../plugins/' . $plugin . '.zip';

        if (!is_dir($pluginPath)) {
            $output->writeln("<error>Plugin no encontrado: {$pluginPath}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Comprimiendo {$plugin}...</info>");

        $zipService = new ZipService();
        if ($zipService->compress($pluginPath, $zipPath, $output)) {
            $output->writeln("<info>✓ Plugin comprimido en: {$zipPath}</info>");
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>Error al comprimir plugin</error>');
        return Command::FAILURE;
    }

    protected function runWithLoader(\Symfony\Component\Process\Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }

}
