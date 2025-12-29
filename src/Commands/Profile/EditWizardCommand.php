<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\DTOs\Profile;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\VcsValidator;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Roots\BedrockCli\Traits\PluginManagementTrait;
use Roots\BedrockCli\Traits\ThemeManagementTrait;
use Roots\BedrockCli\Traits\VendorExtractionTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class EditWizardCommand extends Command
{
    use InteractiveSearchTrait;
    use PremiumAssetsTrait;
    use PluginManagementTrait;
    use ThemeManagementTrait;
    use VendorExtractionTrait;
    
    protected static $defaultName = 'profile:edit-wizard';
    private ProfileService $profileService;
    private VcsValidator $vcsValidator;

    public function __construct(
        ProfileService $profileService,
        VcsValidator $vcsValidator
    ) {
        $this->profileService = $profileService;
        $this->vcsValidator = $vcsValidator;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:edit-wizard')
            ->setDescription('Editar profile existente con wizard interactivo')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $helper = $this->getHelper('question');

        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($name);

        while (true) {
            $this->displayMainMenu($profile, $output);
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === '1') {
                $this->editDescription($profile, $input, $output, $helper);
            } elseif ($action === '2') {
                $this->managePluginsInteractive($profile, $input, $output, $helper);
            } elseif ($action === '3') {
                $this->manageThemesInteractive($profile, $input, $output, $helper);
            } elseif ($action === '4') {
                $this->editDockerMode($profile, $input, $output, $helper);
            }
            
            $output->writeln('');
        }

        // Regenerar require antes de guardar
        $this->regenerateRequire($profile);
        
        $this->profileService->saveProfile($name, $profile);
        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' actualizado</info>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function displayMainMenu(Profile|array $profile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   ✏️  EDITAR PROFILE               <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln("<comment>Profile: {$profile['name']}</comment>");
        $dockerStatus = ($profile['docker_mode'] ?? false) ? '<fg=green>SÍ</>' : '<fg=red>NO</>';
        $output->writeln("<comment>Docker Mode: {$dockerStatus}</comment>");
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 📝 Descripción');
        $output->writeln('  <fg=cyan>[2]</> 📦 Plugins');
        $output->writeln('  <fg=cyan>[3]</> 🎨 Themes');
        $output->writeln('  <fg=cyan>[4]</> 🐳 Docker Mode');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Guardar y salir');
        $output->writeln('');
    }

    private function editDescription(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $current = $profile['description'] ?? 'Sin descripción';
        $output->writeln("<comment>Actual: {$current}</comment>");
        $output->writeln('');
        
        $question = new Question('Nueva descripción [Enter para mantener]: ', $current);
        $profile['description'] = $helper->ask($input, $output, $question);
        $output->writeln('<info>✓ Descripción actualizada</info>');
    }
    
    private function editDockerMode(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $current = $profile['docker_mode'] ?? false;
        $currentText = $current ? 'SÍ' : 'NO';
        $output->writeln("<comment>Docker Mode actual: {$currentText}</comment>");
        $output->writeln('');
        $output->writeln('<fg=yellow>Docker Mode controla si los assets premium se copian o usan symlinks.</>');        $output->writeln('<fg=yellow>• SÍ (Docker): Copia archivos (compatible con contenedores)</>');        $output->writeln('<fg=yellow>• NO (Nativo): Usa symlinks (más rápido en desarrollo local)</>');        $output->writeln('');
        
        $question = new ConfirmationQuestion('¿Habilitar Docker Mode? (Y/n): ', $current);
        $profile['docker_mode'] = $helper->ask($input, $output, $question);
        $output->writeln('<info>✓ Docker Mode actualizado</info>');
    }

    protected function addNewPlugin(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 🌐 Público (WordPress.org)');
        $output->writeln('  <fg=cyan>[2]</> 💎 Premium (Repositorio privado)');
        $output->writeln('  <fg=cyan>[3]</> 🔧 Custom (Carpeta local)');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver atrás');
        $output->writeln('');
        
        $question = new Question('> ');
        $type = trim($helper->ask($input, $output, $question));
        
        if ($type === '0') {
            return;
        }
        
        if ($type === '1') {
            $plugins = $this->searchWithCancelOption($input, $output, $helper, 'plugin');
            $added = 0;
            $pluginsData = $profile['plugins'];
            foreach ($plugins as $plugin) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $validation = $this->validateNoDuplicatePlugin($profile, $slug, 'public');
                if (!$validation['valid']) {
                    $output->writeln("<error>{$validation['message']}</error>");
                    continue;
                }
                $pluginsData['public'][] = $plugin;
                $added++;
            }
            $profile['plugins'] = $pluginsData;
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) público(s) agregado(s)</info>");
            }
        } elseif ($type === '2') {
            $plugins = $this->selectPremiumPlugins($input, $output, $helper);
            $plugins = $this->downloadPremiumPluginsToCache($plugins, $input, $output);
            $added = 0;
            $pluginsData = $profile['plugins'];
            foreach ($plugins as $plugin) {
                $validation = $this->validateNoDuplicatePlugin($profile, $plugin['name'], 'premium');
                if (!$validation['valid']) {
                    $output->writeln("<error>{$validation['message']}</error>");
                    continue;
                }
                $pluginsData['premium'][] = $plugin;
                $added++;
            }
            $profile['plugins'] = $pluginsData;
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) premium agregado(s)</info>");
            }
        } elseif ($type === '3') {
            $added = $this->selectCustomPluginsInteractive($profile, $input, $output, $helper);
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) custom agregado(s)</info>");
            }
        }
    }

    protected function getProfileService()
    {
        return $this->profileService;
    }
    
    private function downloadPremiumPluginsToCache(array $premiumPlugins, InputInterface $input, OutputInterface $output): array
    {
        $cacheService = new \Roots\BedrockCli\Services\PremiumCacheService();
        $processedPlugins = [];
        $helper = $this->getHelper('question');
        
        foreach ($premiumPlugins as $plugin) {
            if ($plugin['source'] === 'vcs' && !empty($plugin['path'])) {
                if ($cacheService->pluginExists($plugin['name'], $plugin['version'])) {
                    $question = new ConfirmationQuestion(
                        "<fg=yellow>{$plugin['name']} v{$plugin['version']} ya existe en caché. ¿Redescargar? (Y/n):</> ",
                        false
                    );
                    
                    if ($helper->ask($input, $output, $question)) {
                        $cacheService->clearPluginCache($plugin['name'], $plugin['version']);
                        $output->writeln("<info>✓ Caché de {$plugin['name']} limpiado</info>");
                    } else {
                        $output->writeln("<comment>✓ Usando {$plugin['name']} v{$plugin['version']} desde caché</comment>");
                        $plugin['source'] = 'cache';
                        $plugin['original_url'] = $plugin['url'];
                        unset($plugin['url']);
                        $processedPlugins[] = $plugin;
                        continue;
                    }
                }
                
                $output->writeln("<comment>📥 Descargando {$plugin['name']} v{$plugin['version']} a caché...</comment>");
                
                try {
                    $cacheService->downloadPlugin(
                        $plugin['url'],
                        $plugin['name'],
                        $plugin['version'],
                        $plugin['path']
                    );
                    
                    $plugin['source'] = 'cache';
                    $plugin['original_url'] = $plugin['url'];
                    unset($plugin['url']);
                    
                    $output->writeln("<info>✓ {$plugin['name']} descargado</info>");
                } catch (\Exception $e) {
                    $output->writeln("<error>✗ Error: {$e->getMessage()}</error>");
                    continue;
                }
            }
            
            $processedPlugins[] = $plugin;
        }
        
        return $processedPlugins;
    }
    
    protected function addNewTheme(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 🌐 Público (WordPress.org)');
        $output->writeln('  <fg=cyan>[2]</> 💎 Premium (Repositorio privado)');
        $output->writeln('  <fg=cyan>[3]</> 📦 Importar .zip local');
        $output->writeln('  <fg=cyan>[4]</> 🔧 Custom (Carpeta local)');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver atrás');
        $output->writeln('');
        
        $question = new Question('> ');
        $type = trim($helper->ask($input, $output, $question));
        
        if ($type === '0') {
            return;
        }
        
        if ($type === '1') {
            $themes = $this->searchWithCancelOption($input, $output, $helper, 'theme');
            $added = 0;
            foreach ($themes as $theme) {
                $profile['themes']['public'][] = $theme;
                $added++;
            }
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} theme(s) público(s) agregado(s)</info>");
            }
        } elseif ($type === '2') {
            $premiumTheme = $this->selectPremiumTheme($input, $output, $helper);
            if ($premiumTheme) {
                // Download to cache if from VCS
                if ($premiumTheme['source'] === 'vcs' && !empty($premiumTheme['path'])) {
                    $cacheService = new \Roots\BedrockCli\Services\PremiumCacheService();
                    
                    if ($cacheService->themeExists($premiumTheme['name'], $premiumTheme['version'])) {
                        $question = new ConfirmationQuestion(
                            "<fg=yellow>{$premiumTheme['name']} v{$premiumTheme['version']} ya existe en caché. ¿Redescargar? (Y/n):</> ",
                            false
                        );
                        
                        if ($helper->ask($input, $output, $question)) {
                            $cacheService->clearThemeCache($premiumTheme['name'], $premiumTheme['version']);
                            $output->writeln("<info>✓ Caché de {$premiumTheme['name']} limpiado</info>");
                        } else {
                            $output->writeln("<comment>✓ Usando {$premiumTheme['name']} v{$premiumTheme['version']} desde caché</comment>");
                            $premiumTheme['source'] = 'cache';
                            $premiumTheme['original_url'] = $premiumTheme['url'];
                            unset($premiumTheme['url']);
                        }
                    }
                    
                    if ($premiumTheme['source'] === 'vcs') {
                        $output->writeln("<comment>📥 Descargando {$premiumTheme['name']} v{$premiumTheme['version']} a caché...</comment>");
                        
                        try {
                            $cacheService->downloadTheme(
                                $premiumTheme['url'],
                                $premiumTheme['name'],
                                $premiumTheme['version'],
                                $premiumTheme['path']
                            );
                            
                            $premiumTheme['source'] = 'cache';
                            $premiumTheme['original_url'] = $premiumTheme['url'];
                            unset($premiumTheme['url']);
                            
                            $output->writeln("<info>✓ {$premiumTheme['name']} descargado</info>");
                        } catch (\Exception $e) {
                            $output->writeln("<error>✗ Error: {$e->getMessage()}</error>");
                            return;
                        }
                    }
                }
                
                $themes = $profile['themes'];
                $themes['premium'][] = $premiumTheme;
                $profile['themes'] = $themes;
                $output->writeln("<info>✓ Theme premium agregado</info>");
            }
        } elseif ($type === '3') {
            $imported = $this->importLocalZip($input, $output, $helper, 'theme');
            if (!empty($imported)) {
                $themes = $profile['themes'];
                foreach ($imported as $theme) {
                    $themes['premium'][] = $theme;
                }
                $profile['themes'] = $themes;
                $output->writeln("<info>✓ Theme importado</info>");
            }
        } elseif ($type === '4') {
            $added = $this->selectCustomThemesInteractive($profile, $input, $output, $helper);
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} theme(s) custom agregado(s)</info>");
            }
        }
    }
    
    protected function regenerateRequire(Profile|array &$profile): void
    {
        // Limpiar require completamente
        $newRequire = [];
        
        // Plugins públicos
        if (!empty($profile['plugins']['public'])) {
            foreach ($profile['plugins']['public'] as $plugin) {
                if (is_array($plugin)) {
                    $newRequire["wpackagist-plugin/{$plugin['slug']}"] = $plugin['version'];
                } else {
                    $newRequire["wpackagist-plugin/{$plugin}"] = '*';
                }
            }
        }
        
        // Plugins premium
        if (!empty($profile['plugins']['premium'])) {
            foreach ($profile['plugins']['premium'] as $plugin) {
                if ($plugin['source'] === 'vcs') {
                    // VCS: validar y obtener package name real con la rama del plugin
                    $branch = $plugin['version'];
                    if (str_starts_with($branch, 'dev-')) {
                        $branch = substr($branch, 4);
                    }
                    $packageInfo = $this->vcsValidator->getPackageInfo($plugin['url'], $branch);
                    if ($packageInfo) {
                        $newRequire[$packageInfo['name']] = "dev-{$branch}";
                    }
                    continue;
                }
                $vendor = $this->extractVendorFromPlugin($plugin);
                $newRequire["{$vendor}/{$plugin['name']}"] = $plugin['version'];
            }
        }
        
        // Themes premium
        if (!empty($profile['themes']['premium'])) {
            foreach ($profile['themes']['premium'] as $theme) {
                $vendor = $theme['source'] === 'cache' ? 'cached' : 'wpackagist-theme';
                $newRequire["{$vendor}/{$theme['name']}"] = $theme['version'];
            }
        }
        
        // Reemplazar require completamente
        $profile['require'] = $newRequire;
    }
}
