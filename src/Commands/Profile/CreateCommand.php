<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\PremiumCacheService;
use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CreateCommand extends Command
{
    use InteractiveSearchTrait;
    use PremiumAssetsTrait;
    
    protected static $defaultName = 'profile:create';
    private ProfileService $profileService;
    private PremiumCacheService $cacheService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
        $this->cacheService = new PremiumCacheService();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:create')
            ->setDescription('Crear un nuevo profile de proyecto')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $helper = $this->getHelper('question');

        if ($this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' ya existe</error>");
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>╔════════════════════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║           🎨 BEDROCK PROFILE WIZARD                            ║</info>');
        $output->writeln('<info>╚════════════════════════════════════════════════════════════════╝</info>');
        $output->writeln('');

        // Descripción
        $question = new Question("📝 Descripción del profile: ");
        $description = $helper->ask($input, $output, $question) ?: 'Sin descripción';

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Plugins públicos
        $output->writeln('<info>🔌 PLUGINS PÚBLICOS (desde wpackagist.org)</info>');
        $question = new ConfirmationQuestion('¿Buscar plugins interactivamente? (Y/n): ', true);
        $searchPlugins = $helper->ask($input, $output, $question);
        
        $publicPlugins = [];
        if ($searchPlugins) {
            $publicPlugins = $this->searchPluginsInteractive($input, $output, $helper);
        } else {
            $question = new Question('¿Qué plugins públicos necesitas? (separados por coma): ');
            $pluginsInput = $helper->ask($input, $output, $question) ?: '';
            $publicPlugins = array_filter(array_map('trim', explode(',', $pluginsInput)));
        }

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Plugins premium usando trait
        $premiumPlugins = $this->selectPremiumPlugins($input, $output, $helper);
        
        // Descargar plugins premium a caché
        $premiumPlugins = $this->downloadPremiumPluginsToCache($premiumPlugins, $output);

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Plugins custom
        $output->writeln('<info>🛠️  PLUGINS CUSTOM (en desarrollo)</info>');
        $question = new ConfirmationQuestion('¿Tienes plugins custom en otro proyecto? (Y/n): ', false);
        $hasCustomPlugins = $helper->ask($input, $output, $question);

        $customPluginsPath = null;
        $customPluginsList = [];
        if ($hasCustomPlugins) {
            $question = new Question('Path absoluto a los plugins custom: ');
            $customPluginsPath = $helper->ask($input, $output, $question);
            
            if ($customPluginsPath) {
                try {
                    $detectedPlugins = $this->profileService->scanCustomPlugins($customPluginsPath);
                    
                    if (!empty($detectedPlugins)) {
                        $output->writeln("\n<info>Plugins detectados:</info>");
                        $pluginsList = [];
                        $index = 1;
                        foreach ($detectedPlugins as $slug => $plugin) {
                            $output->writeln("  <fg=cyan>[{$index}]</> {$plugin['name']} ({$slug}) - v{$plugin['version']}");
                            $pluginsList[$index] = $slug;
                            $index++;
                        }
                        
                        $question = new ConfirmationQuestion("\n¿Agregar todos estos plugins? (Y/n): ", true);
                        if ($helper->ask($input, $output, $question)) {
                            $customPluginsList = array_keys($detectedPlugins);
                        } else {
                            $selectQuestion = new Question("\nSeleccionar números (ej: 1,3,5) o Enter para omitir: ");
                            $selection = $helper->ask($input, $output, $selectQuestion);
                            
                            if (!empty($selection)) {
                                $selected = array_map('trim', explode(',', $selection));
                                foreach ($selected as $num) {
                                    $num = (int)$num;
                                    if (isset($pluginsList[$num])) {
                                        $customPluginsList[] = $pluginsList[$num];
                                    }
                                }
                            }
                        }
                        
                        if (!empty($customPluginsList)) {
                            $output->writeln("\n<info>Plugins seleccionados:</info>");
                            foreach ($customPluginsList as $slug) {
                                $output->writeln("  ✓ {$slug}");
                            }
                        }
                    } else {
                        $output->writeln('<comment>No se detectaron plugins válidos en ese directorio.</comment>');
                    }
                } catch (\RuntimeException $e) {
                    $output->writeln("<error>{$e->getMessage()}</error>");
                    $customPluginsPath = null;
                }
            }
        }

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Tema
        $output->writeln('<info>🎨 TEMA</info>');
        $question = new ConfirmationQuestion('¿Usar tema premium/custom? (Y/n): ', false);
        $usePremiumTheme = $helper->ask($input, $output, $question);

        $themeData = null;
        $themeLicenseEnv = null;
        $themeName = null;
        $isPremiumTheme = false;
        
        if ($usePremiumTheme) {
            $premiumTheme = $this->selectPremiumTheme($input, $output, $helper);
            if ($premiumTheme) {
                $themeData = $premiumTheme;
                $themeName = $premiumTheme['name'];
                $isPremiumTheme = true;
                $question = new Question('Variable de licencia en .env (opcional, ej: MOTTA_LICENSE): ');
                $themeLicenseEnv = $helper->ask($input, $output, $question);
            }
        }
        
        if (!$themeData) {
            $question = new Question('Nombre del tema público: ', 'twentytwentyfour');
            $themeName = $helper->ask($input, $output, $question);
            $themeData = [
                'name' => $themeName,
                'type' => 'public',
                'source' => 'public'
            ];
        }

        // Construir profile
        $profile = [
            'name' => $name,
            'description' => $description,
            'repositories' => [],
            'require' => [],
            'plugins' => [
                'public' => $publicPlugins,
                'premium' => $premiumPlugins,
                'custom' => $customPluginsList
            ],
            'theme' => [
                'name' => $themeName,
                'type' => $isPremiumTheme ? 'premium' : 'public',
                'license_env' => $themeLicenseEnv
            ],
            'blueprints' => [
                'production' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder', 'WooCommerceSeeder', 'ThemeSeeder', 'PluginsSeeder'],
                    'users' => ['admin' => 'administrator']
                ],
                'staging' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder', 'WooCommerceSeeder', 'ThemeSeeder', 'PluginsSeeder', 'ProductsSeeder'],
                    'faker_products' => 50
                ],
                'development' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder', 'WooCommerceSeeder', 'ThemeSeeder'],
                    'users' => ['admin' => 'administrator', 'dev' => 'administrator']
                ]
            ]
        ];

        // Agregar repositorios premium desde plugins
        foreach ($premiumPlugins as $plugin) {
            if ($plugin['source'] === 'cache') {
                // Para plugins en caché, agregar path al extracted
                $cachePath = $this->cacheService->getCachePath($plugin['name'], $plugin['version']);
                $profile['repositories'][] = [
                    'type' => 'path',
                    'url' => $cachePath,
                    'options' => ['symlink' => true]
                ];
            } elseif ($plugin['source'] === 'vcs' && !empty($plugin['url'])) {
                $exists = false;
                foreach ($profile['repositories'] as $repo) {
                    if ($repo['url'] === $plugin['url']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $profile['repositories'][] = [
                        'type' => 'vcs',
                        'url' => $plugin['url']
                    ];
                }
            } elseif ($plugin['source'] === 'path') {
                $profile['repositories'][] = [
                    'type' => 'path',
                    'url' => dirname($plugin['path']),
                    'options' => ['symlink' => true]
                ];
            }
        }

        // Agregar path de plugins custom si existe (uno por cada plugin)
        if ($customPluginsPath && !empty($customPluginsList)) {
            foreach ($customPluginsList as $pluginSlug) {
                $profile['repositories'][] = [
                    'type' => 'path',
                    'url' => $customPluginsPath . DIRECTORY_SEPARATOR . $pluginSlug,
                    'options' => ['symlink' => true]
                ];
            }
        }

        // Agregar plugins públicos a require
        foreach ($publicPlugins as $plugin) {
            if (is_array($plugin)) {
                $profile['require']["wpackagist-plugin/{$plugin['slug']}"] = $plugin['version'];
            } else {
                $profile['require']["wpackagist-plugin/{$plugin}"] = '*';
            }
        }
        
        // Agregar plugins premium a require
        foreach ($premiumPlugins as $plugin) {
            $vendor = $this->extractVendorFromPlugin($plugin);
            $profile['require']["{$vendor}/{$plugin['name']}"] = $plugin['version'];
        }

        // Guardar profile
        $this->profileService->saveProfile($name, $profile);

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' creado exitosamente</info>");
        $output->writeln('');
        $output->writeln('<comment>Ubicación:</comment> ' . $this->profileService->getProfilesPath() . "/{$name}.json");
        $output->writeln('');
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln("  bedrock new mi-proyecto --profile={$name}");
        $output->writeln("  bedrock profile:show {$name}");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function searchPluginsInteractive(InputInterface $input, OutputInterface $output, $helper): array
    {
        return $this->searchWithCancelOption($input, $output, $helper, 'plugin');
    }

    private function extractVendorFromPlugin(array $plugin): string
    {
        // Si es cache, SIEMPRE usar 'cached'
        if ($plugin['source'] === 'cache') {
            return 'cached';
        }
        
        // Si es VCS, extraer vendor del URL del repositorio
        if ($plugin['source'] === 'vcs' && !empty($plugin['url'])) {
            if (preg_match('#[:/]([^/]+)/[^/]+(?:\.git)?$#', $plugin['url'], $matches)) {
                return $matches[1];
            }
        }
        
        // Si es path o zip, intentar leer composer.json
        if (($plugin['source'] === 'path' || $plugin['source'] === 'zip') && !empty($plugin['path'])) {
            $composerPath = $plugin['path'] . '/composer.json';
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                if (!empty($composer['name'])) {
                    return explode('/', $composer['name'])[0];
                }
            }
        }
        
        return 'local';
    }
    
    private function downloadPremiumPluginsToCache(array $premiumPlugins, OutputInterface $output): array
    {
        $processedPlugins = [];
        $helper = $this->getHelper('question');
        $input = new \Symfony\Component\Console\Input\ArgvInput();
        
        foreach ($premiumPlugins as $plugin) {
            // Solo procesar plugins de tipo VCS con path (repositorio de paquetes)
            if ($plugin['source'] === 'vcs' && !empty($plugin['path'])) {
                // Verificar si ya existe en caché
                if ($this->cacheService->pluginExists($plugin['name'], $plugin['version'])) {
                    $question = new ConfirmationQuestion(
                        "<fg=yellow>{$plugin['name']} v{$plugin['version']} ya existe en caché. ¿Redescargar? (Y/n):</> ",
                        false
                    );
                    
                    if ($helper->ask($input, $output, $question)) {
                        $this->cacheService->clearPluginCache($plugin['name'], $plugin['version']);
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
                    $this->cacheService->downloadPlugin(
                        $plugin['url'],
                        $plugin['name'],
                        $plugin['version'],
                        $plugin['path']
                    );
                    
                    // Cambiar source a cache
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
}
