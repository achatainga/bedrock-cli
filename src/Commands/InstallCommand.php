<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install')
            ->setDescription('Instalar WordPress core')
            ->addOption('url', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'URL del sitio')
            ->addOption('title', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Título del sitio')
            ->addOption('admin-user', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Usuario admin')
            ->addOption('admin-password', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Contraseña admin')
            ->addOption('admin-email', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Email admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>===== INSTALACIÓN DE WORDPRESS =====</>');
        $output->writeln('');
        
        $confirmQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Deseas continuar con la instalación de WordPress?</> [s/n] ',
            false,
            '/^(s|si|y|yes)/i'
        );
        
        // Si hay opciones, usarlas directamente
        if ($input->getOption('url')) {
            $url = $input->getOption('url');
            $title = $input->getOption('title') ?? 'Mi Sitio';
            $user = $input->getOption('admin-user') ?? 'admin';
            $pass = $input->getOption('admin-password') ?? 'admin';
            $email = $input->getOption('admin-email') ?? 'admin@example.com';
        } else {
            // Modo interactivo
            if (!$helper->ask($input, $output, $confirmQuestion)) {
                $output->writeln('<comment>Instalación cancelada</comment>');
                return Command::SUCCESS;
            }
            
            // URL con validación
            $url = null;
            while (!$url) {
                $urlInput = $helper->ask($input, $output, new Question('<fg=yellow>URL del sitio</> [http://localhost:8080]: ', 'http://localhost:8080'));
                $url = $this->validateAndFixUrl($urlInput);
                if (!$url) {
                    $output->writeln('<error>URL inválida. Usa formato: http://localhost:8080 o solo el puerto: 8080</error>');
                }
            }
            
            $title = $helper->ask($input, $output, new Question('<fg=yellow>Título del sitio</> [Mi Sitio]: ', 'Mi Sitio'));
            $user = $helper->ask($input, $output, new Question('<fg=yellow>Usuario admin</> [admin]: ', 'admin'));
            $pass = $helper->ask($input, $output, new Question('<fg=yellow>Contraseña</> [admin]: ', 'admin'));
            
            // Email con validación
            $email = null;
            while (!$email) {
                $emailInput = $helper->ask($input, $output, new Question('<fg=yellow>Email</> [admin@example.com]: ', 'admin@example.com'));
                if (filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                    $email = $emailInput;
                } else {
                    $output->writeln('<error>Email inválido. Usa formato: usuario@dominio.com</error>');
                }
            }
        }

        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $output->writeln('');
        $process = $wpcli->coreInstall([
            'url' => $url,
            'title' => $title,
            'admin_user' => $user,
            'admin_password' => $pass,
            'admin_email' => $email
        ]);

        $this->runWithLoader($process, $output, 'Instalando WordPress');

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ WordPress instalado exitosamente</info>');
            $output->writeln('');
            $output->writeln('<fg=cyan>Acceso:</>');
            $output->writeln("  URL: <fg=white>{$url}</>");
            $output->writeln("  Admin: <fg=white>{$url}/wp/wp-admin</>");
            $output->writeln("  Usuario: <fg=white;options=bold>{$user}</>");
            $output->writeln("  Contraseña: <fg=white;options=bold>{$pass}</>");
            $output->writeln('');
            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Error al instalar WordPress</error>');
        $output->writeln('');
        $output->writeln('<fg=yellow>Output del comando:</>');
        $output->writeln($process->getOutput());
        if ($process->getErrorOutput()) {
            $output->writeln('<fg=yellow>Errores:</>');
            $output->writeln($process->getErrorOutput());
        }
        $output->writeln('');
        return Command::FAILURE;
    }

    private function validateAndFixUrl(string $input): ?string
    {
        $input = trim($input);
        
        // Si está vacío, retornar null
        if (empty($input)) {
            return null;
        }
        
        // Si es solo número, asumir localhost con ese puerto
        if (preg_match('/^\d+$/', $input)) {
            $port = (int)$input;
            if ($port < 1 || $port > 65535) {
                return null; // Puerto inválido
            }
            return "http://localhost:{$port}";
        }
        
        // Si no tiene protocolo, agregar http://
        if (!preg_match('/^https?:\/\//', $input)) {
            $input = "http://{$input}";
        }
        
        // Validar que sea una URL válida
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }
        
        return null;
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
