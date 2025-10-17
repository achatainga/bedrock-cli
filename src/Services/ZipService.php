<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;

class ZipService
{
    private UnzipService $unzipService;

    public function __construct()
    {
        $this->unzipService = new UnzipService();
    }

    public function compress(string $sourceDir, string $zipPath, OutputInterface $output): bool
    {
        if (!is_dir($sourceDir)) {
            $output->writeln("<error>Directorio no encontrado: {$sourceDir}</error>");
            return false;
        }

        // 1. Python Local
        if ($this->compressWithPythonLocal($sourceDir, $zipPath, $output)) {
            return true;
        }

        // 2. ZipArchive PHP Local
        if ($this->compressWithPhp($sourceDir, $zipPath, $output)) {
            return true;
        }

        // 3. Python Docker
        if ($this->compressWithPythonDocker($sourceDir, $zipPath, $output)) {
            return true;
        }

        // 4. zip Docker
        return $this->compressWithDocker($sourceDir, $zipPath, $output);
    }

    private function compressWithPythonLocal(string $sourceDir, string $zipPath, OutputInterface $output): bool
    {
        $projectRoot = $this->unzipService->detectProjectRoot();
        $pythonScript = $projectRoot . '/scripts/zip_file.py';

        if (!file_exists($pythonScript)) {
            return false;
        }

        $output->writeln('<fg=cyan>➜ Usando: Python Local</>');
        exec('python --version 2>&1', $pythonCheck, $pythonCode);
        
        if ($pythonCode !== 0) {
            $this->suggestPythonInstall($output);
            return false;
        }

        $command = sprintf('python "%s" "%s" "%s" 2>&1', $pythonScript, $sourceDir, $zipPath);
        exec($command, $output_lines, $return_code);
        
        if ($return_code === 0) {
            $output->writeln('<fg=green>✓ Comprimido exitosamente</>');
            return true;
        }
        
        $output->writeln('<error>✗ Error al comprimir con Python Local</error>');
        if (!empty($output_lines)) {
            $output->writeln('<comment>  ' . implode("\n  ", $output_lines) . '</comment>');
        }
        return false;
    }

    private function suggestPythonInstall(OutputInterface $output): void
    {
        $os = PHP_OS_FAMILY;
        
        if ($os === 'Windows') {
            exec('choco --version 2>&1', $chocoCheck, $chocoCode);
            if ($chocoCode === 0) {
                $output->writeln("<error>✗ Python no encontrado</error>");
                $output->writeln("<info>➜ Instalar con: <fg=green>choco install python</></info>");
            } else {
                $output->writeln("<error>✗ Python no encontrado</error>");
                $output->writeln("<info>➜ Descargar: <fg=cyan>https://www.python.org/downloads/</></info>");
                $output->writeln("<info>➜ O instalar Chocolatey: <fg=cyan>https://chocolatey.org/install</></info>");
            }
        } elseif ($os === 'Linux') {
            $output->writeln("<error>✗ Python no encontrado</error>");
            $output->writeln("<info>➜ Instalar con: <fg=green>sudo apt install python3</></info>");
        } elseif ($os === 'Darwin') {
            $output->writeln("<error>✗ Python no encontrado</error>");
            $output->writeln("<info>➜ Instalar con: <fg=green>brew install python3</></info>");
        }
    }

