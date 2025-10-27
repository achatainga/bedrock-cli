<?php

namespace Roots\BedrockCli\Commands;

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

class PluginsCommand extends Command
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
            $choices = [
                1 => '<fg=green>Gestionar</> plugin específico',
                2 => '<fg=green>Instalar</> desde repositorio',
                3 => '<fg=green>Actualizar</> todos los plugins',
                4 => '<fg=green>Descomprimir</> ZIPs',
                5 => '<fg=yellow>Orden de Activación</> - Gestionar secuencia de carga',
                0 => '<fg=yellow>Volver atrás</>',
            ];
            
            $question = new ChoiceQuestion(
                '<fg=cyan>Selecciona una opción:</>',
                $choices,
                1
            );
            $question->setAutocompleterValues(null);

            $answer = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $index = is_numeric($answer) ? (int)$answer : array_search($answer, $choices);
            
            if ($index === 0) {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case 1:
                    $this->managePlugin($input, $output, $wpcli);
                    break;
                case 2:
                    $plugin = $helper->ask($input, $output, new Question('<fg=yellow>Slug del plugin:</>'));
                    $this->install($wpcli, $output, $plugin);
                    break;
                case 3:
                    $this->update($wpcli, $output);
                    break;
                case 4:
                    $this->unzipPlugins($input, $output);
                    break;
                case 5:
                    $command = $this->getApplication()->find('plugins:order:menu');
                    $command->run($input, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
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
        $output->writeln("<info>Instalando {$plugin}...</info>");
        $process = $wpcli->pluginInstall($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin instalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Activando {$plugin}...</info>");
        $process = $wpcli->pluginActivate($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function deactivate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Desactivando {$plugin}...</info>");
        $process = $wpcli->pluginDeactivate($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desactivado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function uninstall(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Desinstalando {$plugin}...</info>");
        $process = $wpcli->pluginUninstall($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desinstalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Actualizando todos los plugins...</info>');
        $process = $wpcli->pluginUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
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
                    $output->writeln("<info>Obteniendo información de {$plugin}...</info>");
                    $process = $wpcli->custom("plugin get {$plugin}");
                    $process->run(function ($type, $buffer) use ($output) {
                        $output->write($buffer);
                    });
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

}
