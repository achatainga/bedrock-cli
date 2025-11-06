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
        $current = $profile['plugins']['public'] ?? [];
        
        if (!empty($current)) {
            $output->writeln('<comment>Plugins actuales:</comment>');
            foreach ($current as $plugin) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $version = is_array($plugin) ? $plugin['version'] : '*';
                $output->writeln("  • {$slug}:{$version}");
            }
            $output->writeln('');
        }
        
        $question = new ChoiceQuestion(
            '<fg=yellow>Acción:</> ',
            ['1' => 'Agregar más', '2' => 'Reemplazar todos', '0' => 'Cancelar'],
            '0'
        );
        $action = $helper->ask($input, $output, $question);
        
        if ($action === '0' || $action === 'Cancelar') {
            return;
        }
        
        $newPlugins = $this->searchWithCancelOption($input, $output, $helper, 'plugin');
        
        if ($action === '1' || $action === 'Agregar más') {
            $profile['plugins']['public'] = array_merge($current, $newPlugins);
        } else {
            $profile['plugins']['public'] = $newPlugins;
        }
    }

    private function editPremiumPlugins(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $current = $profile['plugins']['premium'] ?? [];
        
        if (!empty($current)) {
            $output->writeln('<comment>Plugins premium actuales:</comment>');
            foreach ($current as $plugin) {
                $output->writeln("  • {$plugin['name']}:{$plugin['version']} ({$plugin['source']})");
            }
            $output->writeln('');
        }
        
        $question = new ChoiceQuestion(
            '<fg=yellow>Acción:</> ',
            ['1' => 'Agregar más', '2' => 'Reemplazar todos', '0' => 'Cancelar'],
            '0'
        );
        $action = $helper->ask($input, $output, $question);
        
        if ($action === '0' || $action === 'Cancelar') {
            return;
        }
        
        $newPlugins = $this->selectPremiumPlugins($input, $output, $helper);
        
        if ($action === '1' || $action === 'Agregar más') {
            $profile['plugins']['premium'] = array_merge($current, $newPlugins);
        } else {
            $profile['plugins']['premium'] = $newPlugins;
        }
    }

    private function editCustomPlugins(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $current = $profile['plugins']['custom'] ?? [];
        
        if (!empty($current)) {
            $output->writeln('<comment>Plugins custom actuales:</comment>');
            foreach ($current as $slug) {
                $output->writeln("  • {$slug}");
            }
            $output->writeln('');
        }
        
        $question = new Question('Path a plugins custom [Enter para mantener]: ');
        $path = $helper->ask($input, $output, $question);
        
        if (!empty($path) && is_dir($path)) {
            $detected = $this->profileService->scanCustomPlugins($path);
            $profile['plugins']['custom'] = array_keys($detected);
            $output->writeln('<info>✓ ' . count($detected) . ' plugins detectados</info>');
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
