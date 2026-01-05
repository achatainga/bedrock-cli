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
use Roots\BedrockCli\Services\WebServerService;
use Roots\BedrockCli\Traits\ProjectCreationTrait;

class NewWizardCommand extends Command
{
    use ProjectCreationTrait;
    
    public function __construct(WebServerService $webServerService)
    {
        parent::__construct();
        $this->webServerService = $webServerService;
    }
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

        // 5. Modo de Ejecución
        $output->writeln('');
        $output->writeln('<fg=cyan>═══ MODO DE EJECUCIÓN ═══</>');
        
        $modeChoices = [
            'full' => '🐳 Full Docker (Todo en contenedores: Web + DB + Redis)',
            'hybrid' => '🚀 Hybrid (PHP local + Servicios en Docker)',
            'native' => '💻 Native (Todo local: LAMP/LEMP clásico)'
        ];
        
        $modeQuestion = new ChoiceQuestion(
            '<fg=yellow>Selecciona el modo de ejecución:</>',
            array_values($modeChoices),
            0
        );
        $modeAnswer = $helper->ask($input, $output, $modeQuestion);
        $mode = array_search($modeAnswer, $modeChoices); // 'full', 'hybrid', 'native'

        $withDocker = $mode !== 'native';
        $hybridServices = [];

        // Configuración específica para modo Híbrido
        if ($mode === 'hybrid') {
            $output->writeln('');
            $output->writeln('<fg=cyan>Configuración Híbrida:</>');
            
            $serviceQuestion = new ChoiceQuestion(
                '<fg=yellow>¿Qué servicios quieres en Docker? (separados por coma, ej: 0,1):</>',
                ['MySQL', 'Redis'],
                '0,1'
            );
            $serviceQuestion->setMultiselect(true);
            $selectedServices = $helper->ask($input, $output, $serviceQuestion);
            
            if (in_array('MySQL', $selectedServices)) $hybridServices[] = 'mysql';
            if (in_array('Redis', $selectedServices)) $hybridServices[] = 'redis';
            
            // Validar WP-CLI global
            $wpPath = trim(shell_exec('which wp 2>/dev/null') ?: '');
            if (empty($wpPath)) {
                $output->writeln('<fg=yellow>⚠️  WP-CLI global no detectado. Es necesario para el modo híbrido.</>');
            }
        }
        
        $acornQuestion = new Question('<fg=yellow>¿Instalar Roots Acorn? (S/n):</> ', 'S');
        $acornAnswer = strtoupper(trim($helper->ask($input, $output, $acornQuestion)));
        $withAcorn = $acornAnswer === 'S' || $acornAnswer === 'Y' || $acornAnswer === '';
        
        $redisQuestion = new Question('<fg=yellow>¿Instalar Redis Object Cache? (S/n):</> ', 'S');
        $redisAnswer = strtoupper(trim($helper->ask($input, $output, $redisQuestion)));
        $withRedis = $redisAnswer === 'S' || $redisAnswer === 'Y' || $redisAnswer === '';

        // 6. Detectar web server del sistema y estrategia
        $webServerService = new WebServerService();
        $webServer = $withDocker ? $webServerService->detectWebServer() : null;
        $useReverseProxy = false;
        $generateWebServerConfig = false;
        
        // 7. Puertos (solo si Docker está habilitado)
        $httpPort = 80;
        $mysqlPort = 3306;
        $redisPort = 6379;
        
