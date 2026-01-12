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
        $wpcli = $this->wpCliService;
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</>   🔄 ACTUALIZACIÓN TOTAL DEL SISTEMA  <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<comment>Iniciando proceso de actualización de Core, Plugins y Themes...</comment>');
        $output->writeln('');
        
        $process = $wpcli->coreUpdate();
        $this->runWithLoader($process, $output, 'WordPress Core');
        
        $process = $wpcli->pluginUpdate();
        $this->runWithLoader($process, $output, 'Plugins (Todos)');
        
        $process = $wpcli->themeUpdate();
        $this->runWithLoader($process, $output, 'Temas (Todos)');
        
        $output->writeln('');
        $output->writeln('<info>✅ PROCESO FINALIZADO:</info>');
        $output->writeln(' Todos los componentes han sido procesados y actualizados.');
        $output->writeln('');
        
        return Command::SUCCESS;
    }


}
