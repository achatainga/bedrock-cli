<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Roots\BedrockCli\Traits\PluginManagementTrait;
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
                '2', 'Plugins públicos', '3', 'Plugins premium', '4', 'Plugins custom' => $this->managePluginsInteractive($profile, $input, $output, $helper),
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

    protected function addNewPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
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
