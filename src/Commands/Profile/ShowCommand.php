<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends Command
{
    protected static $defaultName = 'profile:show';
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        parent::__construct();
        $this->profileService = $profileService;
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:show')
            ->setDescription('Mostrar detalles de un profile')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        try {
            $profile = $this->profileService->loadProfile($name);
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln("<info>📦 Profile: {$name}</info>");
        $output->writeln('');
        $output->writeln('<comment>Descripción:</comment> ' . ($profile['description'] ?? 'Sin descripción'));
        $output->writeln('');

        // Plugins públicos
        if (!empty($profile['plugins']['public'])) {
            $output->writeln('<info>🔌 Plugins Públicos:</info>');
            foreach ($profile['plugins']['public'] as $plugin) {
                $output->writeln("  • {$plugin}");
            }
            $output->writeln('');
        }

        // Repositorios
        if (!empty($profile['repositories'])) {
            $output->writeln('<info>📚 Repositorios:</info>');
            foreach ($profile['repositories'] as $repo) {
                $output->writeln("  • [{$repo['type']}] {$repo['url']}");
            }
            $output->writeln('');
        }

        // Tema
        $output->writeln('<info>🎨 Tema:</info>');
        $output->writeln("  • Nombre: {$profile['theme']['name']}");
        $output->writeln("  • Tipo: {$profile['theme']['type']}");
        if ($profile['theme']['license_env']) {
            $output->writeln("  • Licencia: \${$profile['theme']['license_env']}");
        }
        $output->writeln('');

        // Blueprints
        $output->writeln('<info>🏗️  Blueprints:</info>');
        foreach ($profile['blueprints'] as $env => $config) {
            $output->writeln("  • <comment>{$env}</comment>: " . count($config['seeders']) . ' seeders');
        }
        $output->writeln('');

        $output->writeln('<comment>JSON completo:</comment>');
        $output->writeln(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $output->writeln('');

        return Command::SUCCESS;
    }
}
