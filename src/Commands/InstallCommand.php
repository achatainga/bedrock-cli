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
            ->setDescription('Instalar WordPress core');
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
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln('<comment>Instalación cancelada</comment>');
            return Command::SUCCESS;
        }
        
        $url = $helper->ask($input, $output, new Question('<fg=yellow>URL del sitio</> [http://127.0.0.1:8024]: ', 'http://127.0.0.1:8024'));
        $title = $helper->ask($input, $output, new Question('<fg=yellow>Título del sitio</> [Detodo24]: ', 'Detodo24'));
        $user = $helper->ask($input, $output, new Question('<fg=yellow>Usuario admin</> [detodo24]: ', 'detodo24'));
        $pass = $helper->ask($input, $output, new Question('<fg=yellow>Contraseña</> [detodo24]: ', 'detodo24'));
        $email = $helper->ask($input, $output, new Question('<fg=yellow>Email</> [detodo24@detodo24.com]: ', 'detodo24@detodo24.com'));

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
            return Command::SUCCESS;
        }

        $output->writeln('<error>✗ Error al instalar WordPress</error>');
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
