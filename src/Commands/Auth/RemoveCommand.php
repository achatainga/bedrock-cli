<?php

namespace Roots\BedrockCli\Commands\Auth;

use Roots\BedrockCli\Services\AuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:remove')
            ->setDescription('Eliminar credenciales configuradas')
            ->addArgument('domain', InputArgument::OPTIONAL, 'Dominio a eliminar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $authService = new AuthService();
        $auths = $authService->listAuth();

        if (empty($auths)) {
            $output->writeln('<comment>No hay credenciales configuradas</comment>');
            return Command::SUCCESS;
        }

        $domain = $input->getArgument('domain');
        
        if (!$domain) {
            $choices = [];
            foreach ($auths as $auth) {
                $choices[] = "{$auth['type']}:{$auth['domain']}";
            }
            
            $question = new ChoiceQuestion(
                '<fg=yellow>Seleccionar credencial a eliminar:</> ',
                $choices
            );
            $selected = $helper->ask($input, $output, $question);
            
            list($type, $domain) = explode(':', $selected, 2);
        } else {
            $found = false;
            foreach ($auths as $auth) {
                if ($auth['domain'] === $domain) {
                    $type = $auth['type'];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $output->writeln("<error>No se encontró credencial para: {$domain}</error>");
                return Command::FAILURE;
            }
        }

        $confirmQuestion = new ConfirmationQuestion(
            "<fg=yellow>¿Eliminar credencial para {$domain}? (y/N):</> ",
            false
        );
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }

        try {
            $authService->removeAuth($type, $domain);
            $output->writeln('');
            $output->writeln("<info>✓ Credencial eliminada para {$domain}</info>");
            $output->writeln('');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
