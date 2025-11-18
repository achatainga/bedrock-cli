<?php

namespace Roots\BedrockCli\Commands\Auth;

use Roots\BedrockCli\Services\AuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ListCommand extends Command
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('auth:list')
            ->setDescription('Listar credenciales configuradas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $auths = $this->authService->listAuth();

        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🔐 CREDENCIALES CONFIGURADAS     <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        if (empty($auths)) {
            $output->writeln('<comment>No hay credenciales configuradas</comment>');
            $output->writeln('');
            $output->writeln('<info>Usa:</info> bedrock auth:add');
            $output->writeln('');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Tipo', 'Dominio', 'Estado']);

        foreach ($auths as $auth) {
            $table->addRow([
                $auth['type'],
                $auth['domain'],
                '<fg=green>✓ Configurado</>'
            ]);
        }

        $table->render();
        $output->writeln('');
        $output->writeln('<comment>Ubicación:</comment> ' . $this->authService->getAuthFile());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
