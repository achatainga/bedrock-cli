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
            $forceQuestion = new Question("<fg=yellow>El directorio '{$name}' ya existe. ¿Sobrescribir? (s/N):</> ", 'N');
            $forceAnswer = strtoupper(trim($helper->ask($input, $output, $forceQuestion)));
            if ($forceAnswer !== 'S' && $forceAnswer !== 'Y') {
                $output->writeln('<error>Operación cancelada</error>');
                return Command::FAILURE;
            }
        }

        // 3. Seleccionar profile
        $output->writeln('');
        $output->writeln('<fg=cyan>═══ CONFIGURACIÓN DE PROFILE ═══</>');
        
        $profileService = new ProfileService();
        $profiles = array_values($profileService->listProfiles());
        
        $output->writeln('');
        foreach ($profiles as $idx => $profile) {
            $output->writeln('  <fg=cyan>[' . ($idx + 1) . ']</> ' . $profile['name']);
        }
        $output->writeln('  <fg=cyan>[N]</> Crear nuevo profile');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Cancelar');
        $output->writeln('');
        
        $profileQuestion = new Question('<fg=yellow>Opción [1]:</> ', '1');
        $selectedProfile = strtoupper($helper->ask($input, $output, $profileQuestion));
        
        if ($selectedProfile === '0') {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::FAILURE;
        }
        
        $profileName = 'default';
        if ($selectedProfile === 'N') {
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
            $profileIndex = (int)$selectedProfile - 1;
            if (isset($profiles[$profileIndex])) {
                $profileName = $profiles[$profileIndex]['name'];
            }
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
        
        $dockerQuestion = new Question('<fg=yellow>¿Generar archivos Docker? (S/n):</> ', 'S');
        $dockerAnswer = strtoupper(trim($helper->ask($input, $output, $dockerQuestion)));
        $withDocker = $dockerAnswer === 'S' || $dockerAnswer === 'Y' || $dockerAnswer === '';
        
        $acornQuestion = new Question('<fg=yellow>¿Instalar Roots Acorn? (S/n):</> ', 'S');
        $acornAnswer = strtoupper(trim($helper->ask($input, $output, $acornQuestion)));
        $withAcorn = $acornAnswer === 'S' || $acornAnswer === 'Y' || $acornAnswer === '';
        
        $redisQuestion = new Question('<fg=yellow>¿Instalar Redis Object Cache? (S/n):</> ', 'S');
        $redisAnswer = strtoupper(trim($helper->ask($input, $output, $redisQuestion)));
        $withRedis = $redisAnswer === 'S' || $redisAnswer === 'Y' || $redisAnswer === '';

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
        
        $confirmQuestion = new Question('<fg=yellow>¿Crear proyecto con esta configuración? (S/n):</> ', 'S');
        $confirmAnswer = strtoupper(trim($helper->ask($input, $output, $confirmQuestion)));
        
        if ($confirmAnswer === 'N') {
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
