<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

class DoctorCommand extends Command
{
    private string $os;

    protected function configure(): void
    {
        $this
            ->setName('doctor')
            ->setDescription('Verificar e instalar dependencias del sistema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK DOCTOR - System Check  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        // Detectar OS
        $this->detectOS($output);

        if ($this->os === 'Windows') {
            return $this->windowsSetup($input, $output);
        } elseif ($this->os === 'Linux') {
            return $this->linuxSetup($input, $output);
        } elseif ($this->os === 'Mac') {
            return $this->macSetup($input, $output);
        }

        $output->writeln('<fg=red>Sistema operativo no soportado</>');
        return Command::FAILURE;
    }

    private function detectOS(OutputInterface $output): void
    {
        $uname = php_uname('s');
        
        if (stripos($uname, 'Windows') !== false || DIRECTORY_SEPARATOR === '\\') {
            $this->os = 'Windows';
        } elseif (stripos($uname, 'Darwin') !== false) {
            $this->os = 'Mac';
        } elseif (stripos($uname, 'Linux') !== false) {
            $this->os = 'Linux';
        } else {
            $this->os = 'Unknown';
        }

        $output->writeln("<fg=green>Sistema detectado: {$this->os}</>");
        $output->writeln('');
    }

    private function windowsSetup(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // 1. Verificar/Instalar Chocolatey
        $output->writeln('<fg=cyan>═══ Paso 1: Chocolatey ═══</>');
        if (!$this->checkChocolatey($output)) {
            $question = new ConfirmationQuestion('<fg=yellow>¿Instalar Chocolatey? [s/n] </>', false, '/^(s|si|y|yes)/i');
            if ($helper->ask($input, $output, $question)) {
                $this->installChocolatey($output);
            } else {
                $output->writeln('<fg=red>Chocolatey es necesario para continuar</>');
                return Command::FAILURE;
            }
        }
        $output->writeln('');

        // 2. Verificar/Instalar WSL2
        $output->writeln('<fg=cyan>═══ Paso 2: WSL2 ═══</>');
        if (!$this->checkWSL($output)) {
            $question = new ConfirmationQuestion('<fg=yellow>¿Instalar WSL2? (requiere reinicio) [s/n] </>', false, '/^(s|si|y|yes)/i');
            if ($helper->ask($input, $output, $question)) {
                $this->installWSL($output);
                
                $output->writeln('');
                $output->writeln('<fg=red;options=bold>⚠️  REINICIO REQUERIDO ⚠️</>');
                $output->writeln('<fg=yellow>Debes reiniciar tu sistema para completar la instalación de WSL2.</>');
                $output->writeln('<fg=yellow>Después de reiniciar, ejecuta: bedrock doctor</>');
                return Command::SUCCESS;
            }
        }
        $output->writeln('');

        // 3. Verificar/Instalar Ubuntu en WSL
        $output->writeln('<fg=cyan>═══ Paso 3: Ubuntu en WSL ═══</>');
        if (!$this->checkUbuntu($output)) {
            $question = new ConfirmationQuestion('<fg=yellow>¿Instalar Ubuntu en WSL? [s/n] </>', false, '/^(s|si|y|yes)/i');
            if ($helper->ask($input, $output, $question)) {
                $this->installUbuntu($output);
            }
        }
        $output->writeln('');

        // 4. Verificar/Instalar Docker Desktop
        $output->writeln('<fg=cyan>═══ Paso 4: Docker Desktop ═══</>');
        if (!$this->checkDockerDesktop($output)) {
            $question = new ConfirmationQuestion('<fg=yellow>¿Instalar Docker Desktop? [s/n] </>', false, '/^(s|si|y|yes)/i');
            if ($helper->ask($input, $output, $question)) {
                $this->installDockerDesktop($output);
            }
        }
        $output->writeln('');

        // 5. Verificar Docker corriendo
        $output->writeln('<fg=cyan>═══ Paso 5: Docker en ejecución ═══</>');
        $this->checkDockerRunning($output);
        $output->writeln('');

        $output->writeln('<fg=green;options=bold>✓ Verificación completada</>');
        return Command::SUCCESS;
    }

    private function linuxSetup(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=cyan>═══ Verificando Docker en Linux ═══</>');
        
        $process = new Process(['which', 'docker']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('<fg=yellow>Docker no está instalado</>');
            $output->writeln('<fg=cyan>Instala Docker con:</>');
            $output->writeln('  sudo apt-get update');
            $output->writeln('  sudo apt-get install -y docker.io docker-compose');
            $output->writeln('  sudo usermod -aG docker $USER');
            return Command::FAILURE;
        }

        $output->writeln('<fg=green>✓ Docker instalado</>');
        return Command::SUCCESS;
    }

    private function macSetup(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=cyan>═══ Verificando Docker en Mac ═══</>');
        
        if (!file_exists('/Applications/Docker.app')) {
            $output->writeln('<fg=yellow>Docker Desktop no está instalado</>');
            $output->writeln('<fg=cyan>Descarga desde: https://www.docker.com/products/docker-desktop</>');
            return Command::FAILURE;
        }

        $output->writeln('<fg=green>✓ Docker Desktop instalado</>');
        return Command::SUCCESS;
    }

    private function checkChocolatey(OutputInterface $output): bool
    {
        $process = new Process(['choco', '--version']);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Chocolatey instalado</>');
            return true;
        }
        
        $output->writeln('<fg=yellow>✗ Chocolatey no instalado</>');
        return false;
    }

