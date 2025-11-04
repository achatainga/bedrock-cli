<?php

namespace Roots\BedrockCli\Commands\Plugin;

use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class SearchCommand extends Command
{
    use InteractiveSearchTrait;

    protected function configure(): void
    {
        $this->setName('plugin:search')
            ->setDescription('Search for WordPress plugins interactively')
            ->addArgument('query', InputArgument::REQUIRED, 'Search query')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add selected plugins to this profile')
            ->addOption('page', null, InputOption::VALUE_OPTIONAL, 'Page number', 1)
            ->addOption('per-page', null, InputOption::VALUE_OPTIONAL, 'Results per page', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');
        $page = (int) $input->getOption('page');
        $perPage = (int) $input->getOption('per-page');
        $profileName = $input->getOption('profile');

        $apiService = new WordPressApiService();
        $output->writeln("<info>Searching for plugins: {$query}...</info>");

        $result = $apiService->searchPlugins($query, $page, $perPage);

        if (empty($result['plugins'])) {
            $output->writeln('<error>No plugins found.</error>');
            return Command::FAILURE;
        }

        $plugins = $result['plugins'];
        $total = $result['info']['results'] ?? 0;

        $output->writeln("<comment>Found {$total} plugins (showing page {$page}):</comment>\n");

        $this->displayPluginsTable($plugins, $output);

        $helper = $this->getHelper('question');
        $question = new Question("\n<fg=yellow>Seleccionar números (ej: 1,3,5) o Enter para salir:</> ");
        $selection = $helper->ask($input, $output, $question);

        if (empty($selection)) {
            return Command::SUCCESS;
        }

        $selected = array_map('trim', explode(',', $selection));
        $selectedPlugins = [];

        foreach ($selected as $num) {
            $index = (int) $num - 1;
            if (!isset($plugins[$index])) {
                continue;
            }

            $plugin = $plugins[$index];
            $slug = $plugin['slug'];
            $version = $this->selectPluginVersion($slug, $helper, $input, $output);
            
            $selectedPlugins[$slug] = $version;
            $output->writeln("<info>✓ {$slug}:{$version}</info>");
        }

        if (empty($selectedPlugins)) {
            return Command::SUCCESS;
        }

        // Preguntar qué hacer con los plugins seleccionados
        $output->writeln('');
        $output->writeln('<fg=yellow>¿Qué deseas hacer con los plugins seleccionados?</>');
        $output->writeln(' <fg=cyan>[1]</> Agregar a un profile');
        $output->writeln(' <fg=cyan>[2]</> Instalar en proyecto actual');
        $output->writeln(' <fg=cyan>[0]</> Cancelar');
        $output->writeln('');
        
        $actionQuestion = new Question('<fg=yellow>Opción [0]:</> ', '0');
        $action = $helper->ask($input, $output, $actionQuestion);

        if ($action === '1') {
            // Agregar a profile
            if (!$profileName) {
                $profileService = new ProfileService();
                $profiles = $profileService->listProfiles();
                
                if (empty($profiles)) {
                    $output->writeln('<error>No hay profiles creados. Usa: bedrock profile:create</error>');
                    return Command::FAILURE;
                }
                
                $output->writeln('');
                $output->writeln('<fg=cyan>Profiles disponibles:</>');
                foreach ($profiles as $idx => $profile) {
                    $output->writeln("  <fg=cyan>[" . ($idx + 1) . "]</> {$profile['name']}");
                }
                $output->writeln('');
                
                $profileQuestion = new Question('<fg=yellow>Seleccionar profile [1]:</> ', '1');
                $profileChoice = $helper->ask($input, $output, $profileQuestion);
                
                $profileIndex = (int)$profileChoice - 1;
                if (!isset($profiles[$profileIndex])) {
                    $output->writeln('<error>Selección inválida</error>');
                    return Command::FAILURE;
                }
                
                $profileName = $profiles[$profileIndex]['name'];
            } else {
                $profileService = new ProfileService();
            }

            if (!$profileService->profileExists($profileName)) {
                $output->writeln("<error>Profile '{$profileName}' no existe.</error>");
                return Command::FAILURE;
            }

            $profile = $profileService->loadProfile($profileName);
            foreach ($selectedPlugins as $slug => $version) {
                $profile['plugins']['public'][$slug] = $version;
            }
            $profileService->saveProfile($profileName, $profile);
            $output->writeln("\n<info>✓ Plugins agregados al profile '{$profileName}'</info>");
            
        } elseif ($action === '2') {
            // Instalar en proyecto actual
            $projectPath = $this->findBedrockProject($output);
            
            if (!$projectPath) {
                $output->writeln('<error>No se encontró ningún proyecto Bedrock</error>');
                return Command::FAILURE;
            }
            
            $output->writeln("<info>Proyecto: {$projectPath}</info>");
            $output->writeln('');
            
            // Verificar plugins existentes
            $composerFile = $projectPath . '/composer.json';
            $composer = json_decode(file_get_contents($composerFile), true);
            $existing = $composer['require'] ?? [];
            
            foreach ($selectedPlugins as $slug => $version) {
                $package = "wpackagist-plugin/{$slug}";
                
                if (isset($existing[$package])) {
                    $currentVersion = $existing[$package];
                    $output->writeln("<comment>⚠️  {$slug} ya existe (versión: {$currentVersion})</comment>");
                    
                    $confirmQuestion = new \Symfony\Component\Console\Question\ConfirmationQuestion(
                        "<fg=yellow>¿Actualizar a {$version}? (Y/n):</> ",
                        false
                    );
                    
                    if (!$helper->ask($input, $output, $confirmQuestion)) {
                        $output->writeln("<comment>Omitido: {$slug}</comment>");
                        continue;
                    }
                }
                
                $constraint = $version === '*' ? '' : ":{$version}";
                $command = "composer require {$package}{$constraint} --working-dir={$projectPath}";
                $output->writeln("<comment>$ {$command}</comment>");
                passthru($command, $exitCode);
                
                if ($exitCode === 0) {
                    $output->writeln("<info>✓ {$slug} instalado</info>");
                } else {
                    $output->writeln("<error>✗ Error instalando {$slug}</error>");
                }
                $output->writeln('');
            }
        }

        return Command::SUCCESS;
    }

    private function findBedrockProject(OutputInterface $output): ?string
    {
        // Verificar directorio actual
        if ($this->isBedrockProject(getcwd())) {
            return getcwd();
        }
        
        // Buscar en subdirectorios inmediatos
        $output->writeln('<comment>Buscando proyectos Bedrock en subdirectorios...</comment>');
        $subdirs = glob(getcwd() . '/*', GLOB_ONLYDIR);
        $bedrockProjects = [];
        
        foreach ($subdirs as $dir) {
            if ($this->isBedrockProject($dir)) {
                $bedrockProjects[] = $dir;
            }
        }
        
        if (empty($bedrockProjects)) {
            return null;
        }
        
        if (count($bedrockProjects) === 1) {
            return $bedrockProjects[0];
        }
        
        // Múltiples proyectos encontrados
        $output->writeln('');
        $output->writeln('<fg=cyan>Proyectos Bedrock encontrados:</>');
        foreach ($bedrockProjects as $idx => $project) {
            $name = basename($project);
            $output->writeln("  <fg=cyan>[" . ($idx + 1) . "]</> {$name}");
        }
        $output->writeln('');
        
        $helper = $this->getHelper('question');
        $question = new \Symfony\Component\Console\Question\Question('<fg=yellow>Seleccionar proyecto [1]:</> ', '1');
        $choice = $helper->ask($this->getApplication()->find('plugin:search')->getDefinition()->getArguments()['query'], $output, $question);
        
        $index = (int)$choice - 1;
        return $bedrockProjects[$index] ?? null;
    }
    
    private function isBedrockProject(string $path): bool
    {
        $composerFile = $path . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        
        // Verificar si tiene roots/bedrock como dependencia o es un proyecto bedrock
        return isset($composer['require']['roots/bedrock']) ||
               isset($composer['require']['roots/wordpress']) ||
               (isset($composer['extra']['installer-paths']) && 
                isset($composer['extra']['installer-paths']['web/app/mu-plugins/{$name}/']));
    }
}
