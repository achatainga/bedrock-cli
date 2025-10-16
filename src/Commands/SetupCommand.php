<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('setup')
            ->setDescription('Configuración inicial del proyecto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  CONFIGURACIÓN INICIAL - BEDROCK  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $confirmQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Deseas continuar con la configuración inicial?</> [s/n] ',
            false,
            '/^(s|si|y|yes)/i'
        );
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln('<comment>Configuración cancelada</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln('');

        // Configuración de acceso
        $output->writeln('<fg=green;options=bold>--- Configuración de Acceso ---</>');
        $host = $helper->ask($input, $output, new Question('<fg=yellow>HOST</> [127.0.0.1]: ', '127.0.0.1'));
        $port = $helper->ask($input, $output, new Question('<fg=yellow>PUERTO</> [8024]: ', '8024'));
        $output->writeln("<comment>URL: http://{$host}:{$port}</comment>");
        $output->writeln('');

        // Configuración de base de datos
        $output->writeln('<fg=green;options=bold>--- Configuración de Base de Datos ---</>');
        $dbName = $helper->ask($input, $output, new Question('<fg=yellow>Nombre DB</> [detodo24_bedrock]: ', 'detodo24_bedrock'));
        $dbUser = $helper->ask($input, $output, new Question('<fg=yellow>Usuario DB</> [root]: ', 'root'));
        
        $dbPassQuestion = new Question('<fg=yellow>Contraseña DB</> [mysql]: ', 'mysql');
        $dbPassQuestion->setHidden(true);
        $dbPassword = $helper->ask($input, $output, $dbPassQuestion);
        $output->writeln('');

        // Generar salts
        $output->writeln('<info>Generando salts de seguridad...</info>');
        $salts = $this->generateSalts();
        
        // Crear archivo .env
        $output->writeln('<info>Creando archivo .env...</info>');
        $envContent = $this->buildEnvContent($host, $port, $dbName, $dbUser, $dbPassword, $salts);
        
        $envPath = getcwd() . '/.env';
        file_put_contents($envPath, $envContent);
        
        $output->writeln('');
        $output->writeln('<info>✓ Archivo .env creado exitosamente</info>');
        $output->writeln('');
        $output->writeln("<comment>Accede a: http://{$host}:{$port}</comment>");
        $output->writeln('<comment>Ejecuta: bedrock docker (opción 1) para levantar contenedores</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function generateSalts(): array
    {
        $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 
                 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
        $salts = [];
        
        foreach ($keys as $key) {
            $salts[$key] = $this->generateSalt(64);
        }
        
        return $salts;
    }

    private function generateSalt(int $length = 64): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_+=[]{};:,.<>?/~';
        $charsLength = strlen($chars);
        $salt = '';
        
        for ($i = 0; $i < $length; $i++) {
            $salt .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $salt;
    }

    private function buildEnvContent(string $host, string $port, string $dbName, string $dbUser, string $dbPassword, array $salts): string
    {
        return <<<EOD
# Configuración de la base de datos
DB_NAME='{$dbName}'
DB_USER='{$dbUser}'
DB_PASSWORD='{$dbPassword}'
DB_HOST='mysql'

# Entorno de la aplicación
WP_ENV='development'

# URLs del sitio
WP_HOME='http://{$host}:{$port}'
WP_SITEURL="\${WP_HOME}/wp"

# Salts de seguridad
AUTH_KEY='{$salts['AUTH_KEY']}'
SECURE_AUTH_KEY='{$salts['SECURE_AUTH_KEY']}'
LOGGED_IN_KEY='{$salts['LOGGED_IN_KEY']}'
NONCE_KEY='{$salts['NONCE_KEY']}'
AUTH_SALT='{$salts['AUTH_SALT']}'
SECURE_AUTH_SALT='{$salts['SECURE_AUTH_SALT']}'
LOGGED_IN_SALT='{$salts['LOGGED_IN_SALT']}'
NONCE_SALT='{$salts['NONCE_SALT']}'
EOD;
    }
}
