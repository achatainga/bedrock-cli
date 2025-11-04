<?php

namespace Roots\BedrockCli\Commands\Auth;

use Roots\BedrockCli\Services\AuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:menu')
            ->setDescription('Menú de gestión de autenticación');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $authService = new AuthService();

        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║</>   🔐 AUTENTICACIÓN - Repos Privados <fg=cyan>║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $auths = $authService->listAuth();

            if (empty($auths)) {
                $output->writeln('<comment>No hay credenciales configuradas</comment>');
            } else {
                $output->writeln('<info>Credenciales configuradas:</info>');
                foreach ($auths as $auth) {
                    $output->writeln("  • {$auth['type']}: {$auth['domain']}");
                }
            }

            $output->writeln('');
            $output->writeln(' <fg=cyan>[1]</> ➕ Agregar credencial');
            $output->writeln(' <fg=cyan>[2]</> 📋 Listar credenciales');
            $output->writeln(' <fg=cyan>[3]</> 🗑️  Eliminar credencial');
            $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
            $output->writeln('');

            $question = new Question('<fg=yellow>Opción [0-3]:</> ', '0');
            $choice = $helper->ask($input, $output, $question);

            if ($choice === '0') {
                return Command::SUCCESS;
            }

            $commandMap = [
                '1' => 'auth:add',
                '2' => 'auth:list',
                '3' => 'auth:remove',
            ];

            if (isset($commandMap[$choice])) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandMap[$choice]);
                $command->run(new ArrayInput([]), $output);
            } else {
                $output->writeln('<error>Opción inválida</error>');
                sleep(1);
            }
        }

        return Command::SUCCESS;
    }
}