    private function installChocolatey(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>Instalando Chocolatey...</>');
        
        $cmd = 'powershell -Command "Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString(\'https://community.chocolatey.org/install.ps1\'))"';
        
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Chocolatey instalado</>');
        } else {
            $output->writeln('<fg=red>✗ Error instalando Chocolatey</>');
        }
    }

    private function checkWSL(OutputInterface $output): bool
    {
        $process = Process::fromShellCommandline('powershell -Command "wsl --status"');
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ WSL instalado</>');
            return true;
        }
        
        $output->writeln('<fg=yellow>✗ WSL no instalado</>');
        return false;
    }

    private function installWSL(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>Habilitando características de Windows para WSL2...</>');
        
        $commands = [
            'dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart',
            'dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart',
            'wsl --set-default-version 2'
        ];
        
        foreach ($commands as $cmd) {
            $process = Process::fromShellCommandline('powershell -Command "Start-Process ' . $cmd . ' -Verb RunAs -Wait"');
            $process->setTimeout(300);
            $process->run();
        }
        
        $output->writeln('<fg=green>✓ WSL2 habilitado</>');
    }

    private function checkUbuntu(OutputInterface $output): bool
    {
        $process = Process::fromShellCommandline('powershell -Command "try { $output = wsl --list; if ($output -like \"*Ubuntu*\") { exit 0 } else { exit 1 } } catch { exit 1 }"');
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Ubuntu instalado en WSL</>');
            return true;
        }
        
        $output->writeln('<fg=yellow>✗ Ubuntu no instalado en WSL</>');
        return false;
    }

    private function installUbuntu(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>Instalando Ubuntu en WSL...</>');
        
        $process = Process::fromShellCommandline('powershell -Command "wsl --install -d Ubuntu"');
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Ubuntu instalado</>');
        } else {
            $output->writeln('<fg=red>✗ Error instalando Ubuntu</>');
        }
    }

    private function checkDockerDesktop(OutputInterface $output): bool
    {
        $dockerPath = 'C:\\Program Files\\Docker\\Docker\\Docker Desktop.exe';
        
        if (file_exists($dockerPath)) {
            $output->writeln('<fg=green>✓ Docker Desktop instalado</>');
            return true;
        }
        
        $output->writeln('<fg=yellow>✗ Docker Desktop no instalado</>');
        return false;
    }

    private function installDockerDesktop(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>Instalando Docker Desktop con Chocolatey...</>');
        
        $process = Process::fromShellCommandline('choco install docker-desktop -y');
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Docker Desktop instalado</>');
            $output->writeln('<fg=yellow>Nota: Puede que necesites reiniciar tu sistema</>');
        } else {
            $output->writeln('<fg=red>✗ Error instalando Docker Desktop</>');
        }
    }

    private function checkDockerRunning(OutputInterface $output): void
    {
        $process = Process::fromShellCommandline('docker info');
        $process->run();
        
        if ($process->isSuccessful()) {
            $output->writeln('<fg=green>✓ Docker está corriendo</>');
            return;
        }
        
        $output->writeln('<fg=yellow>✗ Docker no está corriendo</>');
        $output->writeln('<fg=cyan>Iniciando Docker Desktop...</>');
        
        $dockerPath = 'C:\\Program Files\\Docker\\Docker\\Docker Desktop.exe';
        if (file_exists($dockerPath)) {
            $process = Process::fromShellCommandline('start "" "' . $dockerPath . '"');
            $process->run();
            
            $output->writeln('<fg=yellow>Esperando 60 segundos a que Docker se inicialice...</>');
            sleep(60);
            
            $process = Process::fromShellCommandline('docker info');
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln('<fg=green>✓ Docker iniciado correctamente</>');
            } else {
                $output->writeln('<fg=red>✗ Docker no se inició. Inicia Docker Desktop manualmente</>');
            }
        }
    }
}
