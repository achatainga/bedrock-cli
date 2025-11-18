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
                ['gitlab', 'github', 'bitbucket', 'http-basic'],
                0
            );
            $type = $helper->ask($input, $output, $typeQuestion);
        }

        $defaultDomain = match($type) {
            'gitlab' => 'gitlab.com',
            'github' => 'github.com',
            'bitbucket' => 'bitbucket.org',
            default => ''
        };
        $domainQuestion = new Question(
            "<fg=yellow>Dominio [{$defaultDomain}]:</> ",
            $defaultDomain
        );
        $domain = $helper->ask($input, $output, $domainQuestion);

        $credentials = [];

        if ($type === 'http-basic') {
            $usernameQuestion = new Question('<fg=yellow>Usuario:</> ');
            $usernameQuestion->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('El usuario no puede estar vacío');
                }
                return trim($value);
            });
            $usernameQuestion->setMaxAttempts(3);
            $credentials['username'] = $helper->ask($input, $output, $usernameQuestion);

            $passwordQuestion = new Question('<fg=yellow>Contraseña:</> ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $passwordQuestion->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('La contraseña no puede estar vacía');
                }
                return trim($value);
            });
            $passwordQuestion->setMaxAttempts(3);
            $credentials['password'] = $helper->ask($input, $output, $passwordQuestion);
        } else {
            $output->writeln('');
            $output->writeln('<fg=cyan>═══════════════════════════════════════════════════════════════</>');
            $output->writeln('<fg=yellow>  📋 CÓMO GENERAR UN TOKEN DE ACCESO PERSONAL</>');
            $output->writeln('<fg=cyan>═══════════════════════════════════════════════════════════════</>');
            $output->writeln('');
            
            match($type) {
                'gitlab' => $this->showGitLabInstructions($output),
                'github' => $this->showGitHubInstructions($output),
                'bitbucket' => $this->showBitbucketInstructions($output),
            };
            
            $output->writeln('');
            $output->writeln('<fg=cyan>═══════════════════════════════════════════════════════════════</>');
            $output->writeln('');

            $tokenQuestion = new Question('<fg=yellow>Pega tu token aquí:</> ');
            $tokenQuestion->setHidden(true);
            $tokenQuestion->setHiddenFallback(false);
            $tokenQuestion->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('El token no puede estar vacío');
                }
                return trim($value);
            });
            $tokenQuestion->setMaxAttempts(3);
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

    private function showGitLabInstructions(OutputInterface $output): void
    {
        $output->writeln('<fg=white>  1. Abre este enlace en tu navegador:</>');
        $output->writeln('<fg=green>     → https://gitlab.com/-/user_settings/personal_access_tokens</>');
        $output->writeln('');
        $output->writeln('<fg=white>  2. Haz clic en "Add new token"</>');
        $output->writeln('');
        $output->writeln('<fg=white>  3. Configura el token:</>');
        $output->writeln('     • Token name: <comment>bedrock-cli</comment>');
        $output->writeln('     • Expiration: <comment>No expiration</comment> (o fecha futura)');
        $output->writeln('     • Scopes: <comment>✓ api</comment> y <comment>✓ read_repository</comment>');
        $output->writeln('');
        $output->writeln('<fg=white>  4. Haz clic en "Create personal access token"</>');
        $output->writeln('');
        $output->writeln('<fg=white>  5. Copia el token generado (empieza con glpat-...)</>');
    }

    private function showGitHubInstructions(OutputInterface $output): void
    {
        $output->writeln('<fg=white>  1. Abre este enlace en tu navegador:</>');
        $output->writeln('<fg=green>     → https://github.com/settings/tokens/new</>');
        $output->writeln('');
        $output->writeln('<fg=white>  2. Configura el token:</>');
        $output->writeln('     • Note: <comment>bedrock-cli</comment>');
        $output->writeln('     • Expiration: <comment>No expiration</comment> (o fecha futura)');
        $output->writeln('     • Scopes: <comment>✓ repo</comment> (acceso completo a repositorios)');
        $output->writeln('');
        $output->writeln('<fg=white>  3. Haz clic en "Generate token" al final de la página</>');
        $output->writeln('');
        $output->writeln('<fg=white>  4. Copia el token generado (empieza con ghp_...)</>');
    }

    private function showBitbucketInstructions(OutputInterface $output): void
    {
        $output->writeln('<fg=white>  1. Abre este enlace en tu navegador:</>');
        $output->writeln('<fg=green>     → https://bitbucket.org/account/settings/app-passwords/</>');
        $output->writeln('');
        $output->writeln('<fg=white>  2. Haz clic en "Create app password"</>');
        $output->writeln('');
        $output->writeln('<fg=white>  3. Configura el password:</>');
        $output->writeln('     • Label: <comment>bedrock-cli</comment>');
        $output->writeln('     • Permissions: <comment>✓ Repositories (Read)</comment>');
        $output->writeln('');
        $output->writeln('<fg=white>  4. Haz clic en "Create"</>');
        $output->writeln('');
        $output->writeln('<fg=white>  5. Copia el password generado</>');
    }
}