    private function compressWithPythonDocker(string $sourceDir, string $zipPath, OutputInterface $output): bool
    {
        $projectRoot = $this->unzipService->detectProjectRoot();
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        $pythonScript = $projectRoot . '/scripts/zip_file.py';
        
        if (!file_exists($dockerCompose) || !file_exists($pythonScript)) {
            return false;
        }

        $output->writeln('<fg=cyan>➜ Usando: Python Docker</>');
        $content = file_get_contents($dockerCompose);
        $containerName = 'bedrock_web';
        $dockerPath = '/var/www/html';
        
        if (preg_match('/container_name:\s*([^\s#]+)/m', $content, $matches)) {
            $containerName = trim($matches[1]);
        }
        if (preg_match('/- \.\/:([^\s:]+)/m', $content, $matches)) {
            $dockerPath = trim($matches[1]);
        }
        
        $dockerSourceDir = str_replace($projectRoot, $dockerPath, $sourceDir);
        $dockerZipPath = str_replace($projectRoot, $dockerPath, $zipPath);
        $dockerScriptPath = str_replace($projectRoot, $dockerPath, $pythonScript);
        $dockerSourceDir = str_replace('\\', '/', $dockerSourceDir);
        $dockerZipPath = str_replace('\\', '/', $dockerZipPath);
        $dockerScriptPath = str_replace('\\', '/', $dockerScriptPath);
        
        $command = sprintf(
            'docker exec %s python "%s" "%s" "%s" 2>&1',
            $containerName,
            $dockerScriptPath,
            $dockerSourceDir,
            $dockerZipPath
        );
        
        exec($command, $output_lines, $return_code);
        
        if ($return_code === 0) {
            $output->writeln('<fg=green>✓ Comprimido exitosamente</>');
            return true;
        }
        
        $output->writeln('<error>✗ Error al comprimir con Python Docker</error>');
        if (!empty($output_lines)) {
            $output->writeln('<comment>  ' . implode("\n  ", $output_lines) . '</comment>');
        }
        return false;
    }

    private function compressWithPhp(string $sourceDir, string $zipPath, OutputInterface $output): bool
    {
        $output->writeln('<fg=cyan>➜ Usando: ZipArchive PHP Local</>');
        
        if (!class_exists('ZipArchive')) {
            $this->suggestZipArchiveInstall($output);
            return false;
        }
        
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen(dirname($sourceDir)) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                $zip->close();
                $output->writeln('<fg=green>✓ Comprimido exitosamente</>');
                return true;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Error al comprimir con ZipArchive</error>');
            return false;
        }

        $output->writeln('<error>✗ Error al comprimir con ZipArchive</error>');
        return false;
    }

    private function suggestZipArchiveInstall(OutputInterface $output): void
    {
        $os = PHP_OS_FAMILY;
        
        if ($os === 'Windows') {
            $output->writeln("<error>✗ ZipArchive no disponible</error>");
            $output->writeln("<info>➜ Editar php.ini y descomentar: <fg=green>extension=zip</></info>");
            $output->writeln("<info>➜ Ubicar php.ini: <fg=green>php --ini</></info>");
        } elseif ($os === 'Linux') {
            $output->writeln("<error>✗ ZipArchive no disponible</error>");
            $output->writeln("<info>➜ Instalar con: <fg=green>sudo apt install php-zip</></info>");
        }
    }

    private function compressWithDocker(string $sourceDir, string $zipPath, OutputInterface $output): bool
    {
        $projectRoot = $this->unzipService->detectProjectRoot();
        $dockerCompose = $projectRoot . '/docker-compose.yml';
        
        if (!file_exists($dockerCompose)) {
            $output->writeln("<error>✗ No se pudo comprimir: Ningún método disponible</error>");
            return false;
        }

        $output->writeln('<fg=cyan>➜ Usando: zip Docker</>');
        $content = file_get_contents($dockerCompose);
        $containerName = 'bedrock_web';
        $dockerPath = '/var/www/html';
        
        if (preg_match('/container_name:\s*([^\s#]+)/m', $content, $matches)) {
            $containerName = trim($matches[1]);
        }
        if (preg_match('/- \.\/:([^\s:]+)/m', $content, $matches)) {
            $dockerPath = trim($matches[1]);
        }
        
        $dockerSourceDir = str_replace($projectRoot, $dockerPath, $sourceDir);
        $dockerZipPath = str_replace($projectRoot, $dockerPath, $zipPath);
        $dockerSourceDir = str_replace('\\', '/', $dockerSourceDir);
        $dockerZipPath = str_replace('\\', '/', $dockerZipPath);
        
        $command = sprintf(
            'docker exec %s zip -r "%s" "%s" 2>&1',
            $containerName,
            $dockerZipPath,
            $dockerSourceDir
        );
        
        exec($command, $output_lines, $return_code);

        if ($return_code === 0) {
            $output->writeln('<fg=green>✓ Comprimido exitosamente</>');
            return true;
        }
        
        $output->writeln('<error>✗ Error al comprimir con zip Docker</error>');
        if (!empty($output_lines)) {
            $output->writeln('<comment>  ' . implode("\n  ", $output_lines) . '</comment>');
        }
        $output->writeln("<error>✗ No se pudo comprimir: Ningún método disponible</error>");
        return false;
    }
}
