<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\UnzipService;
use Roots\BedrockCli\Services\ZipService;

class ThemesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('themes')
            ->setDescription('Gestión de temas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        while (true) {
            $choices = [
                1 => '<fg=green>Gestionar</>                 - tema específico',
                2 => '<fg=cyan>Listar desde WordPress</>    - (WP-CLI)',
                3 => '<fg=green>Actualizar</>                - todos los temas',
                4 => '<fg=green>Descomprimir</>              - ZIPs',
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
                    $this->manageTheme($input, $output, $wpcli);
                    break;
                case 2:
                    $this->listFromWordPress($wpcli, $output);
                    break;
                case 3:
                    $this->update($wpcli, $output);
                    break;
                case 4:
                    $this->unzipThemes($input, $output);
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
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    Temas desde WordPress (WP-CLI)   </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        try {
            $process = $wpcli->custom('theme list --format=table');
            $this->runWithLoader($process, $output, 'Consultando temas desde WordPress');
            
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

    private function listThemes(WpCliService $wpcli, OutputInterface $output): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $themesDir = $projectRoot . '/web/app/themes';

        if (!is_dir($themesDir)) {
            $output->writeln("<error>Carpeta de themes no encontrada: {$themesDir}</error>");
            return Command::FAILURE;
        }

        $themes = array_filter(scandir($themesDir), function($item) use ($themesDir) {
            return $item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item);
        });

        if (empty($themes)) {
            $output->writeln('<comment>No hay temas instalados</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Temas Instalados:</>');
        $output->writeln('');

        foreach ($themes as $theme) {
            $output->writeln("  <fg=green>•</> {$theme}");
        }

        $output->writeln('');
        $output->writeln("<info>Total: " . count($themes) . " temas</info>");

        return Command::SUCCESS;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $process = $wpcli->themeActivate($theme);
        $this->runWithLoader($process, $output, "Activando tema: {$theme}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function delete(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $process = $wpcli->themeDelete($theme);
        $this->runWithLoader($process, $output, "Eliminando tema: {$theme}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema eliminado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->themeUpdate();
        $this->runWithLoader($process, $output, 'Actualizando todos los temas');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Temas actualizados</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function unzipThemes(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $unzipService = new UnzipService();
        
        $projectRoot = $unzipService->detectProjectRoot();
        $themesZipDir = $projectRoot . '/themes';
        $themesInstallDir = $projectRoot . '/web/app/themes';

        if (!is_dir($themesZipDir)) {
            $output->writeln("<error>Carpeta de themes no encontrada: {$themesZipDir}</error>");
            $customPath = $helper->ask($input, $output, 
                new Question('<question>Ingrese la ruta a la carpeta de themes: </question>')
            );
            
            if (!$customPath || !is_dir($customPath)) {
                $output->writeln('<error>Ruta inválida. Cancelando.</error>');
                return;
            }
            
            $themesZipDir = $customPath;
        }

        $zipFiles = $unzipService->listZipFiles($themesZipDir);

        if (empty($zipFiles)) {
            $output->writeln("<comment>No se encontraron archivos .zip en: {$themesZipDir}</comment>");
            return;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Temas Disponibles para Instalar  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $choices = ['1' => '<fg=green>Todos</> - Descomprimir todos los temas'];
        
        foreach ($zipFiles as $index => $zipFile) {
            $zipPath = $themesZipDir . '/' . $zipFile;
            $size = $unzipService->getFileSize($zipPath);
            $choices[(string)($index + 2)] = "<fg=green>{$zipFile}</> ({$size})";
        }
        
        $choices['0'] = '<fg=yellow>Volver</>';

        $question = new ChoiceQuestion(
            '<fg=yellow>Seleccione un tema para descomprimir:</>', 
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
        
        if ($choice === '<fg=green>Todos</> - Descomprimir todos los temas') {
            $filesToUnzip = $zipFiles;
            $output->writeln('<info>Descomprimiendo todos los temas...</info>');
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
            $zipPath = $themesZipDir . '/' . $zipFile;
            $output->writeln("<info>Descomprimiendo {$zipFile}...</info>");
            
            if ($unzipService->unzip($zipPath, $themesInstallDir, $output)) {
                $output->writeln("<info>✓ {$zipFile} descomprimido correctamente</info>");
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $output->writeln('');
        $output->writeln("<info>Resumen: {$successCount} exitosos, {$failCount} fallidos</info>");
    }

    private function manageTheme(InputInterface $input, OutputInterface $output, WpCliService $wpcli): void
    {
        $helper = $this->getHelper('question');
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $themesDir = $projectRoot . '/web/app/themes';

        if (!is_dir($themesDir)) {
            $output->writeln("<error>Carpeta de themes no encontrada: {$themesDir}</error>");
            return;
        }

        $themes = array_filter(scandir($themesDir), function($item) use ($themesDir) {
            return $item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item);
        });

        if (empty($themes)) {
            $output->writeln('<comment>No hay temas instalados</comment>');
            return;
        }

        $choices = [];
        foreach (array_values($themes) as $index => $theme) {
            $choices[(string)($index + 1)] = "<fg=green>{$theme}</>";
        }
        $choices['0'] = '<fg=yellow>Volver</>';

        $question = new ChoiceQuestion('<fg=yellow>Seleccione un tema:</>', $choices, '0');
        $question->setAutocompleterValues(null);
        $choice = $helper->ask($input, $output, $question);

        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();

        if ($choice === '<fg=yellow>Volver</>') {
            return;
        }

        preg_match('/<fg=green>(.*?)<\/>/  ', $choice, $matches);
        $selectedTheme = $matches[1] ?? null;

        if (!$selectedTheme) {
            return;
        }

        $this->themeActions($input, $output, $wpcli, $selectedTheme, $themesDir);
    }

    private function themeActions(InputInterface $input, OutputInterface $output, WpCliService $wpcli, string $theme, string $themesDir): void
    {
        $helper = $this->getHelper('question');

        while (true) {
            $output->writeln('');
            $output->writeln("<fg=cyan;options=bold>Tema: {$theme}</>");
            $output->writeln('');

            $choices = [
                1 => '<fg=green>Activar</> tema',
                2 => '<fg=red>Eliminar</> tema',
                3 => '<fg=cyan>Estado / información</> (WP-CLI)',
                4 => '<fg=yellow>Comprimir</> a ZIP',
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
                    $this->activate($wpcli, $output, $theme);
                    break;
                case 2:
                    $this->delete($wpcli, $output, $theme);
                    return;
                case 3:
                    $this->themeStatus($wpcli, $output, $theme);
                    break;
                case 4:
                    $this->compressTheme($output, $theme, $themesDir);
                    break;
            }

            $output->writeln('');
        }
    }

    private function compressTheme(OutputInterface $output, string $theme, string $themesDir): void
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        
        $themePath = $themesDir . '/' . $theme;
        $zipPath = $projectRoot . '/themes/' . $theme . '.zip';

        $output->writeln("<info>Comprimiendo {$theme}...</info>");

        $zipService = new ZipService();
        if ($zipService->compress($themePath, $zipPath, $output)) {
            $output->writeln("<info>✓ Tema comprimido en: {$zipPath}</info>");
        } else {
            $output->writeln('<error>Error al comprimir tema</error>');
        }
    }

    private function themeStatus(WpCliService $wpcli, OutputInterface $output, string $theme): void
    {
        $process = $wpcli->custom("theme get {$theme}");
        $this->runWithLoader($process, $output, "Consultando información del tema: {$theme}");
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