        if ($withDocker) {
            $output->writeln('');
            $output->writeln('<fg=cyan>═══ DETECCIÓN DE ENTORNO ═══</>');
            
            if ($webServer) {
                $output->writeln("<fg=green>✓</> Web server detectado: <fg=yellow>{$webServer}</>");
                $existingProjects = $webServerService->detectExistingProjects();
                if (!empty($existingProjects)) {
                    $output->writeln("<fg=green>✓</> Proyectos existentes: <fg=yellow>" . implode(', ', $existingProjects) . "</>");
                }
            } else {
                $output->writeln("<fg=yellow>ℹ</> No se detectó web server del sistema (nginx/apache)");
            }
            
            $output->writeln('');
            $output->writeln('<fg=cyan>═══ CONFIGURACIÓN DE PUERTOS ═══</>');
            
            // Detectar puertos libres usando el trait
            $httpPortInfo = $this->determineHttpPort(null, $output);
            $freeHttpPort = $httpPortInfo['port'];
            $freeMysqlPort = $this->webServerService->findFreePort(3306);
            $freeRedisPort = $this->webServerService->findFreePort(6379);
            
            // Recomendar estrategia
            if ($webServer) {
                $recommendation = $webServerService->recommendStrategy($webServer, $freeHttpPort);
                
                $output->writeln('');
                $output->writeln('<fg=cyan;options=bold>═══ ESTRATEGIA DE DESPLIEGUE ═══</>');
                $output->writeln("<fg=yellow>Razón:</> {$recommendation['reason']}");
                
                if ($recommendation['strategy'] === 'reverse-proxy') {
                    $output->writeln('');
                    $output->writeln('<fg=green>Recomendación:</> Usar reverse proxy (escalable, multi-proyecto)');
                    $output->writeln("  • Docker nginx en puerto 82");
                    $output->writeln("  • {$webServer} sistema hace reverse proxy 80 → 82");
                    $output->writeln("  • Fácil configuración SSL con certbot");
                    $output->writeln('');
                    
                    $strategyQuestion = new Question('<fg=yellow>¿Usar reverse proxy? (S/n):</> ', 'S');
                    $strategyAnswer = strtoupper(trim($helper->ask($input, $output, $strategyQuestion)));
                    
                    if ($strategyAnswer === 'S' || $strategyAnswer === 'Y' || $strategyAnswer === '') {
                        $useReverseProxy = true;
                        $freeHttpPort = 82; // Forzar puerto 82
                        
                        $configQuestion = new Question("<fg=yellow>¿Generar y activar configuración {$webServer}? (S/n):</> ", 'S');
                        $configAnswer = strtoupper(trim($helper->ask($input, $output, $configQuestion)));
                        $generateWebServerConfig = ($configAnswer === 'S' || $configAnswer === 'Y' || $configAnswer === '');
                    }
                } else {
                    $output->writeln('');
                    $output->writeln('<fg=green>Recomendación:</> Docker nginx directo en puerto 80');
                    if (isset($recommendation['alternative'])) {
                        $output->writeln("<fg=yellow>Alternativa:</> {$recommendation['alternative']}");
                    }
                    $output->writeln('');
                }
            }
            
            // Mostrar advertencia si puerto está ocupado
            if ($freeHttpPort !== 80 && !$useReverseProxy) {
                $output->writeln("<comment>⚠️  Puerto 80 ocupado, sugerido: {$freeHttpPort}</comment>");
            }
            if ($freeMysqlPort !== 3306) {
                $output->writeln("<comment>⚠️  Puerto 3306 ocupado, sugerido: {$freeMysqlPort}</comment>");
            }
            if ($freeRedisPort !== 6379) {
                $output->writeln("<comment>⚠️  Puerto 6379 ocupado, sugerido: {$freeRedisPort}</comment>");
            }
            
            $output->writeln('');
            $httpPortQuestion = new Question("<fg=yellow>Puerto HTTP [{$freeHttpPort}]:</> ", (string)$freeHttpPort);
            $httpPort = $helper->ask($input, $output, $httpPortQuestion);
            
            $mysqlPortQuestion = new Question("<fg=yellow>Puerto MySQL [{$freeMysqlPort}]:</> ", (string)$freeMysqlPort);
            $mysqlPort = $helper->ask($input, $output, $mysqlPortQuestion);
            
            $redisPortQuestion = new Question("<fg=yellow>Puerto Redis [{$freeRedisPort}]:</> ", (string)$freeRedisPort);
            $redisPort = $helper->ask($input, $output, $redisPortQuestion);
        }

        // 8. Resumen y confirmación
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

        // 9. Ejecutar comando 'new' con los parámetros configurados
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
            '--mode' => $mode,
            '--hybrid-services' => implode(',', $hybridServices ?? []),
        ];
        
        if ($mode === 'native') {
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
        $result = $newCommand->run($newInput, $output);
        
        // 10. Generar y activar configuración web server si se solicitó
        if ($result === Command::SUCCESS && $generateWebServerConfig && $webServer && $useReverseProxy) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>═══ CONFIGURANDO WEB SERVER ═══</>');
            
            // Preguntar por dominio
            $domainQuestion = new Question("<fg=yellow>Dominio o IP [{$name}.local]:</> ", "{$name}.local");
            $domain = $helper->ask($input, $output, $domainQuestion);
            
            // Generar configuración
            if ($webServer === 'nginx') {
                $config = $webServerService->generateNginxConfig($name, $domain, (int)$httpPort);
                $installResult = $webServerService->installNginxConfig($name, $config);
            } else {
                $config = $webServerService->generateApacheConfig($name, $domain, (int)$httpPort);
                $installResult = $webServerService->installApacheConfig($name, $config);
            }
            
            if ($installResult['success']) {
                $output->writeln("<fg=green>✓</> {$installResult['message']}");
                $output->writeln('');
                $output->writeln("<fg=green>✓</> Proyecto accesible en: <fg=yellow>http://{$domain}</>");
                $output->writeln("<fg=cyan>ℹ</> Para SSL: <fg=yellow>sudo certbot --{$webServer} -d {$domain}</>");
            } else {
                $output->writeln("<fg=red>✗</> {$installResult['message']}");
                $output->writeln('');
                $output->writeln('<fg=yellow>Configuración manual requerida:</>');
                $output->writeln("  1. Guardar config en: <fg=cyan>{$name}-{$webServer}.conf</>");
                $output->writeln("  2. Copiar a /etc/{$webServer}/sites-available/");
                $output->writeln("  3. Activar sitio y recargar {$webServer}");
            }
        }
        
        return $result;
    }
}
