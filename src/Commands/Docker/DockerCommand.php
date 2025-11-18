<?php

namespace Roots\BedrockCli\Commands\Docker;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class DockerCommand extends Command
{
    use ProjectSelectorTrait;
    use SpinnerTrait;
    protected function configure(): void
    {
        $this
            ->setName('docker')
            ->setDescription('Gestión de contenedores Docker')

            ->addOption('up', null, InputOption::VALUE_NONE, 'Levantar contenedores')
            ->addOption('down', null, InputOption::VALUE_NONE, 'Bajar contenedores')
            ->addOption('restart', null, InputOption::VALUE_NONE, 'Reiniciar contenedores')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Ver estado')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Rebuild al levantar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }

        $docker = new DockerService();
        
        // Verificar si Docker está corriendo
        if (!$docker->isRunning()) {
            $output->writeln('');
            $output->writeln('<fg=yellow>Docker Desktop no está en ejecución.</>');
            $output->writeln('<fg=cyan>Ejecutando bedrock doctor para verificar/iniciar Docker...</>');
            $output->writeln('');
            
            // Ejecutar comando doctor con input limpio
            $doctorCommand = $this->getApplication()->find('doctor');
            $doctorInput = new \Symfony\Component\Console\Input\ArrayInput([]);
            $returnCode = $doctorCommand->run($doctorInput, $output);
            
            if ($returnCode !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            
            // Verificar nuevamente si Docker está corriendo
            if (!$docker->isRunning()) {
                $output->writeln('');
                $output->writeln('<fg=red>Docker aún no está disponible después de ejecutar doctor.</>');
                $output->writeln('<fg=yellow>Por favor, revisa los mensajes anteriores y soluciona los problemas.</>');
                return Command::FAILURE;
            }
            
            $output->writeln('');
            $output->writeln('<fg=green>✓ Docker está listo. Continuando...</>');
            $output->writeln('');
        }

        // Comandos directos
        if ($input->getOption('up')) {
            return $this->up($docker, $output, $input->getOption('build'));
        }
        if ($input->getOption('down')) {
            return $this->down($docker, $output);
        }
        if ($input->getOption('restart')) {
            return $this->restart($docker, $output);
        }
        if ($input->getOption('status')) {
            return $this->status($docker, $output);
        }

        // Menú interactivo
        return $this->showMenu($input, $output, $docker);
    }

    private function showMenu(InputInterface $input, OutputInterface $output, DockerService $docker): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║</>   🐳 DOCKER - Contenedores        <fg=cyan>║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            $output->writeln('<comment>Gestiona contenedores Docker del proyecto.</comment>');
            $output->writeln('');
            
            $output->writeln(' <fg=cyan>[1]</> ▶️  Levantar contenedores');
            $output->writeln(' <fg=cyan>[2]</> ⏹️  Bajar contenedores');
            $output->writeln(' <fg=cyan>[3]</> 🔄 Reiniciar contenedores');
            $output->writeln(' <fg=cyan>[4]</> 🛠️  Reconstruir (sin caché)');
            $output->writeln(' <fg=cyan>[5]</> 🛠️  Reconstruir (con caché)');
            $output->writeln(' <fg=cyan>[6]</> 📊 Ver estado');
            $output->writeln(' <fg=cyan>[7]</> 📜 Ver logs');
            $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
            $output->writeln('');
            
            $question = new Question('<fg=yellow>Opción [0-7]: </>', '0');
            $index = $helper->ask($input, $output, $question);
            
            if (!is_numeric($index) || $index < 0 || $index > 7) {
                $output->writeln('<error>Opción inválida</error>');
                continue;
            }
            
            if ($index === '0') {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case '1':
                    $this->up($docker, $output, false);
                    break;
                case '2':
                    $this->down($docker, $output);
                    break;
                case '3':
                    $this->restart($docker, $output);
                    break;
                case '4':
                    $this->rebuild($docker, $output, false);
                    break;
                case '5':
                    $this->rebuild($docker, $output, true);
                    break;
                case '6':
                    $this->status($docker, $output);
                    break;
                case '7':
                    $this->logs($docker, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function up(DockerService $docker, OutputInterface $output, bool $build): int
    {
        $output->writeln('<info>Levantando contenedores...</info>');
        $cmd = $build ? 'docker-compose up -d --build' : 'docker-compose up -d';
        passthru($cmd, $exitCode);
        
        if ($exitCode === 0) {
            $output->writeln('<info>✓ Contenedores levantados</info>');
            $this->markStepCompleted(1);
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al levantar contenedores</error>');
        return Command::FAILURE;
    }

    private function down(DockerService $docker, OutputInterface $output): int
    {
        $process = $docker->down();
        $this->runWithLoader($process, $output, 'Deteniendo contenedores Docker');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores detenidos</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function restart(DockerService $docker, OutputInterface $output): int
    {
        $process = $docker->restart();
        $this->runWithLoader($process, $output, 'Reiniciando contenedores Docker');
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Contenedores reiniciados</info>');
            return Command::SUCCESS;
        }
        
        return Command::FAILURE;
    }

    private function status(DockerService $docker, OutputInterface $output): int
    {
        $process = $docker->status();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    private function rebuild(DockerService $docker, OutputInterface $output, bool $useCache = false): int
    {
        if ($useCache) {
            $output->writeln('<info>Reconstruyendo contenedores con caché...</info>');
            passthru('docker-compose build --progress=plain web', $exitCode);
        } else {
            $output->writeln('<info>Reconstruyendo contenedores sin caché...</info>');
            passthru('docker-compose build --no-cache --progress=plain web', $exitCode);
        }
        
        if ($exitCode !== 0) {
            $output->writeln('<error>✗ Error al reconstruir contenedores</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('<info>Build completado. Levantando contenedores...</info>');
        
        // Paso 2: Up
        passthru('docker-compose up -d', $exitCode);
        
        if ($exitCode === 0) {
            $output->writeln('<info>✓ Contenedores reconstruidos y levantados</info>');
            return Command::SUCCESS;
        }
        
        $output->writeln('<error>✗ Error al levantar contenedores</error>');
        return Command::FAILURE;
    }

    private function logs(DockerService $docker, OutputInterface $output): int
    {
        $output->writeln('<info>Mostrando últimas 100 líneas de logs...</info>');
        $output->writeln('<fg=yellow>Tip: Para seguir logs en tiempo real usa: docker-compose logs -f</>');
        $output->writeln('');
        
        $process = $docker->logs(false);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        return Command::SUCCESS;
    }

    private function startDockerDesktop(OutputInterface $output): bool
    {
        // Verificar si Docker Desktop está instalado
        $dockerPath = 'C:\\Program Files\\Docker\\Docker\\Docker Desktop.exe';
        
        if (!file_exists($dockerPath)) {
            $output->writeln('');
            $output->writeln('<fg=red>Docker Desktop no está instalado.</>');            $output->writeln('<fg=yellow>Descarga e instala desde: https://www.docker.com/products/docker-desktop</>');
            $output->writeln('');
            return false;
        }
        
        // Iniciar Docker Desktop
        $output->writeln('<fg=cyan>Iniciando Docker Desktop...</>');
        
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            $process = Process::fromShellCommandline('start "" "' . $dockerPath . '"');
        } else {
            // Mac/Linux
            $process = new Process(['open', '-a', 'Docker']);
        }
        
        $process->run();
        
        // Esperar a que Docker se inicialice
        $output->writeln('<fg=yellow>Esperando a que Docker Desktop se inicialice (esto puede tardar 60 segundos)...</>');
        $output->writeln('<fg=cyan>Este proceso puede tardar más tiempo en la primera ejecución.</>');
        
        $maxAttempts = 12; // 60 segundos (12 * 5)
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(5);
            $attempt++;
            
            $docker = new DockerService();
            if ($docker->isRunning()) {
                $output->writeln('<fg=green>✓ Docker Desktop está funcionando correctamente</>');
                $output->writeln('');
                return true;
            }
            
            $output->write('.');
        }
        
        $output->writeln('');
        $output->writeln('');
        $output->writeln('<fg=red>Docker Desktop no se inició correctamente después de 60 segundos.</>');
        $output->writeln('<fg=yellow>Posibles soluciones:</>');
        $output->writeln('  1. Verifica que Docker Desktop se esté iniciando (icono en bandeja del sistema)');
        $output->writeln('  2. Espera un poco más y vuelve a ejecutar el comando');
        $output->writeln('  3. Reinicia tu sistema si acabas de instalar Docker');
        $output->writeln('  4. Verifica que WSL2 esté configurado correctamente (Windows)');
        $output->writeln('');
        
        return false;
    }



    private function markStepCompleted(int $stepId): void
    {
        $stateService = new StateService();
        $stateService->markCompleted(getcwd(), $stepId);
    }
}
