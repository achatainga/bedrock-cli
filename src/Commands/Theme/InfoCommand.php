<?php

namespace Roots\BedrockCli\Commands\Theme;

use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
        $this->setName('theme:info')
            ->setDescription('Get detailed information about a WordPress theme')
            ->addArgument('slug', InputArgument::REQUIRED, 'Theme slug')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add theme to this profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $input->getArgument('slug');
        $profileName = $input->getOption('profile');

        $theme = $this->wordPressApiService->getThemeInfo($slug);

        if (!$theme) {
            $output->writeln("<error>Theme '{$slug}' not found.</error>");
            return Command::FAILURE;
        }

        $output->writeln("\n<info>=== {$theme['name']} ===</info>");
        $output->writeln("<comment>Slug:</comment> {$theme['slug']}");
        $output->writeln("<comment>Version:</comment> {$theme['version']}");
        $output->writeln("<comment>Author:</comment> {$theme['author']['display_name']}");
        $output->writeln("<comment>Rating:</comment> {$theme['rating']}% ({$theme['num_ratings']} ratings)");
        $output->writeln("<comment>Active Installs:</comment> " . number_format($theme['active_installs'] ?? 0));
        
        if (!empty($theme['homepage'])) {
            $output->writeln("<comment>Homepage:</comment> {$theme['homepage']}");
        }
        
        $output->writeln("\n<comment>Description:</comment>");
        $output->writeln(strip_tags($theme['description'] ?? ''));

        if (!empty($theme['download_link'])) {
            $output->writeln("\n<comment>Download:</comment> {$theme['download_link']}");
        }

        if (!$profileName) {
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $confirmQuestion = new ConfirmationQuestion("\n<question>Add this theme to profile '{$profileName}'? (y/n):</question> ", false);
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            return Command::SUCCESS;
        }

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' does not exist.</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);
        $profile['theme']['name'] = $slug;
        $profile['theme']['type'] = 'public';
        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Theme added to profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
