<?php

namespace Roots\BedrockCli\Commands\Setup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Roots\BedrockCli\Services\ProfileService;

class NewWizardCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('new:wizard')
             ->setDescription('Wizard interactivo para crear nuevo proyecto Bedrock');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  🆕 WIZARD: CREAR NUEVO PROYECTO BEDROCK  </> <fg=cyan;options=bold>     ║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════╝</>');
        $output->writeln('');

        // 1. Nombre del proyecto
        $nameQuestion = new Question('<fg=yellow>Nombre del proyecto:</> ');
        $nameQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('El nombre del proyecto es obligatorio');
            }
            if (preg_match('/[^a-z0-9\-_]/i', $answer)) {
                throw new \RuntimeException('Solo letras, números, guiones y guiones bajos');
            }
            return $answer;
        });
        $name = $helper->ask($input, $output, $nameQuestion);

        // 2. Verificar si existe
        if (is_dir($name)) {
            $forceQuestion = new ConfirmationQuestion(
                "<fg=yellow>El directorio '{$name}' ya existe. ¿Sobrescribir? (s/N):</> ",
                false
            );
            if (!$helper->ask($input, $output, $forceQuestion)) {
                $output->writeln('<error>Operación cancelada</error>');
                return Command::FAILURE;
            }
        }

        // 3. Seleccionar profile
        $output->writeln('');
        $output->writeln('<fg=cyan>═══ CONFIGURACIÓN DE PROFILE ═══</>');
        
        $profileService = new ProfileService();
        $profiles = $profileService->listProfiles();
        
        $profileChoices = ['[Crear nuevo profile]'];
        foreach ($profiles as $profile) {
            $profileChoices[] = $profile['name'];
        }
        
        $profileQuestion = new ChoiceQuestion(
            '<fg=yellow>Selecciona un profile:</> ',
            $profileChoices,
            0
        );
        $selectedProfile = $helper->ask($input, $output, $profileQuestion);
        
        $profileName = 'default';
        if ($selectedProfile === '[Crear nuevo profile]') {
            $output->writeln('');
            $output->writeln('<fg=cyan>Creando nuevo profile...</>');
            
            $newProfileQuestion = new Question('<fg=yellow>Nombre del nuevo profile:</> ');
            $profileName = $helper->ask($input, $output, $newProfileQuestion);
            
            // Crear profile básico
            $createProfileCmd = $this->getApplication()->find('profile:create');
            $createProfileInput = new ArrayInput([
                'command' => 'profile:create',
                'name' => $profileName,
            ]);
            $createProfileCmd->run($createProfileInput, $output);
        } else {
            $profileName = $selectedProfile;
        }

        // 4. Configuración de base de datos
        $output->writeln('');
        $output->writeln('<fg=cyan>═══ CONFIGURACIÓN DE BASE DE DATOS ═══</>');
        
        $dbNameQuestion = new Question(
            "<fg=yellow>Nombre de BD [" . str_replace('-', '_', $name) . "]:</> ",
            str_replace('-', '_', $name)
        );
        $dbName = $helper->ask($input, $output, $dbNameQuestion);
        
        $dbUserQuestion = new Question('<fg=yellow>Usuario BD [root]:</> ', 'root');
        $dbUser = $helper->ask($input, $output, $dbUserQuestion);
        
        $dbPassQuestion = new Question('<fg=yellow>Contraseña BD [mysql]:</> ', 'mysql');
        $dbPass = $helper->ask($input, $output, $dbPassQuestion);

        // 5. Opciones adicionales
        $output->writeln('');
        $output->writeln('<fg=cyan>═══ OPCIONES ADICIONALES ═══</>');
        
        $dockerQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Generar archivos Docker? (S/n):</> ',
            true
        );
        $withDocker = $helper->ask($input, $output, $dockerQuestion);
        
        $acornQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Instalar Roots Acorn? (S/n):</> ',
            true
        );
        $withAcorn = $helper->ask($input, $output, $acornQuestion);
        
        $redisQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Instalar Redis Object Cache? (S/n):</> ',
            true
        );
        $withRedis = $helper->ask($input, $output, $redisQuestion);

        // 6. Puertos (solo si Docker está habilitado)
        $httpPort = 80;
        $mysqlPort = 3306;
        $redisPort = 6379;
        
        if ($withDocker) {
            $output->writeln('');
            $output->writeln('<fg=cyan>═══ CONFIGURACIÓN DE PUERTOS ═══</>');
            
            $httpPortQuestion = new Question('<fg=yellow>Puerto HTTP [80]:</> ', '80');
            $httpPort = $helper->ask($input, $output, $httpPortQuestion);
            
            $mysqlPortQuestion = new Question('<fg=yellow>Puerto MySQL [3306]:</> ', '3306');
            $mysqlPort = $helper->ask($input, $output, $mysqlPortQuestion);
            
            $redisPortQuestion = new Question('<fg=yellow>Puerto Redis [6379]:</> ', '6379');
            $redisPort = $helper->ask($input, $output, $redisPortQuestion);
        }

        // 7. Resumen y confirmación
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>═══ RESUMEN DE CONFIGURACIÓN ═══</>');
        $output->writeln("<fg=cyan>Proyecto:</> {$name}");
        $output->writeln("<fg=cyan>Profile:</> {$profileName}");
        $output->writeln("<fg=cyan>Base de datos:</> {$dbName}");
        $output->writeln("<fg=cyan>Usuario BD:</> {$dbUser}");
        $output->writeln("<fg=cyan>Docker:</> " . ($withDocker ? 'Sí' : 'No'));
        $output->writeln("<fg=cyan>Acorn:</> " . ($withAcorn ? 'Sí' : 'No'));
        $output->writeln("<fg=cyan>Redis:</> " . ($withRedis ? 'Sí' : 'No'));
        if ($withDocker) {
            $output->writeln("<fg=cyan>Puertos:</> HTTP:{$httpPort}, MySQL:{$mysqlPort}, Redis:{$redisPort}");
        }
        $output->writeln('');
        
        $confirmQuestion = new ConfirmationQuestion(
            '<fg=yellow>¿Crear proyecto con esta configuración? (S/n):</> ',
            true
        );
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln('<error>Operación cancelada</error>');
            return Command::FAILURE;
        }

        // 8. Ejecutar comando 'new' con los parámetros configurados
        $output->writeln('');
        $output->writeln('<fg=green;options=bold>🚀 Creando proyecto...</>');
        $output->writeln('');
        
        $newCommand = $this->getApplication()->find('new');
        $arguments = [
            'command' => 'new',
            'name' => $name,
            '--profile' => $profileName,
            '--db-name' => $dbName,
            '--db-user' => $dbUser,
            '--db-pass' => $dbPass,
        ];
        
        if (!$withDocker) {
            $arguments['--no-docker'] = true;
        } else {
            $arguments['--http-port'] = $httpPort;
            $arguments['--mysql-port'] = $mysqlPort;
            $arguments['--redis-port'] = $redisPort;
        }
        
        if (!$withAcorn) {
            $arguments['--no-acorn'] = true;
        }
        
        if (!$withRedis) {
            $arguments['--no-redis'] = true;
        }
        
        if (is_dir($name)) {
            $arguments['--force'] = true;
        }
        
        $newInput = new ArrayInput($arguments);
        return $newCommand->run($newInput, $output);
    }
}
