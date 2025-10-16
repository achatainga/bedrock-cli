<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Actualizar sistema completo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>===== ACTUALIZACIÓN DEL SISTEMA =====</>');
        $output->writeln('');
        
        $output->writeln('<info>Actualizando WordPress core...</info>');
        $process = $wpcli->coreUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        $output->writeln('');
        $output->writeln('<info>Actualizando plugins...</info>');
        $process = $wpcli->pluginUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        $output->writeln('');
        $output->writeln('<info>Actualizando temas...</info>');
        $process = $wpcli->themeUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        $output->writeln('');
        $output->writeln('<info>✓ Sistema actualizado completamente</info>');
        $output->writeln('');
        
        return Command::SUCCESS;
    }
}
