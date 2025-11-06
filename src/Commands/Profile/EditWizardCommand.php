<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;
use Roots\BedrockCli\Traits\PluginManagementTrait;
use Roots\BedrockCli\Traits\ThemeManagementTrait;
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
    use ThemeManagementTrait;
    
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

        while (true) {
            $this->displayMainMenu($profile, $output);
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === '1') {
                $this->editDescription($profile, $input, $output, $helper);
            } elseif ($action === '2') {
                $this->managePluginsInteractive($profile, $input, $output, $helper);
            } elseif ($action === '3') {
                $this->manageThemesInteractive($profile, $input, $output, $helper);
            }
            
            $output->writeln('');
        }

        $this->profileService->saveProfile($name, $profile);
        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' actualizado</info>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function displayMainMenu(array $profile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   ✏️  EDITAR PROFILE               <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln("<comment>Profile: {$profile['name']}</comment>");
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 📝 Descripción');
        $output->writeln('  <fg=cyan>[2]</> 📦 Plugins');
        $output->writeln('  <fg=cyan>[3]</> 🎨 Themes');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Guardar y salir');
        $output->writeln('');
    }

    private function editDescription(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $current = $profile['description'] ?? 'Sin descripción';
        $output->writeln("<comment>Actual: {$current}</comment>");
        $output->writeln('');
        
        $question = new Question('Nueva descripción [Enter para mantener]: ', $current);
        $profile['description'] = $helper->ask($input, $output, $question);
        $output->writeln('<info>✓ Descripción actualizada</info>');
    }

    protected function addNewPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 🌐 Público (WordPress.org)');
        $output->writeln('  <fg=cyan>[2]</> 💎 Premium (Repositorio privado)');
        $output->writeln('  <fg=cyan>[3]</> 🔧 Custom (Carpeta local)');
        $output->writeln('  <fg=cyan>[0]</> Cancelar');
        $output->writeln('');
        
        $question = new Question('> ');
        $type = trim($helper->ask($input, $output, $question));
        
        if ($type === '0') {
            return;
        }
        
        if ($type === '1') {
            $plugins = $this->searchWithCancelOption($input, $output, $helper, 'plugin');
            foreach ($plugins as $plugin) {
                $profile['plugins']['public'][] = $plugin;
            }
            $output->writeln('<info>✓ Plugins públicos agregados</info>');
        } elseif ($type === '2') {
            $plugins = $this->selectPremiumPlugins($input, $output, $helper);
            foreach ($plugins as $plugin) {
                $profile['plugins']['premium'][] = $plugin;
            }
            $output->writeln('<info>✓ Plugins premium agregados</info>');
        } elseif ($type === '3') {
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

    protected function addNewTheme(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan>[1]</> 🌐 Público (WordPress.org)');
        $output->writeln('  <fg=cyan>[2]</> 💎 Premium (Repositorio privado)');
        $output->writeln('  <fg=cyan>[0]</> Cancelar');
        $output->writeln('');
        
        $question = new Question('> ');
        $type = trim($helper->ask($input, $output, $question));
        
        if ($type === '0') {
            return;
        }
        
        if ($type === '1') {
            $themes = $this->searchWithCancelOption($input, $output, $helper, 'theme');
            foreach ($themes as $theme) {
                $profile['themes']['public'][] = $theme;
            }
            $output->writeln('<info>✓ Themes públicos agregados</info>');
        } elseif ($type === '2') {
            $themes = $this->selectPremiumThemes($input, $output, $helper);
            foreach ($themes as $theme) {
                $profile['themes']['premium'][] = $theme;
            }
            $output->writeln('<info>✓ Themes premium agregados</info>');
        }
    }
}
