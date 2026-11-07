<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddPluginCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this->setName('profile:add-plugin')
             ->setDescription('Agregar plugin a un profile')
             ->addArgument('profile', InputArgument::REQUIRED, 'Nombre del profile')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin')
             ->addOption('plugin-version', 'pv', InputOption::VALUE_OPTIONAL, 'Versión específica', '*');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profileName = $input->getArgument('profile');
        $slug = $input->getArgument('slug');
        $version = $input->getOption('plugin-version');

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);

        if (!isset($profile['plugins'])) {
            $profile['plugins'] = [];
        }

        if (isset($profile['plugins'][$slug])) {
            $output->writeln("<comment>Plugin '{$slug}' ya existe en el profile. Actualizando versión...</comment>");
        }

        $profile['plugins'][$slug] = $version;

        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Plugin '{$slug}' agregado al profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
