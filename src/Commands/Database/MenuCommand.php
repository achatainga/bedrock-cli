<?php

namespace Roots\BedrockCli\Commands\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\SecurityService;

class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db')
            ->setDescription('Gestión de base de datos')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Crear base de datos')
            ->addOption('drop', null, InputOption::VALUE_NONE, 'Eliminar base de datos')
            ->addOption('import', null, InputOption::VALUE_REQUIRED, 'Importar SQL')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Exportar SQL')
            ->addOption('search-replace', null, InputOption::VALUE_REQUIRED, 'Buscar y reemplazar (formato: "buscar|reemplazar")')
            ->addOption('prefix-replace', null, InputOption::VALUE_REQUIRED, 'Cambiar prefijo de tablas (formato: "viejo|nuevo")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        if ($input->getOption('create')) {
            return $this->create($wpcli, $output);
        }
        if ($input->getOption('drop')) {
            return $this->drop($wpcli, $output);
        }
        if ($file = $input->getOption('import')) {
            return $this->import($wpcli, $output, $file);
        }
        if ($file = $input->getOption('export')) {
            return $this->export($wpcli, $output, $file);
        }
        if ($searchReplace = $input->getOption('search-replace')) {
            $parts = explode('|', $searchReplace);
            if (count($parts) !== 2) {
                $output->writeln('<error>Formato inválido. Use: "buscar|reemplazar"</error>');
                return Command::FAILURE;
            }
            return $this->searchReplaceDirect($output, $wpcli, $parts[0], $parts[1]);
        }
        if ($prefixReplace = $input->getOption('prefix-replace')) {
            $parts = explode('|', $prefixReplace);
            if (count($parts) !== 2) {
                $output->writeln('<error>Formato inválido. Use: "viejo|nuevo"</error>');
                return Command::FAILURE;
            }
            return $this->prefixReplaceDirect($output, $wpcli, $parts[0], $parts[1]);
        }

        return $this->showMenu($input, $output, $wpcli);
    }

    private function showMenu(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<cyan>╔═══════════════════════════════════════╗</cyan>');
            $output->writeln('<cyan>║</cyan>   🗄️  DATABASE - Base de Datos       <cyan>║</cyan>');
            $output->writeln('<cyan>╚═══════════════════════════════════════╝</cyan>');
            $output->writeln('');
            $output->writeln('<comment>Operaciones de base de datos: crear, importar, exportar, seeders.</comment>');
            $output->writeln('');
            
            $output->writeln(' <fg=cyan>[1]</> ➕ Crear base de datos');
            $output->writeln(' <fg=cyan>[2]</> 🗑️  Eliminar base de datos');
            $output->writeln(' <fg=cyan>[3]</> 🔄 Resetear base de datos');
            $output->writeln(' <fg=cyan>[4]</> 📥 Importar SQL');
            $output->writeln(' <fg=cyan>[5]</> 📤 Exportar SQL');
            $output->writeln(' <fg=cyan>[6]</> 🔍 Buscar/Reemplazar en DB');
            $output->writeln(' <fg=cyan>[7]</> 🏷️  Cambiar prefijo de tablas');
            $output->writeln(' <fg=cyan>[8]</> ⚙️  Ejecutar Query SQL');
            $output->writeln(' <fg=cyan>[9]</> 🌱 Seeders');
            $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
            $output->writeln('');
            
            $question = new Question('Opción [0-9]: ', '0');
            $index = $helper->ask($input, $output, $question);
            
            if (!is_numeric($index) || $index < 0 || $index > 9) {
                $output->writeln('<error>Opción inválida</error>');
                continue;
            }
            
            if ($index === '0') {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case '1':
                    $this->create($wpcli, $output);
                    break;
                case '2':
                    $this->drop($wpcli, $output);
                    break;
                case '3':
                    $this->reset($wpcli, $output);
                    break;
                case '4':
                    $output->writeln('<comment>Función de importación interactiva pendiente</comment>');
                    break;
                case '5':
                    $output->writeln('<comment>Función de exportación interactiva pendiente</comment>');
                    break;
                case '6':
                    $this->searchReplace($input, $output, $wpcli);
                    break;
                case '7':
                    $this->prefixReplace($input, $output, $wpcli);
                    break;
                case '8':
                    $this->query($input, $output, $wpcli);
                    break;
                case '9':
                    $this->seedersMenu($input, $output, $wpcli);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function create(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->dbCreate();
        $this->runWithLoader($process, $output, 'Creando base de datos');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos creada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al crear base de datos</error>');
        return Command::FAILURE;
    }

    private function drop(WpCliService $wpcli, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        if (!SecurityService::confirmDangerousAction(
            $input,
            $output,
            $helper,
            'Esta acción ELIMINARÁ PERMANENTEMENTE la base de datos.'
        )) {
            return Command::SUCCESS;
        }
        
        $process = $wpcli->dbDrop();
        $this->runWithLoader($process, $output, 'Eliminando base de datos');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos eliminada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al eliminar base de datos</error>');
        return Command::FAILURE;
    }

    private function import(WpCliService $wpcli, OutputInterface $output, string $file): int
    {
        $process = $wpcli->dbImport($file);
        $this->runWithLoader($process, $output, "Importando base de datos desde {$file}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos importada</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function export(WpCliService $wpcli, OutputInterface $output, string $file): int
    {
        $process = $wpcli->dbExport($file);
        $this->runWithLoader($process, $output, "Exportando base de datos a {$file}");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos exportada</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function reset(WpCliService $wpcli, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        if (!SecurityService::confirmDangerousAction(
            $input,
            $output,
            $helper,
            'Esta acción BORRARÁ TODOS LOS DATOS de la base de datos.'
        )) {
            return Command::SUCCESS;
        }
        
        $process = $wpcli->dbReset();
        $this->runWithLoader($process, $output, 'Reseteando base de datos');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Base de datos reseteada</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al resetear base de datos</error>');
        return Command::FAILURE;
    }

    private function searchReplace(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>ℹ️  Buscar y Reemplazar en Base de Datos</>');
        $output->writeln('<fg=yellow>Ejemplo: https://example.com → http://localhost:8080</>');
        $output->writeln('');
        
        $search = $helper->ask($input, $output, new Question('<fg=yellow>Buscar:</> '));
        $replace = $helper->ask($input, $output, new Question('<fg=yellow>Reemplazar por:</> '));
        
        if (!$search || !$replace) {
            $output->writeln('<error>Valores inválidos</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('');
        
        $process = $wpcli->custom("search-replace '{$search}' '{$replace}' --skip-columns=guid --all-tables");
        $this->runWithLoader($process, $output, "Buscando '{$search}' y reemplazando por '{$replace}'");
        
        if ($process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<info>✓ Reemplazo completado exitosamente</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al ejecutar reemplazo</error>');
        return Command::FAILURE;
    }

    private function prefixReplace(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>ℹ️  Cambiar Prefijo de Tablas</>');
        $output->writeln('<fg=yellow>Ejemplo: hp2f_ → wp_</>');
        $output->writeln('<fg=red;options=bold>⚠️  ADVERTENCIA: Esta operación modifica la estructura de la base de datos</>');
        $output->writeln('');
        
        $oldPrefix = $helper->ask($input, $output, new Question('<fg=yellow>Prefijo actual:</> '));
        $newPrefix = $helper->ask($input, $output, new Question('<fg=yellow>Nuevo prefijo:</> '));
        
        if (!$oldPrefix || !$newPrefix) {
            $output->writeln('<error>Valores inválidos</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('');
        
        $process = $wpcli->custom("db prefix replace {$oldPrefix} {$newPrefix} --yes --include-multiple-prefixes");
        $this->runWithLoader($process, $output, "Cambiando prefijo de '{$oldPrefix}' a '{$newPrefix}'");
        
        if ($process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<info>✓ Prefijo cambiado exitosamente</info>');
            $output->writeln('<fg=yellow>⚠️  Recuerda actualizar el archivo .env con el nuevo prefijo si es necesario</>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al cambiar prefijo</error>');
        return Command::FAILURE;
    }

    private function query(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        $query = $helper->ask($input, $output, new Question('<fg=yellow>Query SQL:</>'));
        
        $output->writeln('<info>Ejecutando query...</info>');
        $process = $wpcli->dbQuery($query);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Query ejecutado</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function searchReplaceDirect(OutputInterface $output, WpCliService $wpcli, string $search, string $replace): int
    {
        $process = $wpcli->custom("search-replace '{$search}' '{$replace}' --skip-columns=guid --all-tables");
        $this->runWithLoader($process, $output, "Buscando '{$search}' y reemplazando por '{$replace}'");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Reemplazo completado exitosamente</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al ejecutar reemplazo</error>');
        return Command::FAILURE;
    }

    private function prefixReplaceDirect(OutputInterface $output, WpCliService $wpcli, string $oldPrefix, string $newPrefix): int
    {
        $process = $wpcli->custom("db prefix replace {$oldPrefix} {$newPrefix} --yes --include-multiple-prefixes");
        $this->runWithLoader($process, $output, "Cambiando prefijo de '{$oldPrefix}' a '{$newPrefix}'");
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Prefijo cambiado exitosamente</info>');
            $output->writeln('<fg=yellow>⚠️  Recuerda actualizar el archivo .env con el nuevo prefijo si es necesario</>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al cambiar prefijo</error>');
        return Command::FAILURE;
    }

    private function seedersMenu(InputInterface $input, OutputInterface $output, WpCliService $wpcli): int
    {
        $helper = $this->getHelper('question');
        $seedersPath = getcwd() . '/database/seeders';
        
        if (!is_dir($seedersPath)) {
            mkdir($seedersPath, 0755, true);
        }
        
        while (true) {
            $seeders = glob($seedersPath . '/*Seeder.php');
            
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>🌱 Seeders Disponibles</>');
            $output->writeln('');
            
            $choices = [
                1 => '<fg=green>Ejecutar todos</> los seeders',
                2 => '<fg=green>Ejecutar todos</> (fresh - resetea DB)',
                3 => '<fg=cyan>Crear nuevo</> seeder',
            ];
            
            $seederIndex = 4;
            foreach ($seeders as $seeder) {
                $class = basename($seeder, '.php');
                $choices[$seederIndex] = "<fg=yellow>Ejecutar:</> {$class}";
                $seederIndex++;
            }
            
            $choices[0] = '<fg=yellow>Volver</>';
            
            $question = new ChoiceQuestion('<fg=cyan>Selecciona una opción:</>', $choices, 0);
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
                    $this->runSeed($output, $wpcli, false);
                    break;
                case 2:
                    $this->runSeed($output, $wpcli, true);
                    break;
                case 3:
                    $this->createSeeder($input, $output, $seedersPath);
                    break;
                default:
                    if ($index >= 4) {
                        $seederFile = $seeders[$index - 4];
                        $class = basename($seederFile, '.php');
                        $this->runSeederClass($output, $wpcli, $class);
                    }
                    break;
            }
            
            $output->writeln('');
        }
        
        return Command::SUCCESS;
    }
    
    private function runSeed(OutputInterface $output, WpCliService $wpcli, bool $fresh): int
    {
        $command = 'seed';
        if ($fresh) {
            $command .= ' --fresh';
        }
        
        $process = $wpcli->custom($command);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
    
    private function runSeederClass(OutputInterface $output, WpCliService $wpcli, string $class): int
    {
        $seedersPath = getcwd() . '/database/seeders';
        $file = $seedersPath . '/' . $class . '.php';
        
        if (!file_exists($file)) {
            $output->writeln("<error>Seeder no encontrado: {$class}</error>");
            return Command::FAILURE;
        }
        
        $output->writeln("<info>🌱 Ejecutando: {$class}</info>");
        
        $dockerPath = str_replace(getcwd(), '/var/www/html', $file);
        $dockerPath = str_replace('\\', '/', $dockerPath);
        
        $process = $wpcli->custom("eval-file {$dockerPath}");
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln("<info>✓ {$class} completado</info>");
            return Command::SUCCESS;
        }
        
        $output->writeln("<error>✗ Error en {$class}</error>");
        return Command::FAILURE;
    }
    
    private function createSeeder(InputInterface $input, OutputInterface $output, string $path): int
    {
        $helper = $this->getHelper('question');
        $question = new Question('<fg=yellow>Nombre del seeder (sin .php):</> ');
        $name = $helper->ask($input, $output, $question);
        
        if (!$name) {
            $output->writeln('<error>Nombre inválido</error>');
            return Command::FAILURE;
        }
        
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }
        
        $file = $path . '/' . $name . '.php';
        
        if (file_exists($file)) {
            $output->writeln("<error>El seeder {$name} ya existe</error>");
            return Command::FAILURE;
        }
        
        $template = <<<'PHP'
<?php
/**
 * {NAME}
 */

WP_CLI::line('🌱 Ejecutando {NAME}...');

// Tu código aquí
// Ejemplo:
// $user = wp_insert_user([
//     'user_login' => 'test',
//     'user_pass' => 'test123',
//     'user_email' => 'test@example.com',
//     'role' => 'subscriber'
// ]);

WP_CLI::success('✓ {NAME} completado');

PHP;
        
        $content = str_replace('{NAME}', $name, $template);
        file_put_contents($file, $content);
        
        $output->writeln("<info>✓ Seeder creado: {$file}</info>");
        $output->writeln('<comment>Edita el archivo para agregar tu lógica</comment>');
        
        return Command::SUCCESS;
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
