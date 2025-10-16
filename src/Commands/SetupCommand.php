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
        $output->writeln('<info>Bienvenido al asistente de instalación</info>');
        $output->writeln('');

        // Confirmación
        $confirmQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Deseas continuar con la configuración inicial?</> [s/n] ',
            false,
            '/^(s|si|y|yes)/i'
        );
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln('<comment>Configuración cancelada</comment>');
            return Command::SUCCESS;
        }

        // Configuración de acceso
        $output->writeln('');
        $output->writeln('<info>--- Configuración de Acceso a la Aplicación ---</info>');
        $host = $helper->ask($input, $output, new Question('<question>Introduce el HOST para acceder a la aplicación</question> (default: 127.0.0.1): ', '127.0.0.1'));
        $port = $helper->ask($input, $output, new Question('<question>Introduce el PUERTO para acceder a la aplicación</question> (default: 8024): ', '8024'));
        $output->writeln("<comment>La aplicación será accesible en: http://{$host}:{$port}</comment>");
        $output->writeln('<info>----------------------------------------------</info>');

        // Configuración de base de datos
        $output->writeln('');
        $output->writeln('<info>--- Configuración de la Base de Datos ---</info>');
        $dbName = $helper->ask($input, $output, new Question('<question>Nombre de la base de datos</question> (default: detodo24_bedrock): ', 'detodo24_bedrock'));
        $output->writeln("<comment>Base de datos: {$dbName}</comment>");
        
        $dbUser = $helper->ask($input, $output, new Question('<question>Usuario de la base de datos</question> (default: root): ', 'root'));
        $output->writeln("<comment>Usuario: {$dbUser}</comment>");
        
        $dbPassQuestion = new Question('<question>Contraseña de la base de datos</question> (default: mysql): ', 'mysql');
        $dbPassQuestion->setHidden(true);
        $dbPassword = $helper->ask($input, $output, $dbPassQuestion);
        $output->writeln('<comment>Contraseña ingresada.</comment>');
        $output->writeln('<info>----------------------------------------</info>');

        // Generar salts
        $output->writeln('');
        $output->writeln('<info>Generando salts de seguridad para WordPress...</info>');
        $salts = $this->generateSalts();
        $output->writeln('<info>Salts generados.</info>');

        // Crear archivo .env
        $output->writeln('');
        $output->writeln('<info>Creando o actualizando archivo .env...</info>');
        $envPath = getcwd() . '/.env';
        $envContent = $this->buildEnvContent($host, $port, $dbName, $dbUser, $dbPassword, $salts);
        file_put_contents($envPath, $envContent);
        $output->writeln('<info>Archivo .env generado o actualizado correctamente.</info>');

        // Actualizar docker-compose.yml
        $output->writeln('');
        $output->writeln('<info>Actualizando archivo docker-compose.yml con el puerto de Nginx...</info>');
        $this->updateDockerCompose($port, $output);

        // Actualizar default.conf de Nginx
        $output->writeln('');
        $output->writeln('<info>Actualizando archivo ./docker/nginx/default.conf con el host...</info>');
        $this->updateNginxConf($host, $output);

        // Mensaje final
        $output->writeln('');
        $output->writeln('<info>Configuración de archivos completada.</info>');
        $output->writeln("<info>Ahora puedes ejecutar 'docker-compose up -d' para levantar los contenedores con la nueva configuración.</info>");
        $output->writeln("<info>Accede a la aplicación en: http://{$host}:{$port}</info>");
        $output->writeln('');
        $output->writeln('<info>¡Asistente de instalación finalizado!</info>');
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
# Este archivo .env contiene las variables de entorno para configurar tu aplicación Bedrock.
# Es leído por Bedrock al iniciar.

# Configuración de la base de datos
# Asegúrate de que estos valores coincidan con los definidos para el servicio 'mysql' en tu docker-compose.yml
DB_NAME='{$dbName}'             # Nombre de la base de datos
DB_USER='{$dbUser}'             # Usuario de la base de datos
DB_PASSWORD='{$dbPassword}'     # Contraseña del usuario de la base de datos

# Host de la base de datos.
# Dentro de la red de Docker Compose, el nombre del servicio ('mysql') se resuelve automáticamente
# a la dirección IP del contenedor MySQL.
# ¡Es CRUCIAL que este host sea 'mysql' y no 'localhost' cuando se ejecuta dentro de Docker!
DB_HOST='mysql'                 # <-- Apunta al nombre del servicio MySQL en Docker Compose.

# Prefijo opcional para las tablas de WordPress
# DB_PREFIX='wp_'

