<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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
        
        $url = $helper->ask($input, $output, new Question('URL del sitio [http://127.0.0.1:8024]: ', 'http://127.0.0.1:8024'));
        $title = $helper->ask($input, $output, new Question('Título del sitio [Detodo24]: ', 'Detodo24'));
        $user = $helper->ask($input, $output, new Question('Usuario admin [detodo24]: ', 'detodo24'));
        $pass = $helper->ask($input, $output, new Question('Contraseña [detodo24]: ', 'detodo24'));
        $email = $helper->ask($input, $output, new Question('Email [detodo24@detodo24.com]: ', 'detodo24@detodo24.com'));

        $docker = new DockerService();
        $wpcli = new WpCliService($docker);

        $output->writeln('<info>Instalando WordPress...</info>');
        $process = $wpcli->coreInstall([
            'url' => $url,
            'title' => $title,
            'admin_user' => $user,
            'admin_password' => $pass,
            'admin_email' => $email
        ]);

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ WordPress instalado</info>');
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
