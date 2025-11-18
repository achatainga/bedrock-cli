<?php

namespace Roots\BedrockCli\Commands;

use Roots\BedrockCli\Traits\SpinnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class UpdateCommand extends Command
{
    use SpinnerTrait;
    
    private DockerService $dockerService;
    private WpCliService $wpCliService;
    
    public function __construct(
        DockerService $dockerService,
        WpCliService $wpCliService
    ) {
        $this->dockerService = $dockerService;
        $this->wpCliService = $wpCliService;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Actualizar sistema completo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $docker = $this->dockerService;
        $wpcli = $this->wpCliService;
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>===== ACTUALIZACIÓN DEL SISTEMA =====</>');
        $output->writeln('');
        
        $process = $wpcli->coreUpdate();
        $this->runWithLoader($process, $output, 'Actualizando WordPress core');
        
        $output->writeln('');
        $process = $wpcli->pluginUpdate();
        $this->runWithLoader($process, $output, 'Actualizando plugins');
        
        $output->writeln('');
        $process = $wpcli->themeUpdate();
        $this->runWithLoader($process, $output, 'Actualizando temas');
        
        $output->writeln('');
        $output->writeln('<info>✓ Sistema actualizado completamente</info>');
        $output->writeln('');
        
        return Command::SUCCESS;
    }


}
