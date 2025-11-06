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
        $this->managePluginsInteractive($profile, $input, $output, $helper);
        $this->profileService->saveProfile($name, $profile);

        return Command::SUCCESS;
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
}
