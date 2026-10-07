<?php

namespace Roots\BedrockCli\Commands\Setup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class InitCommand extends Command
{
    use ProjectSelectorTrait;
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize environment using blueprint configuration')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Environment (production, staging, development)', 'development')
            ->addOption('skip-db', null, InputOption::VALUE_NONE, 'Skip database import')
            ->addOption('skip-seeders', null, InputOption::VALUE_NONE, 'Skip seeders execution')
            ->addOption('skip-plugins', null, InputOption::VALUE_NONE, 'Skip plugin activation')
            ->setHelp('Initialize WordPress environment based on blueprint configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $env = $input->getOption('env');
        
        $io->title("🚀 Initializing {$env} environment");

        // Detect project root
        $projectRoot = $this->detectProjectRoot();
        if (!$projectRoot) {
            $io->error('Not in a Bedrock project directory');
            return Command::FAILURE;
        }

        // Load blueprint
        $blueprintPath = "{$projectRoot}/blueprints/{$env}.json";
        if (!file_exists($blueprintPath)) {
            $io->error("Blueprint not found: {$blueprintPath}");
            return Command::FAILURE;
        }

        $blueprint = json_decode(file_get_contents($blueprintPath), true);
        $io->info("Blueprint: {$blueprint['name']}");

        // Check WP-CLI
        if (!$this->isWpCliAvailable()) {
            $io->error('WP-CLI is not available. Please install it first.');
            return Command::FAILURE;
        }

        // Import database
        if (!$input->getOption('skip-db') && !empty($blueprint['database']['import'])) {
            $this->importDatabase($io, $projectRoot, $blueprint['database']['import']);
        }

        // Execute seeders
        if (!$input->getOption('skip-seeders') && !empty($blueprint['seeders'])) {
            $this->executeSeeders($io, $projectRoot, $blueprint['seeders']);
        }

        // Activate plugins
        if (!$input->getOption('skip-plugins')) {
            $this->activatePlugins($io, $projectRoot, $blueprint['plugins']);
        }

        // Activate theme
        if (!empty($blueprint['theme']['activate'])) {
            $this->activateTheme($io, $projectRoot);
        }

        // Configure options
        if (!empty($blueprint['options'])) {
            $this->configureOptions($io, $projectRoot, $blueprint['options']);
        }

        // Search-replace URLs
        if (!empty($blueprint['wordpress']['url'])) {
            $this->searchReplace($io, $projectRoot, $blueprint['wordpress']['url']);
        }

        // Flush rewrite rules
        $this->flushRewriteRules($io, $projectRoot);

        $io->success("✅ {$env} environment initialized successfully!");
        
        // Marcar paso 2 como completado (Instalar WordPress)
        $this->markStepCompleted(2);
        
        return Command::SUCCESS;
    }

    private function detectProjectRoot(): ?string
    {
        $current = getcwd();
        
        while ($current !== dirname($current)) {
            if (file_exists("{$current}/web/wp-config.php") || file_exists("{$current}/config/application.php")) {
                return $current;
            }
            $current = dirname($current);
        }
        
        return null;
    }

    private function runWp(string $projectRoot, array $args): Process
    {
        $process = new Process(array_merge(['wp'], $args), $projectRoot);
        $process->setTimeout(300);
        $process->run();
        return $process;
    }

    private function isWpCliAvailable(): bool
    {
        $process = new Process(['wp', '--version']);
        $process->run();
        return $process->isSuccessful();
    }

    private function importDatabase(SymfonyStyle $io, string $projectRoot, string $snapshotPath): void
    {
        $fullPath = "{$projectRoot}/database/{$snapshotPath}";
        
        if (!file_exists($fullPath)) {
            $io->warning("Snapshot not found: {$snapshotPath}");
            return;
        }

        $io->section('📦 Importing database snapshot');
        
        $process = $this->runWp($projectRoot, ['db', 'import', $fullPath]);
        
        if ($process->isSuccessful()) {
            $io->success('Database imported successfully');
        } else {
            $io->error('Failed to import database: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function executeSeeders(SymfonyStyle $io, string $projectRoot, array $seeders): void
    {
        $io->section('🌱 Executing seeders');

        foreach ($seeders as $seeder) {
            $io->text("Running {$seeder}...");
            
            $seederPath = "{$projectRoot}/database/seeders/{$seeder}.php";
            
            if (!file_exists($seederPath)) {
                $io->warning("Seeder not found: {$seeder}");
                continue;
            }

            // Try Acorn first, fallback to wp eval-file
            $process = $this->runWp($projectRoot, ['acorn', 'db:seed', "--class={$seeder}"]);
            
            if (!$process->isSuccessful()) {
                // Fallback to wp eval-file
                $process = $this->runWp($projectRoot, ['eval-file', $seederPath]);
            }
            
            if ($process->isSuccessful()) {
                $io->success("{$seeder} executed");
            } else {
                $io->error("Failed to execute {$seeder}: " . trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        }
    }

    private function activatePlugins(SymfonyStyle $io, string $projectRoot, array $pluginsConfig): void
    {
        $io->section('🔌 Activating plugins');

        if ($pluginsConfig['activate'] === 'all') {
            $process = $this->runWp($projectRoot, ['plugin', 'activate', '--all']);
            
            if ($process->isSuccessful()) {
                $io->success('All plugins activated');
            } else {
                $io->error('Failed to activate plugins: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        }

        // Configure licenses
        if (!empty($pluginsConfig['licenses'])) {
            $this->configureLicenses($io, $projectRoot, $pluginsConfig['licenses']);
        }
    }

    private function configureLicenses(SymfonyStyle $io, string $projectRoot, array $licenses): void
    {
        $io->text('Configuring plugin licenses...');

        foreach ($licenses as $plugin => $licenseKey) {
            // Load from .env if placeholder
            if (preg_match('/{{(.+)}}/', $licenseKey, $matches)) {
                $envKey = $matches[1];
                $licenseKey = getenv($envKey) ?: '';
            }

            if (empty($licenseKey)) {
                $io->warning("License key not found for {$plugin}");
                continue;
            }

            // Plugin-specific license configuration
            switch ($plugin) {
                case 'acf-pro':
                    $process = $this->runWp($projectRoot, ['option', 'update', 'acf_pro_license', $licenseKey]);
                    break;
                case 'gravityforms':
                    $process = $this->runWp($projectRoot, ['option', 'update', 'rg_gforms_key', $licenseKey]);
                    break;
                default:
                    $io->warning("Unknown license configuration for {$plugin}");
                    continue 2;
            }
            
            if ($process->isSuccessful()) {
                $io->text("✓ {$plugin} license configured");
            } else {
                $io->warning("Failed to configure license for {$plugin}: " . trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        }
    }

    private function activateTheme(SymfonyStyle $io, string $projectRoot): void
    {
        $io->section('🎨 Activating theme');

        // Get theme from profile
        $profilePath = "{$projectRoot}/.bedrock/profile.json";
        if (!file_exists($profilePath)) {
            $io->warning('Profile not found, skipping theme activation');
            return;
        }

        $profile = json_decode(file_get_contents($profilePath), true);
        $themeName = $profile['theme']['name'] ?? null;

        if (!$themeName) {
            $io->warning('Theme name not found in profile');
            return;
        }

        $process = $this->runWp($projectRoot, ['theme', 'activate', $themeName]);
        
        if ($process->isSuccessful()) {
            $io->success("Theme '{$themeName}' activated");
        } else {
            $io->error('Failed to activate theme: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function configureOptions(SymfonyStyle $io, string $projectRoot, array $options): void
    {
        $io->section('⚙️  Configuring WordPress options');

        foreach ($options as $key => $value) {
            $process = $this->runWp($projectRoot, ['option', 'update', (string) $key, (string) $value]);
            
            if ($process->isSuccessful()) {
                $io->text("✓ {$key} = {$value}");
            }
        }
    }

    private function searchReplace(SymfonyStyle $io, string $projectRoot, string $newUrl): void
    {
        $io->section('🔄 Running search-replace for URLs');

        // Get current URL
        $process = $this->runWp($projectRoot, ['option', 'get', 'siteurl']);
        $oldUrl = trim($process->getOutput());
        
        if (!$process->isSuccessful() || empty($oldUrl)) {
            $io->warning('Could not detect current URL, skipping search-replace');
            return;
        }
        
        if ($oldUrl === $newUrl) {
            $io->text('URLs match, skipping search-replace');
            return;
        }

        $io->text("Replacing {$oldUrl} → {$newUrl}");
        
        $replaceProcess = $this->runWp($projectRoot, ['search-replace', $oldUrl, $newUrl, '--all-tables']);
        
        if ($replaceProcess->isSuccessful()) {
            $io->success('URLs replaced successfully');
        } else {
            $io->error('Failed to replace URLs: ' . trim($replaceProcess->getErrorOutput() ?: $replaceProcess->getOutput()));
        }
    }

    private function flushRewriteRules(SymfonyStyle $io, string $projectRoot): void
    {
        $io->section('🔄 Flushing rewrite rules');

        $process = $this->runWp($projectRoot, ['rewrite', 'flush']);
        if ($process->isSuccessful()) {
            $io->success('Rewrite rules flushed');
        }
    }

    private function markStepCompleted(int $stepId): void
    {
        $stateService = new StateService();
        $stateService->markCompleted(getcwd(), $stepId);
    }
}
