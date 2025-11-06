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
use Symfony\Component\Console\Question\ConfirmationQuestion;

class EditWizardCommand extends Command
{
    use InteractiveSearchTrait;
    use PremiumAssetsTrait;
    
    protected static $defaultName = 'profile:edit-wizard';
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
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

        $output->writeln('');
        $output->writeln('<info>╔════════════════════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║           ✏️  EDITAR PROFILE WIZARD                            ║</info>');
        $output->writeln('<info>╚════════════════════════════════════════════════════════════════╝</info>');
        $output->writeln('');
        $output->writeln("<comment>Profile: {$name}</comment>");
        $output->writeln('');

        // Menú de secciones
        while (true) {
            $sectionQuestion = new ChoiceQuestion(
                '<fg=yellow>¿Qué deseas editar?</> ',
                [
                    '1' => 'Descripción',
                    '2' => 'Plugins públicos',
                    '3' => 'Plugins premium',
                    '4' => 'Plugins custom',
                    '5' => 'Tema',
                    '0' => 'Guardar y salir'
                ],
                '0'
            );
            
            $section = $helper->ask($input, $output, $sectionQuestion);
            
            if ($section === '0' || $section === 'Guardar y salir') {
                break;
            }
            
            $output->writeln('');
            
            match($section) {
                '1', 'Descripción' => $this->editDescription($profile, $input, $output, $helper),
                '2', 'Plugins públicos' => $this->editPublicPlugins($profile, $input, $output, $helper),
                '3', 'Plugins premium' => $this->editPremiumPlugins($profile, $input, $output, $helper),
                '4', 'Plugins custom' => $this->editCustomPlugins($profile, $input, $output, $helper),
                '5', 'Tema' => $this->editTheme($profile, $input, $output, $helper),
                default => null
            };
            
            $output->writeln('');
        }

        // Guardar cambios
        $this->profileService->saveProfile($name, $profile);

        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' actualizado exitosamente</info>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function editDescription(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $current = $profile['description'] ?? 'Sin descripción';
        $output->writeln("<comment>Actual: {$current}</comment>");
        
