<?php

namespace Roots\BedrockCli\Commands\Options;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Process\Process;

class ManageCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:manage')
             ->setDescription('Gestionar opciones individuales (importar/exportar)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = getcwd() . '/config/options';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>   Gestionar Opciones Individual   </> <fg=cyan;options=bold>║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $files = glob("{$configDir}/*.json");
            
            if (empty($files)) {
                $output->writeln('<comment>No hay archivos JSON. Usa "Exportar" primero.</comment>');
                return Command::SUCCESS;
            }

            $choices = [];
            $fileMap = [];
            $index = 1;
            
            foreach ($files as $file) {
                $name = basename($file, '.json');
                $size = $this->formatSize(filesize($file));
                $choices[$index] = "<fg=green>{$name}</> ({$size})";
                $fileMap[$index] = $file;
                $index++;
            }
            
            $choices[0] = '<fg=red>Volver</>';

            $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 1);
            $question->setAutocompleterValues(null);
            $choice = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $selectedIndex = array_search($choice, $choices);
            
            if ($selectedIndex === 0) {
                return Command::SUCCESS;
            }

            $selectedFile = $fileMap[$selectedIndex];
            $this->manageOption($selectedFile, $input, $output, $helper);
        }

        return Command::SUCCESS;
    }

    protected function manageOption(string $file, InputInterface $input, OutputInterface $output, $helper): void
    {
        $data = json_decode(file_get_contents($file), true);
        $key = $data['key'];
        
        $output->writeln('');
        $output->writeln("<info>Opción: {$key}</info>");
        $output->writeln('');

        $choices = [
            1 => '<fg=green>Importar</>         - Inyectar a WordPress',
            2 => '<fg=cyan>Exportar</>          - Actualizar desde WordPress',
            3 => '<fg=yellow>Ver contenido</>   - Mostrar JSON',
            0 => '<fg=red>Volver</>',
        ];

        $question = new ChoiceQuestion('<fg=yellow>¿Qué deseas hacer?</>', $choices, 1);
        $question->setAutocompleterValues(null);
        $choice = $helper->ask($input, $output, $question);
        
        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();
        
        $selectedIndex = array_search($choice, $choices);
        
        $output->writeln('');
        
        switch ($selectedIndex) {
            case 1:
                $this->importOption($key, $data, $output);
                break;
            case 2:
                $this->exportOption($key, $file, $output);
                break;
            case 3:
                $this->showContent($file, $output);
                break;
        }
        
        $output->writeln('');
    }

    protected function importOption(string $key, array $data, OutputInterface $output): void
    {
        $output->writeln("<comment>Importando '{$key}' a WordPress...</comment>");
        
        $valueJson = json_encode($data['value']);
        $php = "update_option('{$key}', json_decode('{$valueJson}', true)); echo 'OK';";
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->run();
        
        if ($process->isSuccessful() && trim($process->getOutput()) === 'OK') {
            $output->writeln("<info>✓ Opción '{$key}' importada exitosamente</info>");
        } else {
            $output->writeln("<error>✗ Error al importar '{$key}'</error>");
        }
    }

    protected function exportOption(string $key, string $file, OutputInterface $output): void
    {
        $output->writeln("<comment>Exportando '{$key}' desde WordPress...</comment>");
        
        $php = "\$v = get_option('{$key}'); echo json_encode(['key' => '{$key}', 'value' => \$v, 'type' => gettype(\$v)]);";
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln("<error>✗ Error al obtener '{$key}'</error>");
            return;
        }

        $data = json_decode($process->getOutput(), true);
        
        if (!$data) {
            $output->writeln("<error>✗ Error al decodificar '{$key}'</error>");
            return;
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $output->writeln("<info>✓ Opción '{$key}' exportada exitosamente</info>");
    }

    protected function showContent(string $file, OutputInterface $output): void
    {
        $content = file_get_contents($file);
        $output->writeln('<fg=cyan>Contenido del archivo:</>');
        $output->writeln('');
        $output->writeln($content);
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
