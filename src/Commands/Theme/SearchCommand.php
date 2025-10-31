<?php

namespace BedrockCli\Commands\Theme;

use BedrockCli\Services\WordPressApiService;
use BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;

class SearchCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('theme:search')
            ->setDescription('Search for WordPress themes interactively')
            ->addArgument('query', InputArgument::REQUIRED, 'Search query')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add selected theme to this profile')
            ->addOption('page', null, InputOption::VALUE_OPTIONAL, 'Page number', 1)
            ->addOption('per-page', null, InputOption::VALUE_OPTIONAL, 'Results per page', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');
        $page = (int) $input->getOption('page');
        $perPage = (int) $input->getOption('per-page');
        $profileName = $input->getOption('profile');

        $apiService = new WordPressApiService();
        $output->writeln("<info>Searching for themes: {$query}...</info>");

        $result = $apiService->searchThemes($query, $page, $perPage);

        if (empty($result['themes'])) {
            $output->writeln('<error>No themes found.</error>');
            return Command::FAILURE;
        }

        $themes = $result['themes'];
        $total = $result['info']['results'] ?? 0;

        $output->writeln("<comment>Found {$total} themes (showing page {$page}):</comment>\n");

        $table = new Table($output);
        $table->setHeaders(['#', 'Name', 'Slug', 'Rating', 'Version']);

        foreach ($themes as $index => $theme) {
            $table->addRow([
                $index + 1,
                $theme['name'],
                $theme['slug'],
                ($theme['rating'] ?? 0) . '%',
                $theme['version'] ?? 'N/A'
            ]);
        }

        $table->render();

        if (!$profileName) {
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $question = new Question("\n<question>Select theme number to add or press Enter to skip:</question> ");
        $selection = $helper->ask($input, $output, $question);

        if (empty($selection)) {
            return Command::SUCCESS;
        }

        $index = (int) $selection - 1;
        if (!isset($themes[$index])) {
            $output->writeln('<error>Invalid selection.</error>');
            return Command::FAILURE;
        }

        $theme = $themes[$index];
        $slug = $theme['slug'];

        $profileService = new ProfileService();

        if (!$profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' does not exist.</error>");
            return Command::FAILURE;
        }

        $profile = $profileService->loadProfile($profileName);
        $profile['theme']['name'] = $slug;
        $profile['theme']['type'] = 'public';
        $profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Theme '{$slug}' added to profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
