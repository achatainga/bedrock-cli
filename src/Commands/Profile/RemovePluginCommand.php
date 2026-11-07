<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemovePluginCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this->setName('profile:remove-plugin')
             ->setDescription('Remover plugin de un profile')
             ->addArgument('profile', InputArgument::REQUIRED, 'Nombre del profile')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profileName = $input->getArgument('profile');
        $slug = $input->getArgument('slug');

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);

        if (!isset($profile['plugins'][$slug])) {
            $output->writeln("<error>Plugin '{$slug}' no existe en el profile</error>");
            return Command::FAILURE;
        }

        unset($profile['plugins'][$slug]);

        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Plugin '{$slug}' removido del profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