        $question = new Question('Nueva descripción [Enter para mantener]: ', $current);
        $profile['description'] = $helper->ask($input, $output, $question);
    }

    private function editPublicPlugins(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        while (true) {
            $current = $profile['plugins']['public'] ?? [];
            
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║   🌐 PLUGINS PÚBLICOS                ║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            if (!empty($current)) {
                $output->writeln('<comment>Plugins actuales (' . count($current) . '):</comment>');
                foreach ($current as $index => $plugin) {
                    $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                    $version = is_array($plugin) ? $plugin['version'] : '*';
                    $num = $index + 1;
                    $output->writeln("  <fg=cyan>[{$num}]</> {$slug}:{$version}");
                }
                $output->writeln('');
            } else {
                $output->writeln('<comment>(ninguno)</comment>');
                $output->writeln('');
            }
            
            $output->writeln('<comment>Acciones:</comment>');
            $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo plugin');
            if (!empty($current)) {
                $output->writeln('  <fg=cyan>[E]</> ✏️  Editar plugin (número)');
                $output->writeln('  <fg=cyan>[D]</> 🗑️  Eliminar plugin (número)');
            }
            $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
            $output->writeln('');
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $newPlugins = $this->searchWithCancelOption($input, $output, $helper, 'plugin');
                $profile['plugins']['public'] = array_merge($current, $newPlugins);
                $output->writeln('<info>✓ Plugins agregados</info>');
            } elseif ($action === 'E' && !empty($current)) {
                $question = new Question('Número de plugin a editar: ');
                $num = (int)$helper->ask($input, $output, $question);
                if ($num > 0 && $num <= count($current)) {
                    $this->editSinglePlugin($profile['plugins']['public'][$num - 1], $input, $output, $helper);
                }
            } elseif ($action === 'D' && !empty($current)) {
                $question = new Question('Número de plugin a eliminar: ');
                $num = (int)$helper->ask($input, $output, $question);
                if ($num > 0 && $num <= count($current)) {
                    $removed = array_splice($profile['plugins']['public'], $num - 1, 1);
                    $slug = is_array($removed[0]) ? $removed[0]['slug'] : $removed[0];
                    $output->writeln("<info>✓ Plugin '{$slug}' eliminado</info>");
                }
            }
            
            $output->writeln('');
        }
    }
    
    private function editSinglePlugin(array &$plugin, InputInterface $input, OutputInterface $output, $helper): void
    {
        $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
        $version = is_array($plugin) ? $plugin['version'] : '*';
        
        $output->writeln('');
        $output->writeln("<comment>Editando: {$slug}:{$version}</comment>");
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> Cambiar versión');
        $output->writeln('  <fg=cyan>[2]</> Cambiar slug');
        $output->writeln('  <fg=cyan>[0]</> Volver');
        $output->writeln('');
        
        $question = new Question('> ');
        $choice = trim($helper->ask($input, $output, $question));
        
        if ($choice === '1') {
            $question = new Question("Nueva versión [{$version}]: ", $version);
            $newVersion = $helper->ask($input, $output, $question);
            if (is_array($plugin)) {
                $plugin['version'] = $newVersion;
            } else {
                $plugin = ['slug' => $slug, 'version' => $newVersion];
            }
        } elseif ($choice === '2') {
            $question = new Question("Nuevo slug [{$slug}]: ", $slug);
            $newSlug = $helper->ask($input, $output, $question);
            if (is_array($plugin)) {
                $plugin['slug'] = $newSlug;
            } else {
                $plugin = $newSlug;
            }
        }
    }

    private function editPremiumPlugins(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        while (true) {
            $current = $profile['plugins']['premium'] ?? [];
            
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║   💎 PLUGINS PREMIUM                 ║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            if (!empty($current)) {
                $output->writeln('<comment>Plugins actuales (' . count($current) . '):</comment>');
                foreach ($current as $index => $plugin) {
                    $num = $index + 1;
                    $output->writeln("  <fg=cyan>[{$num}]</> {$plugin['name']}:{$plugin['version']} ({$plugin['source']})");
                }
                $output->writeln('');
            } else {
                $output->writeln('<comment>(ninguno)</comment>');
                $output->writeln('');
            }
            
            $output->writeln('<comment>Acciones:</comment>');
            $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo plugin');
            if (!empty($current)) {
                $output->writeln('  <fg=cyan>[D]</> 🗑️  Eliminar plugin (número)');
            }
            $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
            $output->writeln('');
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $newPlugins = $this->selectPremiumPlugins($input, $output, $helper);
                $profile['plugins']['premium'] = array_merge($current, $newPlugins);
                $output->writeln('<info>✓ Plugins agregados</info>');
            } elseif ($action === 'D' && !empty($current)) {
                $question = new Question('Número de plugin a eliminar: ');
                $num = (int)$helper->ask($input, $output, $question);
                if ($num > 0 && $num <= count($current)) {
                    $removed = array_splice($profile['plugins']['premium'], $num - 1, 1);
                    $output->writeln("<info>✓ Plugin '{$removed[0]['name']}' eliminado</info>");
                }
            }
            
            $output->writeln('');
        }
    }

    private function editCustomPlugins(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        while (true) {
            $current = $profile['plugins']['custom'] ?? [];
            
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║   🔧 PLUGINS CUSTOM                  ║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            if (!empty($current)) {
                $output->writeln('<comment>Plugins actuales (' . count($current) . '):</comment>');
                foreach ($current as $index => $slug) {
                    $num = $index + 1;
                    $output->writeln("  <fg=cyan>[{$num}]</> {$slug}");
                }
                $output->writeln('');
            } else {
                $output->writeln('<comment>(ninguno)</comment>');
                $output->writeln('');
            }
            
            $output->writeln('<comment>Acciones:</comment>');
            $output->writeln('  <fg=cyan>[A]</> ➕ Escanear carpeta (detectar plugins)');
            if (!empty($current)) {
                $output->writeln('  <fg=cyan>[D]</> 🗑️  Eliminar plugin (número)');
            }
            $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
            $output->writeln('');
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $question = new Question('Path a carpeta de plugins: ');
                $path = $helper->ask($input, $output, $question);
                
                if (!empty($path) && is_dir($path)) {
                    $detected = $this->profileService->scanCustomPlugins($path);
                    $profile['plugins']['custom'] = array_merge($current, array_keys($detected));
                    $output->writeln('<info>✓ ' . count($detected) . ' plugins detectados</info>');
                } else {
                    $output->writeln('<error>Carpeta no válida</error>');
                }
            } elseif ($action === 'D' && !empty($current)) {
                $question = new Question('Número de plugin a eliminar: ');
                $num = (int)$helper->ask($input, $output, $question);
                if ($num > 0 && $num <= count($current)) {
                    $removed = array_splice($profile['plugins']['custom'], $num - 1, 1);
                    $output->writeln("<info>✓ Plugin '{$removed[0]}' eliminado</info>");
                }
            }
            
            $output->writeln('');
        }
    }

    private function editTheme(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $current = $profile['theme'] ?? [];
        
        if (!empty($current)) {
            $output->writeln('<comment>Tema actual:</comment>');
            $output->writeln("  • {$current['name']} ({$current['type']})");
            $output->writeln('');
        }
        
        $question = new ConfirmationQuestion('¿Cambiar tema? (Y/n): ', false);
        if (!$helper->ask($input, $output, $question)) {
            return;
        }
        
        $premiumTheme = $this->selectPremiumTheme($input, $output, $helper);
        
        if ($premiumTheme) {
            $profile['theme'] = [
                'name' => $premiumTheme['name'],
                'type' => 'premium',
                'source' => $premiumTheme['source'],
                'url' => $premiumTheme['url'] ?? null
            ];
        } else {
            $question = new Question('Nombre del tema público: ', 'twentytwentyfour');
            $themeName = $helper->ask($input, $output, $question);
            $profile['theme'] = [
                'name' => $themeName,
                'type' => 'public',
                'source' => 'public'
            ];
        }
    }
}
