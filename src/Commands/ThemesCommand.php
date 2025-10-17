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
                1 => '<fg=green>Listar</> temas',
                2 => '<fg=green>Activar</> tema',
                3 => '<fg=green>Eliminar</> tema',
                4 => '<fg=green>Actualizar</> temas',
                5 => '<fg=green>Descomprimir</> ZIPs',
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
                    $this->listThemes($wpcli, $output);
                    break;
                case 2:
                    $theme = $helper->ask($input, $output, new Question('<fg=yellow>Tema a activar:</>'));
                    $this->activate($wpcli, $output, $theme);
                    break;
                case 3:
                    $theme = $helper->ask($input, $output, new Question('<fg=yellow>Tema a eliminar:</>'));
                    $this->delete($wpcli, $output, $theme);
                    break;
                case 4:
                    $this->update($wpcli, $output);
                    break;
                case 5:
                    $this->unzipThemes($input, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function listThemes(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->themeList();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $output->writeln("<info>Activando {$theme}...</info>");
        $process = $wpcli->themeActivate($theme);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function delete(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $output->writeln("<info>Eliminando {$theme}...</info>");
        $process = $wpcli->themeDelete($theme);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema eliminado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Actualizando todos los temas...</info>');
        $process = $wpcli->themeUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
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
}