# Entorno de la aplicación (development, staging, production)
WP_ENV='development'            # Define el entorno actual de la aplicación.

# URL principal del sitio. Debe ser accesible desde tu navegador.
# Corresponde al mapeo de puertos de Nginx en docker-compose.yml.
WP_HOME='http://{$host}:{$port}' # <-- Utiliza el HOST y PUERTO ingresados por el usuario.
# URL del directorio de WordPress (siempre \${WP_HOME}/wp en Bedrock)
WP_SITEURL="\${WP_HOME}/wp"      # <-- Se construye automáticamente a partir de WP_HOME.

# Especifica la ruta opcional para el archivo debug.log de WordPress.
# WP_DEBUG_LOG='/path/to/debug.log'

# Claves de autenticación y salts de seguridad para WordPress.
# Se generaron automáticamente durante la ejecución de este script.
# Puedes regenerarlas en https://roots.io/salts.html si es necesario.
AUTH_KEY='{$salts['AUTH_KEY']}'
SECURE_AUTH_KEY='{$salts['SECURE_AUTH_KEY']}'
LOGGED_IN_KEY='{$salts['LOGGED_IN_KEY']}'
NONCE_KEY='{$salts['NONCE_KEY']}'
AUTH_SALT='{$salts['AUTH_SALT']}'
SECURE_AUTH_SALT='{$salts['SECURE_AUTH_SALT']}'
LOGGED_IN_SALT='{$salts['LOGGED_IN_SALT']}'
NONCE_SALT='{$salts['NONCE_SALT']}'

# Asegúrate de que las claves y salts anteriores se hayan generado correctamente
# en tu archivo .env local. Si no, puedes generarlas en https://roots.io/salts.html
# y pegarlas aquí.
EOD;
    }

    private function updateDockerCompose(string $port, OutputInterface $output): void
    {
        $dockerComposeFile = getcwd() . '/docker-compose.yml';
        
        if (!file_exists($dockerComposeFile)) {
            $output->writeln('<error>Error: Archivo docker-compose.yml no encontrado.</error>');
            return;
        }

        $lines = file($dockerComposeFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $inNginxService = false;
        $inPortsBlock = false;
        $updatedPorts = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*nginx:\s*.*?$/', $line)) {
                $inNginxService = true;
                $inPortsBlock = false;
                $updatedPorts = false;
            }

            if ($inNginxService && preg_match('/^\s*ports:\s*.*?$/', $line)) {
                $inPortsBlock = true;
            }

            if ($inNginxService && $inPortsBlock && preg_match('/^\s*-\s*"(\d+):80"/', $line, $matches) && !$updatedPorts) {
                $newLine = preg_replace('/^(\s*-\s*")\d+(:80")/', '${1}' . $port . '${2}', $line);
                $newLines[] = $newLine;
                $output->writeln("<comment>Actualizada línea de puertos: '" . rtrim($line) . "' -> '" . rtrim($newLine) . "'</comment>");
                $updatedPorts = true;
            } else {
                $newLines[] = $line;
            }
        }

        if (!$updatedPorts) {
            $output->writeln('<error>No se pudo encontrar o actualizar la línea de mapeo de puertos de Nginx.</error>');
        } else {
            file_put_contents($dockerComposeFile, implode(PHP_EOL, $newLines));
            $output->writeln("<info>Archivo docker-compose.yml actualizado correctamente con el puerto {$port} para Nginx.</info>");
        }
    }

    private function updateNginxConf(string $host, OutputInterface $output): void
    {
        $nginxConfFile = getcwd() . '/docker/nginx/default.conf';
        
        if (!file_exists($nginxConfFile)) {
            $output->writeln('<error>Error: Archivo ./docker/nginx/default.conf no encontrado.</error>');
            return;
        }

        $lines = file($nginxConfFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $updatedServerName = false;

        foreach ($lines as $line) {
            if (preg_match('/^(\s*server_name\s+)(.*?)(;?\s*)$/', $line, $matches) && !$updatedServerName) {
                $newLine = $matches[1] . $host . $matches[3];
                $newLines[] = $newLine;
                $output->writeln("<comment>Actualizada línea server_name: '" . rtrim($line) . "' -> '" . rtrim($newLine) . "'</comment>");
                $updatedServerName = true;
            } else {
                $newLines[] = $line;
            }
        }

        if (!$updatedServerName) {
            $output->writeln('<error>No se pudo encontrar o actualizar la directiva server_name.</error>');
        } else {
            file_put_contents($nginxConfFile, implode(PHP_EOL, $newLines));
            $output->writeln("<info>Archivo ./docker/nginx/default.conf actualizado correctamente con el host {$host}.</info>");
        }
    }
}
