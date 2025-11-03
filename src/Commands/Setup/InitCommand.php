<?php

namespace Roots\BedrockCli\Commands\Setup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
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

    private function isWpCliAvailable(): bool
    {
        exec('wp --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    private function importDatabase(SymfonyStyle $io, string $projectRoot, string $snapshotPath): void
    {
        $fullPath = "{$projectRoot}/database/{$snapshotPath}";
        
        if (!file_exists($fullPath)) {
            $io->warning("Snapshot not found: {$snapshotPath}");
            return;
        }

        $io->section('📦 Importing database snapshot');
        
        $command = "cd {$projectRoot} && wp db import {$fullPath} 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->success('Database imported successfully');
        } else {
            $io->error('Failed to import database: ' . implode("\n", $output));
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
            $command = "cd {$projectRoot} && wp acorn db:seed --class={$seeder} 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                // Fallback to wp eval-file
                $command = "cd {$projectRoot} && wp eval-file {$seederPath} 2>&1";
                exec($command, $output, $returnCode);
            }
            
            if ($returnCode === 0) {
                $io->success("{$seeder} executed");
            } else {
                $io->error("Failed to execute {$seeder}: " . implode("\n", $output));
            }
        }
    }

    private function activatePlugins(SymfonyStyle $io, string $projectRoot, array $pluginsConfig): void
    {
        $io->section('🔌 Activating plugins');

        if ($pluginsConfig['activate'] === 'all') {
            $command = "cd {$projectRoot} && wp plugin activate --all 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $io->success('All plugins activated');
            } else {
                $io->error('Failed to activate plugins: ' . implode("\n", $output));
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
                    $command = "cd {$projectRoot} && wp option update acf_pro_license '{$licenseKey}' 2>&1";
                    break;
                case 'gravityforms':
                    $command = "cd {$projectRoot} && wp option update rg_gforms_key '{$licenseKey}' 2>&1";
                    break;
                default:
                    $io->warning("Unknown license configuration for {$plugin}");
                    continue 2;
            }

            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $io->text("✓ {$plugin} license configured");
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

        $command = "cd {$projectRoot} && wp theme activate {$themeName} 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->success("Theme '{$themeName}' activated");
        } else {
            $io->error('Failed to activate theme: ' . implode("\n", $output));
        }
    }

    private function configureOptions(SymfonyStyle $io, string $projectRoot, array $options): void
    {
        $io->section('⚙️  Configuring WordPress options');

        foreach ($options as $key => $value) {
            $command = "cd {$projectRoot} && wp option update {$key} '{$value}' 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $io->text("✓ {$key} = {$value}");
            }
        }
    }

    private function searchReplace(SymfonyStyle $io, string $projectRoot, string $newUrl): void
    {
        $io->section('🔄 Running search-replace for URLs');

        // Get current URL
        $command = "cd {$projectRoot} && wp option get siteurl 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || empty($output[0])) {
            $io->warning('Could not detect current URL, skipping search-replace');
            return;
        }

        $oldUrl = trim($output[0]);
        
        if ($oldUrl === $newUrl) {
            $io->text('URLs match, skipping search-replace');
            return;
        }

        $io->text("Replacing {$oldUrl} → {$newUrl}");
        
        $command = "cd {$projectRoot} && wp search-replace '{$oldUrl}' '{$newUrl}' --all-tables 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->success('URLs replaced successfully');
        } else {
            $io->error('Failed to replace URLs: ' . implode("\n", $output));
        }
    }

    private function flushRewriteRules(SymfonyStyle $io, string $projectRoot): void
    {
        $io->section('🔄 Flushing rewrite rules');

        $command = "cd {$projectRoot} && wp rewrite flush 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $io->success('Rewrite rules flushed');
        }
    }
}
