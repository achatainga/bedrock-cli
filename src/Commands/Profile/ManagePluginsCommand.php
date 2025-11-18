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

class ManagePluginsCommand extends Command
{
    use InteractiveSearchTrait;
    use PremiumAssetsTrait;
    use PluginManagementTrait;
    
    protected static $defaultName = 'profile:manage-plugins';
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
        parent::__construct();
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
        $this->managePluginsInteractive($profile, $input, $output, $helper);
        $this->profileService->saveProfile($name, $profile);

        return Command::SUCCESS;
    }

    protected function getProfileService()
    {
        return $this->profileService;
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
            $added = 0;
            foreach ($plugins as $plugin) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $validation = $this->validateNoDuplicatePlugin($profile, $slug, 'public');
                if (!$validation['valid']) {
                    $output->writeln("<error>{$validation['message']}</error>");
                    continue;
                }
                $profile['plugins']['public'][] = $plugin;
                $added++;
            }
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) público(s) agregado(s)</info>");
            }
        } elseif ($type === '2' || strpos($type, 'Premium') !== false) {
            $plugins = $this->selectPremiumPlugins($input, $output, $helper);
            $added = 0;
            foreach ($plugins as $plugin) {
                $validation = $this->validateNoDuplicatePlugin($profile, $plugin['name'], 'premium');
                if (!$validation['valid']) {
                    $output->writeln("<error>{$validation['message']}</error>");
                    continue;
                }
                $profile['plugins']['premium'][] = $plugin;
                $added++;
            }
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) premium agregado(s)</info>");
            }
        } else {
            $added = $this->selectCustomPluginsInteractive($profile, $input, $output, $helper);
            if ($added > 0) {
                $output->writeln("<info>✓ {$added} plugin(s) custom agregado(s)</info>");
            }
        }
    }
}
