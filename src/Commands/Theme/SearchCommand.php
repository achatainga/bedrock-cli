<?php

namespace Roots\BedrockCli\Commands\Theme;

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
        $this->setName('theme:search')
            ->setDescription('Search for WordPress themes interactively')
            ->addArgument('query', InputArgument::REQUIRED, 'Search query')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add selected theme to this profile')
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
        $output->writeln("<info>Searching for themes: {$query}...</info>");

        $result = $apiService->searchThemes($query, $page, $perPage);

        if (empty($result['themes'])) {
            $output->writeln('<error>No themes found.</error>');
            return Command::FAILURE;
        }

        $themes = $result['themes'];
        $total = $result['info']['results'] ?? 0;

        $output->writeln("<comment>Found {$total} themes (showing page {$page}):</comment>\n");

        $this->displayThemesTable($themes, $output);

        $helper = $this->getHelper('question');
        $question = new Question("\n<fg=yellow>Seleccionar número o Enter para salir:</> ");
        $selection = $helper->ask($input, $output, $question);

        if (empty($selection)) {
            return Command::SUCCESS;
        }

        $index = (int) $selection - 1;
        if (!isset($themes[$index])) {
            $output->writeln('<error>Selección inválida</error>');
            return Command::FAILURE;
        }

        $theme = $themes[$index];
        $slug = $theme['slug'];
        $version = $this->selectThemeVersion($slug, $helper, $input, $output);
        
        $output->writeln("<info>✓ {$slug}:{$version}</info>");

        // Preguntar qué hacer
        $output->writeln('');
        $output->writeln('<fg=yellow>¿Qué deseas hacer con el tema seleccionado?</>');
        $output->writeln(' <fg=cyan>[1]</> Agregar a un profile');
        $output->writeln(' <fg=cyan>[2]</> Instalar en proyecto Bedrock');
        $output->writeln(' <fg=cyan>[0]</> Cancelar');
        $output->writeln('');
        
        $actionQuestion = new Question('<fg=yellow>Opción [0]:</> ', '0');
        $action = $helper->ask($input, $output, $actionQuestion);

        if ($action === '1') {
            // Agregar a profile
            if (!$profileName) {
                $profileService = new ProfileService();
                $profiles = array_values($profileService->listProfiles());
                
                if (empty($profiles)) {
                    $output->writeln('<comment>No hay profiles creados.</comment>');
                    $output->writeln('');
                    $createQuestion = new \Symfony\Component\Console\Question\ConfirmationQuestion(
                        '<fg=yellow>¿Crear un nuevo profile? (Y/n):</> ',
                        true
                    );
                    
                    if ($helper->ask($input, $output, $createQuestion)) {
                        $nameQuestion = new Question('<fg=yellow>Nombre del nuevo profile:</> ');
                        $newProfileName = $helper->ask($input, $output, $nameQuestion);
                        
                        if (!empty($newProfileName)) {
                            $createCmd = $this->getApplication()->find('profile:create');
                            $createInput = new \Symfony\Component\Console\Input\ArrayInput(['name' => $newProfileName]);
                            $createCmd->run($createInput, $output);
                        }
                    }
                    return Command::SUCCESS;
                }
                
                $output->writeln('');
                $output->writeln('<fg=cyan>Profiles disponibles:</>');
                foreach ($profiles as $idx => $profileData) {
                    $output->writeln("  <fg=cyan>[" . ($idx + 1) . "]</> {$profileData['name']}");
                }
                $output->writeln('  <fg=cyan>[N]</> Crear nuevo profile');
                $output->writeln('  <fg=cyan>[0]</> Cancelar');
                $output->writeln('');
                
                $profileQuestion = new Question('<fg=yellow>Seleccionar profile [1]:</> ', '1');
                $profileChoice = $helper->ask($input, $output, $profileQuestion);
                
                if (strtoupper($profileChoice) === 'N') {
                    $nameQuestion = new Question('<fg=yellow>Nombre del nuevo profile:</> ');
                    $newProfileName = $helper->ask($input, $output, $nameQuestion);
                    
                    if (empty($newProfileName)) {
                        $output->writeln('<error>Nombre requerido</error>');
                        return Command::FAILURE;
                    }
                    
                    $createCmd = $this->getApplication()->find('profile:create');
                    $createInput = new \Symfony\Component\Console\Input\ArrayInput(['name' => $newProfileName]);
                    $createCmd->run($createInput, $output);
                    return Command::SUCCESS;
                }
                
                if ($profileChoice === '0') {
                    return Command::SUCCESS;
                }
                
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
            $profile['theme']['name'] = $slug;
            $profile['theme']['version'] = $version;
            $profile['theme']['type'] = 'public';
            $profileService->saveProfile($profileName, $profile);
            $output->writeln("\n<info>✓ Tema agregado al profile '{$profileName}'</info>");
            
        } elseif ($action === '2') {
            // Instalar en proyecto
            $projectPath = $this->findBedrockProject($output, $input);
            
            if (!$projectPath) {
                $output->writeln('<error>No se encontró ningún proyecto Bedrock</error>');
                return Command::FAILURE;
            }
            
            $output->writeln("<info>Proyecto: {$projectPath}</info>");
            $output->writeln('');
            
            $package = "wpackagist-theme/{$slug}";
            $constraint = $version === '*' ? '' : ":{$version}";
            $command = "composer require {$package}{$constraint} --working-dir={$projectPath}";
            $output->writeln("<comment>$ {$command}</comment>");
            passthru($command, $exitCode);
            
            if ($exitCode === 0) {
                $output->writeln("<info>✓ {$slug} instalado</info>");
            } else {
                $output->writeln("<error>✗ Error instalando {$slug}</error>");
            }
        }

        return Command::SUCCESS;
    }

    private function findBedrockProject(OutputInterface $output, InputInterface $input = null): ?string
    {
        if ($this->isBedrockProject(getcwd())) {
            return getcwd();
        }
        
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
        
        $output->writeln('');
        $output->writeln('<fg=cyan>Proyectos Bedrock encontrados:</>');
        foreach ($bedrockProjects as $idx => $project) {
            $name = basename($project);
            $output->writeln("  <fg=cyan>[" . ($idx + 1) . "]</> {$name}");
        }
        $output->writeln('  <fg=cyan>[0]</> Cancelar');
        $output->writeln('');
        
        $helper = $this->getHelper('question');
        $question = new \Symfony\Component\Console\Question\Question('<fg=yellow>Seleccionar proyecto [1]:</> ', '1');
        $choice = $helper->ask($input, $output, $question);
        
        if ($choice === '0') {
            return null;
        }
        
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
        
        return isset($composer['require']['roots/bedrock']) ||
               isset($composer['require']['roots/wordpress']) ||
               (isset($composer['extra']['installer-paths']) && 
                isset($composer['extra']['installer-paths']['web/app/mu-plugins/{$name}/']));
    }
}
