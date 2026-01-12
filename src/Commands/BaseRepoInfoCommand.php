<?php

namespace Roots\BedrockCli\Commands;

use Roots\BedrockCli\Services\WordPressApiService;
use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class BaseRepoInfoCommand extends Command
{
    protected WordPressApiService $wordPressApiService;
    protected ProfileService $profileService;
    
    public function __construct(
        WordPressApiService $wordPressApiService,
        ProfileService $profileService
    ) {
        $this->wordPressApiService = $wordPressApiService;
        $this->profileService = $profileService;
        parent::__construct();
    }
    
    abstract protected function getItemType(): string; // 'plugin' o 'theme'

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this->setName("{$type}:info")
            ->setDescription("Obtener información detallada de un " . ($type === 'plugin' ? 'plugin' : 'tema') . " de WordPress.org")
            ->addArgument('slug', InputArgument::REQUIRED, ucfirst($type) . " slug")
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, "Agregar a este profile");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $this->getItemType();
        $slug = $input->getArgument('slug');
        $profileName = $input->getOption('profile');

        $method = "get" . ucfirst($type) . "Info";
        $item = $this->wordPressApiService->$method($slug);

        if (!$item) {
            $output->writeln("<error>" . ucfirst($type) . " '{$slug}' no encontrado.</error>");
            return Command::FAILURE;
        }

        $this->displayItemInfo($output, $item, $type);

        if (!$profileName) {
            return Command::SUCCESS;
        }

        return $this->handleProfileAddition($input, $output, $profileName, $slug, $type);
    }

    protected function displayItemInfo(OutputInterface $output, array $item, string $type): void
    {
        $output->writeln("\n<info>=== {$item['name']} ===</info>");
        $output->writeln("<comment>Slug:</comment> {$item['slug']}");
        $output->writeln("<comment>Version:</comment> {$item['version']}");
        
        $author = is_array($item['author']) ? ($item['author']['display_name'] ?? 'Unknown') : $item['author'];
        $output->writeln("<comment>Author:</comment> {$author}");
        
        $output->writeln("<comment>Rating:</comment> {$item['rating']}% ({$item['num_ratings']} ratings)");
        $output->writeln("<comment>Active Installs:</comment> " . number_format($item['active_installs'] ?? 0));
        
        if ($type === 'plugin') {
            $output->writeln("<comment>Requires WordPress:</comment> {$item['requires']}");
            $output->writeln("<comment>Tested up to:</comment> {$item['tested']}");
        }
        
        if (!empty($item['homepage'])) {
            $output->writeln("<comment>Homepage:</comment> {$item['homepage']}");
        }
        
        $output->writeln("\n<comment>Description:</comment>");
        $desc = $type === 'plugin' ? ($item['short_description'] ?? '') : ($item['description'] ?? '');
        $output->writeln(strip_tags($desc));

        if (!empty($item['download_link'])) {
            $output->writeln("\n<comment>Download:</comment> {$item['download_link']}");
        }
    }

    abstract protected function handleProfileAddition(InputInterface $input, OutputInterface $output, string $profileName, string $slug, string $type): int;
}
