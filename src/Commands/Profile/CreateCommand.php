<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
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

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
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
                                
                                if (!empty($customPluginsList)) {
                                    $output->writeln("\n<info>Plugins seleccionados:</info>");
                                    foreach ($customPluginsList as $slug) {
                                        $output->writeln("  ✓ {$slug}");
                                    }
                                }
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
        $question = new Question('Nombre del tema: ', 'twentytwentyfour');
        $themeName = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('¿Es un tema premium? (Y/n): ', false);
        $isPremiumTheme = $helper->ask($input, $output, $question);

        $themeLicenseEnv = null;
        if ($isPremiumTheme) {
            $question = new Question('Variable de licencia en .env (ej: MOTTA_LICENSE): ');
            $themeLicenseEnv = $helper->ask($input, $output, $question);
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
            if ($plugin['source'] === 'vcs' && !empty($plugin['url'])) {
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

        // Agregar path de plugins custom si existe
        if ($customPluginsPath) {
            $profile['repositories'][] = [
                'type' => 'path',
                'url' => $customPluginsPath,
                'options' => ['symlink' => true]
            ];
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
            $vendor = $plugin['source'] === 'vcs' ? 'detodo24' : 'local';
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
        $apiService = new WordPressApiService();
        $selectedPlugins = [];
        
        while (true) {
            $question = new Question("\n🔍 Buscar plugin (o Enter para terminar): ");
            $query = $helper->ask($input, $output, $question);
            
            if (empty($query)) {
                break;
            }
            
            $result = $apiService->searchPlugins($query, 1, 10);
            
            if (empty($result['plugins'])) {
                $output->writeln('<error>No se encontraron plugins.</error>');
                continue;
            }
            
            $plugins = $result['plugins'];
            $output->writeln("\n<comment>Resultados:</comment>\n");
            
            $this->displayPluginsTable($plugins, $output);
            
            $question = new Question("\nSeleccionar números (ej: 1,3,5) o Enter para nueva búsqueda: ");
            $selection = $helper->ask($input, $output, $question);
            
            if (empty($selection)) {
                continue;
            }
            
            $selected = array_map('trim', explode(',', $selection));
            
            foreach ($selected as $num) {
                $index = (int) $num - 1;
                if (isset($plugins[$index])) {
                    $slug = $plugins[$index]['slug'];
                    $version = $this->selectPluginVersion($slug, $helper, $input, $output);
                    
                    $selectedPlugins[] = [
                        'slug' => $slug,
                        'version' => $version
                    ];
                    $output->writeln("<info>✓ {$slug}:{$version}</info>");
                }
            }
        }
        
        return $selectedPlugins;
    }
}
