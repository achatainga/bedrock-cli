<?php

namespace Roots\BedrockCli\Commands\Plugin;

use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class SearchCommand extends Command
{
    use InteractiveSearchTrait;
{
    protected function configure(): void
    {
        $this->setName('plugin:search')
            ->setDescription('Search for WordPress plugins interactively')
            ->addArgument('query', InputArgument::REQUIRED, 'Search query')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Add selected plugins to this profile')
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
        $output->writeln("<info>Searching for plugins: {$query}...</info>");

        $result = $apiService->searchPlugins($query, $page, $perPage);

        if (empty($result['plugins'])) {
            $output->writeln('<error>No plugins found.</error>');
            return Command::FAILURE;
        }

        $plugins = $result['plugins'];
        $total = $result['info']['results'] ?? 0;

        $output->writeln("<comment>Found {$total} plugins (showing page {$page}):</comment>\n");

        $this->displayPluginsTable($plugins, $output);

        if (!$profileName) {
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $question = new Question("\n<question>Select plugins to add (comma-separated numbers, e.g., 1,3,5) or press Enter to skip:</question> ");
        $selection = $helper->ask($input, $output, $question);

        if (empty($selection)) {
            return Command::SUCCESS;
        }

        $selected = array_map('trim', explode(',', $selection));
        $profileService = new ProfileService();

        if (!$profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' does not exist.</error>");
            return Command::FAILURE;
        }

        $profile = $profileService->loadProfile($profileName);

        foreach ($selected as $num) {
            $index = (int) $num - 1;
            if (!isset($plugins[$index])) {
                continue;
            }

            $plugin = $plugins[$index];
            $slug = $plugin['slug'];

            $version = $this->selectPluginVersion($slug, $helper, $input, $output);

            $profile['plugins']['public'][$slug] = $version;
            $output->writeln("<info>✓ Added {$slug} ({$version})</info>");
        }

        $profileService->saveProfile($profileName, $profile);
        $output->writeln("\n<info>Profile '{$profileName}' updated successfully!</info>");

        return Command::SUCCESS;
    }
}
