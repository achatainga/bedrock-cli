<?php

namespace Roots\BedrockCli\Commands\Plugin;

use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InfoCommand extends Command
{
    private WordPressApiService $wordPressApiService;
    private ProfileService $profileService;
    
    public function __construct(
        WordPressApiService $wordPressApiService,
        ProfileService $profileService
    ) {
        $this->wordPressApiService = $wordPressApiService;
        $this->profileService = $profileService;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setName('plugin:info')
            ->setDescription('Get detailed information about a WordPress plugin')
            ->addArgument('slug', InputArgument::REQUIRED, 'Plugin slug')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add plugin to this profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $input->getArgument('slug');
        $profileName = $input->getOption('profile');

        $plugin = $this->wordPressApiService->getPluginInfo($slug);

        if (!$plugin) {
            $output->writeln("<error>Plugin '{$slug}' not found.</error>");
            return Command::FAILURE;
        }

        $output->writeln("\n<info>=== {$plugin['name']} ===</info>");
        $output->writeln("<comment>Slug:</comment> {$plugin['slug']}");
        $output->writeln("<comment>Version:</comment> {$plugin['version']}");
        $output->writeln("<comment>Author:</comment> {$plugin['author']}");
        $output->writeln("<comment>Rating:</comment> {$plugin['rating']}% ({$plugin['num_ratings']} ratings)");
        $output->writeln("<comment>Active Installs:</comment> " . number_format($plugin['active_installs'] ?? 0));
        $output->writeln("<comment>Requires WordPress:</comment> {$plugin['requires']}");
        $output->writeln("<comment>Tested up to:</comment> {$plugin['tested']}");
        $output->writeln("<comment>Requires PHP:</comment> {$plugin['requires_php']}");
        
        if (!empty($plugin['homepage'])) {
            $output->writeln("<comment>Homepage:</comment> {$plugin['homepage']}");
        }
        
        $output->writeln("\n<comment>Description:</comment>");
        $output->writeln(strip_tags($plugin['short_description'] ?? ''));

        if (!empty($plugin['download_link'])) {
            $output->writeln("\n<comment>Download:</comment> {$plugin['download_link']}");
        }

        if (!$profileName) {
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $confirmQuestion = new ConfirmationQuestion("\n<question>Add this plugin to profile '{$profileName}'? (y/n):</question> ", false);
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            return Command::SUCCESS;
        }

        $versionQuestion = new Question("<question>Version constraint (default: *): </question>", '*');
        $version = $helper->ask($input, $output, $versionQuestion);

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' does not exist.</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);
        $profile['plugins']['public'][$slug] = $version;
        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Plugin added to profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
