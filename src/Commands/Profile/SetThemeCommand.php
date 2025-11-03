<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetThemeCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this->setName('profile:set-theme')
             ->setDescription('Establecer theme de un profile')
             ->addArgument('profile', InputArgument::REQUIRED, 'Nombre del profile')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del theme')
             ->addOption('theme-version', 'v', InputOption::VALUE_OPTIONAL, 'Versión específica', '*');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profileName = $input->getArgument('profile');
        $slug = $input->getArgument('slug');
        $version = $input->getOption('theme-version');

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);

        if (isset($profile['theme'])) {
            $oldTheme = $profile['theme'];
            $output->writeln("<comment>Reemplazando theme '{$oldTheme}' con '{$slug}'</comment>");
        }

        $profile['theme'] = $slug;
        $profile['theme_version'] = $version;

        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Theme '{$slug}' establecido en profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
