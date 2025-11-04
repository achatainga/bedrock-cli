<?php

namespace Roots\BedrockCli\Commands\Auth;

use Roots\BedrockCli\Services\AuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

class AddCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:add')
            ->setDescription('Agregar credenciales para repositorios privados')
            ->addArgument('type', InputArgument::OPTIONAL, 'Tipo: gitlab, github, http-basic');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $authService = new AuthService();

        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🔐 AGREGAR AUTENTICACIÓN         <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $type = $input->getArgument('type');
        if (!$type) {
            $typeQuestion = new ChoiceQuestion(
                '<fg=yellow>Tipo de autenticación:</> ',
                ['gitlab', 'github', 'http-basic'],
                0
            );
            $type = $helper->ask($input, $output, $typeQuestion);
        }

        $defaultDomain = $type === 'gitlab' ? 'gitlab.com' : ($type === 'github' ? 'github.com' : '');
        $domainQuestion = new Question(
            "<fg=yellow>Dominio [{$defaultDomain}]:</> ",
            $defaultDomain
        );
        $domain = $helper->ask($input, $output, $domainQuestion);

        $credentials = [];

        if ($type === 'http-basic') {
            $usernameQuestion = new Question('<fg=yellow>Usuario:</> ');
            $credentials['username'] = $helper->ask($input, $output, $usernameQuestion);

            $passwordQuestion = new Question('<fg=yellow>Contraseña:</> ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $credentials['password'] = $helper->ask($input, $output, $passwordQuestion);
        } else {
            $output->writeln('');
            $output->writeln('<comment>Para obtener un token de acceso personal:</comment>');
            if ($type === 'gitlab') {
                $output->writeln('  → https://gitlab.com/-/user_settings/personal_access_tokens');
                $output->writeln('  → Scopes: api, read_repository');
            } else {
                $output->writeln('  → https://github.com/settings/tokens');
                $output->writeln('  → Scopes: repo');
            }
            $output->writeln('');

            $tokenQuestion = new Question('<fg=yellow>Token de acceso personal:</> ');
            $tokenQuestion->setHidden(true);
            $tokenQuestion->setHiddenFallback(false);
            $credentials['token'] = $helper->ask($input, $output, $tokenQuestion);
        }

        try {
            $authService->addAuth($type, $domain, $credentials);
            
            $output->writeln('');
            $output->writeln("<info>✓ Credencial agregada para {$domain}</info>");
            $output->writeln('');
            $output->writeln('<comment>Ubicación:</comment> ' . $authService->getAuthFile());
            $output->writeln('');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
