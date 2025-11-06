<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ManagePluginsCommand extends Command
{
    use InteractiveSearchTrait;
    use PremiumAssetsTrait;
    
    protected static $defaultName = 'profile:manage-plugins';
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:manage-plugins')
            ->setDescription('Gestión CRUD completa de plugins en profile')
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
            $this->showPluginsMenu($profile, $input, $output, $helper);
            
            $question = new Question('<fg=yellow>Opción [A/E/D/C/0]:</> ', '0');
            $action = strtoupper($helper->ask($input, $output, $question));
            
            $output->writeln('');
            
            if ($action === '0') {
                break;
            }
            
            match($action) {
                'A' => $this->addPlugin($profile, $input, $output, $helper),
                'E' => $this->editPlugin($profile, $input, $output, $helper),
                'D' => $this->deletePlugin($profile, $input, $output, $helper),
                'C' => $this->changeSource($profile, $input, $output, $helper),
                default => $output->writeln('<error>Opción inválida</error>')
            };
            
            $this->profileService->saveProfile($name, $profile);
        }

        return Command::SUCCESS;
    }

    private function showPluginsMenu(array $profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>   📦 GESTIÓN DE PLUGINS          </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $publicPlugins = $profile['plugins']['public'] ?? [];
        $premiumPlugins = $profile['plugins']['premium'] ?? [];
        $customPlugins = $profile['plugins']['custom'] ?? [];
        
        $index = 1;
        
        $output->writeln('<fg=green>🌐 PÚBLICOS (' . count($publicPlugins) . ') - WordPress.org</>');
        if (empty($publicPlugins)) {
            $output->writeln('  <comment>(ninguno)</comment>');
        } else {
            foreach ($publicPlugins as $plugin) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $version = is_array($plugin) ? $plugin['version'] : '*';
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}:{$version}");
                $index++;
            }
        }
        $output->writeln('');
        
        $output->writeln('<fg=magenta>💎 PREMIUM (' . count($premiumPlugins) . ') - Repositorios privados</>');
        if (empty($premiumPlugins)) {
            $output->writeln('  <comment>(ninguno)</comment>');
        } else {
            foreach ($premiumPlugins as $plugin) {
                $source = $plugin['source'] ?? 'vcs';
                $output->writeln("  <fg=cyan>[{$index}]</> {$plugin['name']}:{$plugin['version']} ({$source})");
                $index++;
            }
        }
        $output->writeln('');
        
        $output->writeln('<fg=yellow>🔧 CUSTOM (' . count($customPlugins) . ') - Carpeta local</>');
        if (empty($customPlugins)) {
            $output->writeln('  <comment>(ninguno)</comment>');
        } else {
            foreach ($customPlugins as $slug) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}");
                $index++;
            }
        }
        $output->writeln('');
        
        $output->writeln('<fg=cyan>Acciones:</>');
        $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo plugin');
        $output->writeln('  <fg=cyan>[E]</> ✏️  Editar plugin (cambiar versión, URL, tipo)');
        $output->writeln('  <fg=cyan>[D]</> 🗑️  Eliminar plugin');
        $output->writeln('  <fg=cyan>[C]</> 🔄 Cambiar fuente (público ↔ premium ↔ custom)');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
    }

    private function addPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $typeQuestion = new ChoiceQuestion(
            '<fg=yellow>Tipo de plugin:</> ',
            [
                '1' => '🌐 Público (WordPress.org)',
                '2' => '💎 Premium (Repositorio privado)',
                '3' => '🔧 Custom (Carpeta local)',
                '0' => 'Cancelar'
            ],
            '0'
        );
        
        $type = $helper->ask($input, $output, $typeQuestion);
        
        if ($type === '0' || $type === 'Cancelar') {
            return;
        }
        
        if ($type === '1' || strpos($type, 'Público') !== false) {
            $plugins = $this->searchWithCancelOption($input, $output, $helper, 'plugin');
            foreach ($plugins as $plugin) {
                $profile['plugins']['public'][] = $plugin;
            }
            $output->writeln('<info>✓ Plugins públicos agregados</info>');
        } elseif ($type === '2' || strpos($type, 'Premium') !== false) {
            $plugins = $this->selectPremiumPlugins($input, $output, $helper);
            foreach ($plugins as $plugin) {
                $profile['plugins']['premium'][] = $plugin;
            }
            $output->writeln('<info>✓ Plugins premium agregados</info>');
        } else {
            $pathQuestion = new Question('<fg=yellow>Path a carpeta de plugins custom:</> ');
            $path = $helper->ask($input, $output, $pathQuestion);
            
            if (!empty($path) && is_dir($path)) {
                $detected = $this->profileService->scanCustomPlugins($path);
                foreach (array_keys($detected) as $slug) {
                    $profile['plugins']['custom'][] = $slug;
                }
                $output->writeln('<info>✓ ' . count($detected) . ' plugins custom agregados</info>');
            }
        }
    }

    private function editPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $numQuestion = new Question('<fg=yellow>Número de plugin a editar:</> ');
        $num = (int)$helper->ask($input, $output, $numQuestion);
        
        $plugin = $this->getPluginByIndex($profile, $num);
        
        if (!$plugin) {
            $output->writeln('<error>Plugin no encontrado</error>');
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Editando: {$plugin['display']}</info>");
        $output->writeln('');
        
        $editQuestion = new ChoiceQuestion(
            '<fg=yellow>¿Qué deseas editar?</> ',
            [
                '1' => 'Cambiar versión',
                '2' => 'Cambiar slug/nombre',
                '0' => 'Cancelar'
            ],
            '0'
        );
        
        $editAction = $helper->ask($input, $output, $editQuestion);
        
        if ($editAction === '1' || strpos($editAction, 'versión') !== false) {
            $versionQuestion = new Question("<fg=yellow>Nueva versión [{$plugin['version']}]:</> ", $plugin['version']);
            $newVersion = $helper->ask($input, $output, $versionQuestion);
            $this->updatePluginVersion($profile, $num, $newVersion);
            $output->writeln('<info>✓ Versión actualizada</info>');
        }
    }

    private function deletePlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $numQuestion = new Question('<fg=yellow>Número de plugin a eliminar:</> ');
        $num = (int)$helper->ask($input, $output, $numQuestion);
        
        $plugin = $this->getPluginByIndex($profile, $num);
        
        if (!$plugin) {
            $output->writeln('<error>Plugin no encontrado</error>');
            return;
        }
        
        $confirmQuestion = new Question("<fg=yellow>¿Eliminar {$plugin['display']}? (s/N):</> ", 'n');
        $confirm = strtolower($helper->ask($input, $output, $confirmQuestion));
        
        if ($confirm === 's') {
            $this->removePluginByIndex($profile, $num);
            $output->writeln('<info>✓ Plugin eliminado</info>');
        }
    }

    private function changeSource(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('<comment>Función en desarrollo</comment>');
    }

    private function getPluginByIndex(array $profile, int $index): ?array
    {
        $currentIndex = 1;
        
        foreach ($profile['plugins']['public'] ?? [] as $plugin) {
            if ($currentIndex === $index) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $version = is_array($plugin) ? $plugin['version'] : '*';
                return ['type' => 'public', 'slug' => $slug, 'version' => $version, 'display' => "{$slug}:{$version}"];
            }
            $currentIndex++;
        }
        
        foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
            if ($currentIndex === $index) {
                return ['type' => 'premium', 'slug' => $plugin['name'], 'version' => $plugin['version'], 'display' => "{$plugin['name']}:{$plugin['version']}"];
            }
            $currentIndex++;
        }
        
        foreach ($profile['plugins']['custom'] ?? [] as $slug) {
            if ($currentIndex === $index) {
                return ['type' => 'custom', 'slug' => $slug, 'version' => '*', 'display' => $slug];
            }
            $currentIndex++;
        }
        
        return null;
    }

    private function updatePluginVersion(array &$profile, int $index, string $newVersion): void
    {
        $currentIndex = 1;
        
        foreach ($profile['plugins']['public'] as &$plugin) {
            if ($currentIndex === $index) {
                if (is_array($plugin)) {
                    $plugin['version'] = $newVersion;
                } else {
                    $plugin = ['slug' => $plugin, 'version' => $newVersion];
                }
                return;
            }
            $currentIndex++;
        }
        
        foreach ($profile['plugins']['premium'] as &$plugin) {
            if ($currentIndex === $index) {
                $plugin['version'] = $newVersion;
                return;
            }
            $currentIndex++;
        }
    }

    private function removePluginByIndex(array &$profile, int $index): void
    {
        $currentIndex = 1;
        
        foreach ($profile['plugins']['public'] as $key => $plugin) {
            if ($currentIndex === $index) {
                unset($profile['plugins']['public'][$key]);
                $profile['plugins']['public'] = array_values($profile['plugins']['public']);
                return;
            }
            $currentIndex++;
        }
        
        foreach ($profile['plugins']['premium'] as $key => $plugin) {
            if ($currentIndex === $index) {
                unset($profile['plugins']['premium'][$key]);
                $profile['plugins']['premium'] = array_values($profile['plugins']['premium']);
                return;
            }
            $currentIndex++;
        }
        
        foreach ($profile['plugins']['custom'] as $key => $slug) {
            if ($currentIndex === $index) {
                unset($profile['plugins']['custom'][$key]);
                $profile['plugins']['custom'] = array_values($profile['plugins']['custom']);
                return;
            }
            $currentIndex++;
        }
    }
}
