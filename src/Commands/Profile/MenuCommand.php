<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MenuCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this->setName('profile:menu')
             ->setDescription('Menú de gestión de profiles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        while (true) {
            $result = $this->showMainMenu($input, $output);
            if ($result === 'exit') {
                return Command::SUCCESS;
            }
        }
    }

    private function showMainMenu(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   📋 <fg=white;options=bold>PROFILES - Gestión</><fg=cyan>            ║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<fg=gray>Crea y gestiona profiles con plugins, themes y dependencias.</>');
        $output->writeln('');

        // Detectar contexto
        $inProject = $this->isInBedrockProject();
        $activeProfile = null;
        
        if ($inProject) {
            $output->writeln('<fg=green>✓</> <fg=white>Proyecto:</> <fg=yellow>' . basename(getcwd()) . '</>');
            $activeProfile = $this->getActiveProfile();
            if ($activeProfile) {
                $output->writeln('<fg=green>📋</> <fg=white>Profile activo:</> <fg=cyan>' . $activeProfile . '</>');
            }
        } else {
            $output->writeln('<fg=yellow>⚠️  No estás en un proyecto Bedrock</>');
        }
        $output->writeln('');

        // Listar profiles disponibles
        $profiles = $this->profileService->listProfiles();
        
        if (empty($profiles)) {
            $output->writeln('<fg=gray>No hay profiles disponibles</>');
            $output->writeln('');
            $output->writeln(' <fg=cyan>[C]</> 🆕 Crear nuevo profile');
            $output->writeln(' <fg=cyan>[I]</> 📥 Importar desde JSON');
            $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
            $output->writeln('');
            
            $question = new Question('<fg=yellow>Opción [C, I, 0]: </>', '0');
            $choice = strtoupper($helper->ask($input, $output, $question));
            
            if ($choice === 'C') {
                $this->runCommand('profile:create', [], $input, $output);
                return 'continue';
            } elseif ($choice === 'I') {
                $pathQuestion = new Question('<fg=yellow>Path al archivo JSON:</> ');
                $path = $helper->ask($input, $output, $pathQuestion);
                if ($path) {
                    $this->runCommand('profile:import', ['path' => $path], $input, $output);
                }
                return 'continue';
            }
            return 'exit';
        }

        $output->writeln('<fg=white;options=bold>Profiles disponibles:</>');
        $output->writeln('');
        
        $choices = [];
        $index = 1;
        foreach ($profiles as $profile) {
            $desc = $profile['description'] ?? 'Sin descripción';
            $isActive = ($activeProfile && $profile['name'] === $activeProfile);
            $activeTag = $isActive ? ' <fg=green;options=bold>(ACTIVO)</>' : '';
            $output->writeln(" <fg=cyan>[{$index}]</> <fg=white>{$profile['name']}</> <fg=gray>- {$desc}</>{$activeTag}");
            $choices[$index] = $profile['name'];
            $index++;
        }
        
        $output->writeln('');
        $output->writeln(' <fg=cyan>[C]</> 🆕 Crear nuevo profile');
        $output->writeln(' <fg=cyan>[I]</> 📥 Importar desde JSON');
        $output->writeln(' <fg=cyan>[L]</> 📊 Listar todos (detalles)');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [1-' . count($profiles) . ', C, I, L, 0]: </>', '0');
        $choice = strtoupper($helper->ask($input, $output, $question));

        if ($choice === '0') {
            return 'exit';
        }

        if ($choice === 'C') {
            $nameQuestion = new Question('<fg=yellow>Nombre del nuevo profile:</> ');
            $newProfileName = $helper->ask($input, $output, $nameQuestion);
            
            if (!empty($newProfileName)) {
                $this->runCommand('profile:create', ['name' => $newProfileName], $input, $output);
            }
            return 'continue';
        }
        
        if ($choice === 'I') {
            $pathQuestion = new Question('<fg=yellow>Path al archivo JSON:</> ');
            $path = $helper->ask($input, $output, $pathQuestion);
            if ($path) {
                $this->runCommand('profile:import', ['path' => $path], $input, $output);
                $this->waitForEnter($input, $output);
            }
            return 'continue';
        }
        
        if ($choice === 'L') {
            $this->runCommand('profile:list', [], $input, $output);
            $this->waitForEnter($input, $output);
            return 'continue';
        }

        $selectedIndex = (int)$choice;
        if (isset($choices[$selectedIndex])) {
            $this->showProfileMenu($choices[$selectedIndex], $inProject, $input, $output);
            return 'continue';
        }

        $output->writeln('<error>Opción inválida</error>');
        return 'continue';
    }

    private function showProfileMenu(string $profileName, bool $inProject, InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $activeProfile = $inProject ? $this->getActiveProfile() : null;
        $isActive = ($activeProfile === $profileName);
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   Profile: ' . str_pad($profileName, 24) . '<fg=cyan>║</>');
        if ($isActive) {
            $output->writeln('<fg=cyan>║</>   <fg=cyan>📌 ACTIVO en proyecto actual</><fg=cyan>       ║</>');
        }
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 👁️  Ver detalles (JSON completo)');
        $output->writeln(' <fg=cyan>[2]</> ✏️  Editar (abrir en editor)');
        $output->writeln(' <fg=cyan>[3]</> 🧙 Editar con wizard');
        
        if ($inProject) {
            $output->writeln(' <fg=cyan>[4]</> 📥 Aplicar (a proyecto actual)');
        } else {
            $output->writeln(' <comment>[4]</comment> <fg=gray>📥 Aplicar (requiere proyecto)</>');
        }
        
        $output->writeln(' <fg=cyan>[5]</> 📤 Exportar a JSON');
        $output->writeln(' <fg=cyan>[6]</> 📋 Duplicar profile');
        $output->writeln(' <fg=cyan>[7]</> 🔢 Orden de activación');
        $output->writeln(' <fg=cyan>[8]</> 🗑️  Eliminar');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-8]: </>', '0');
        $choice = $helper->ask($input, $output, $question);
        
        if (!is_numeric($choice) || $choice < 0 || $choice > 8) {
            $output->writeln('<error>Opción inválida</error>');
            $this->waitForEnter($input, $output);
            return;
        }

        switch ($choice) {
            case '1':
                $this->runCommand('profile:show', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '2':
                $this->runCommand('profile:edit', ['name' => $profileName], $input, $output);
                break;
            case '3':
                $this->runCommand('profile:edit-wizard', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '4':
                $this->runCommand('profile:apply', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '5':
                $pathQuestion = new Question('<fg=yellow>Path destino (Enter = ./' . $profileName . '.json):</> ');
                $exportPath = $helper->ask($input, $output, $pathQuestion);
                $args = ['name' => $profileName];
                if ($exportPath) {
                    $args['path'] = $exportPath;
                }
                $this->runCommand('profile:export', $args, $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '6':
                $nameQuestion = new Question('<fg=yellow>Nombre del nuevo profile:</> ');
                $newName = $helper->ask($input, $output, $nameQuestion);
                if ($newName) {
                    $profile = $this->profileService->loadProfile($profileName);
                    $profile['name'] = $newName;
                    $profile['description'] = ($profile['description'] ?? '') . ' (copia de ' . $profileName . ')';
                    $this->profileService->saveProfile($newName, $profile);
                    $output->writeln("<info>✓ Profile '{$newName}' creado como copia de '{$profileName}'</info>");
                }
                $this->waitForEnter($input, $output);
                break;
            case '7':
                $this->manageActivationOrder($profileName, $input, $output);
                break;
            case '8':
                $this->runCommand('profile:delete', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '0':
                break;
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
        }
    }

    private function isInBedrockProject(): bool
    {
        $cwd = getcwd();
        return file_exists("{$cwd}/web/wp-config.php") || file_exists("{$cwd}/config/application.php");
    }

    private function getActiveProfile(): ?string
    {
        $cwd = getcwd();
        $profileFile = "{$cwd}/.bedrock/profile.json";
        
        if (!file_exists($profileFile)) {
            return null;
        }
        
        $content = file_get_contents($profileFile);
        $profile = json_decode($content, true);
        
        return $profile['name'] ?? null;
    }

    private function runCommand(string $commandName, array $arguments, InputInterface $input, OutputInterface $output): void
    {
        try {
            $command = $this->getApplication()->find($commandName);
            $commandInput = new ArrayInput($arguments);
            $command->run($commandInput, $output);
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
        }
    }

    private function waitForEnter(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');
        $output->write('<comment>Presiona Enter para continuar...</comment>');
        if ($input->isInteractive()) {
            fgets(STDIN);
        }
    }
    
    private function manageActivationOrder(string $profileName, InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $profile = $this->profileService->loadProfile($profileName);
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln('<fg=magenta;options=bold>  🔢 ORDEN DE ACTIVACIÓN</>');
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 👁️  Ver orden guardado');
        $output->writeln(' <fg=cyan>[2]</> ✏️  Editar orden (wizard)');
        $output->writeln(' <fg=cyan>[3]</> 🗑️  Eliminar orden');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
        
        $question = new Question('<fg=yellow>Opción [0-3]: </>', '0');
        $choice = $helper->ask($input, $output, $question);
        
        switch ($choice) {
            case '1':
                if (!isset($profile['activation_order'])) {
                    $output->writeln('<comment>No hay orden guardado en este profile</comment>');
                } else {
                    $order = $profile['activation_order']['order'];
                    $deps = $profile['activation_order']['dependencies'] ?? [];
                    $updated = $profile['activation_order']['updated_at'] ?? 'N/A';
                    
                    $output->writeln('');
                    $output->writeln('<fg=yellow;options=bold>Orden de activación:</>');
                    $output->writeln('');
                    
                    asort($order);
                    foreach ($order as $plugin => $position) {
                        $depInfo = isset($deps[$plugin]) ? ' <fg=yellow>↳ ' . implode(', ', $deps[$plugin]) . '</>' : '';
                        $output->writeln("  <fg=cyan>[{$position}]</> <fg=green>{$plugin}</>{$depInfo}");
                    }
                    
                    $output->writeln('');
                    $output->writeln("<fg=gray>Actualizado: {$updated}</>");
                }
                $this->waitForEnter($input, $output);
                break;
                
            case '2':
                $output->writeln('');
                $output->writeln('<comment>Abriendo wizard de orden de activación...</comment>');
                $output->writeln('<comment>Después de completar el wizard, guarda en este profile.</comment>');
                $this->waitForEnter($input, $output);
                $this->runCommand('plugins:order:build', [], $input, $output);
                break;
                
            case '3':
                if (!isset($profile['activation_order'])) {
                    $output->writeln('<comment>No hay orden guardado</comment>');
                } else {
                    unset($profile['activation_order']);
                    $this->profileService->saveProfile($profileName, $profile);
                    $output->writeln('<info>✓ Orden eliminado del profile</info>');
                }
                $this->waitForEnter($input, $output);
                break;
        }
    }
}
