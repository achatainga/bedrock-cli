<?php

namespace BedrockCli\Commands\Profile;

use BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CreateCommand extends Command
{
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
        $question = new Question('¿Qué plugins públicos necesitas? (separados por coma): ');
        $pluginsInput = $helper->ask($input, $output, $question) ?: '';
        $publicPlugins = array_filter(array_map('trim', explode(',', $pluginsInput)));

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Repositorio premium
        $output->writeln('<info>💎 PLUGINS PREMIUM</info>');
        $question = new ConfirmationQuestion('¿Tienes un repositorio Git de plugins premium? (Y/n): ', false);
        $hasPremiumRepo = $helper->ask($input, $output, $question);

        $premiumRepoUrl = null;
        if ($hasPremiumRepo) {
            $question = new Question('Git URL del repositorio premium: ');
            $premiumRepoUrl = $helper->ask($input, $output, $question);
        }

        $output->writeln('');
        $output->writeln('<comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $output->writeln('');

        // Plugins custom
        $output->writeln('<info>🛠️  PLUGINS CUSTOM (en desarrollo)</info>');
        $question = new ConfirmationQuestion('¿Tienes plugins custom en otro proyecto? (Y/n): ', false);
        $hasCustomPlugins = $helper->ask($input, $output, $question);

        $customPluginsPath = null;
        if ($hasCustomPlugins) {
            $question = new Question('Path relativo a los plugins custom: ');
            $customPluginsPath = $helper->ask($input, $output, $question);
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
                'premium' => [],
                'custom' => []
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

        // Agregar repositorio premium si existe
        if ($premiumRepoUrl) {
            $profile['repositories'][] = [
                'type' => 'vcs',
                'url' => $premiumRepoUrl
            ];
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
            $profile['require']["wpackagist-plugin/{$plugin}"] = '*';
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
}
