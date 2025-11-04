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
            $output->writeln('');
            $output->writeln('<info>Instalando plugins...</info>');
            
            foreach ($selectedPlugins as $slug => $version) {
                $constraint = $version === '*' ? '' : ":{$version}";
                $command = "composer require wpackagist-plugin/{$slug}{$constraint}";
                $output->writeln("<comment>$ {$command}</comment>");
                passthru($command, $exitCode);
                
                if ($exitCode === 0) {
                    $output->writeln("<info>✓ {$slug} instalado</info>");
                } else {
                    $output->writeln("<error>✗ Error instalando {$slug}</error>");
                }
            }
        }

        return Command::SUCCESS;
    }
}
