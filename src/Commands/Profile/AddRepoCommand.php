<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddRepoCommand extends Command
{
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('profile:add-repo')
             ->setDescription('Agregar repositorio a un profile')
             ->addArgument('profile', InputArgument::REQUIRED, 'Nombre del profile')
             ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Tipo de repositorio (vcs, composer, path)')
             ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL del repositorio')
             ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nombre del repositorio');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profileName = $input->getArgument('profile');
        $type = $input->getOption('type');
        $url = $input->getOption('url');
        $name = $input->getOption('name');

        if (!$type || !$url) {
            $output->writeln('<error>Opciones --type y --url son requeridas</error>');
            return Command::FAILURE;
        }

        if (!in_array($type, ['vcs', 'composer', 'path'])) {
            $output->writeln('<error>Tipo debe ser: vcs, composer o path</error>');
            return Command::FAILURE;
        }

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);

        if (!isset($profile['repositories'])) {
            $profile['repositories'] = [];
        }

        $repo = ['type' => $type, 'url' => $url];
        
        if ($name) {
            $repo['name'] = $name;
        }

        $profile['repositories'][] = $repo;

        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Repositorio agregado al profile '{$profileName}'</info>");
        $output->writeln("  Tipo: {$type}");
        $output->writeln("  URL: {$url}");

        return Command::SUCCESS;
    }
}
