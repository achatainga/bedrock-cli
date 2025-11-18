<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ListCommand extends Command
{
    protected static $defaultName = 'profile:list';
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        parent::__construct();
        $this->profileService = $profileService;
    }

    protected function configure(): void
    {
        $this->setName('profile:list')
            ->setDescription('Listar profiles disponibles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profiles = $this->profileService->listProfiles();

        if (empty($profiles)) {
            $output->writeln('<comment>No hay profiles disponibles</comment>');
            $output->writeln('');
            $output->writeln('Crea uno con: <info>bedrock profile:create <name></info>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>📂 Profiles Disponibles</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Nombre', 'Descripción']);

        foreach ($profiles as $profile) {
            $table->addRow([
                $profile['name'],
                $profile['description']
            ]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln('<comment>Ver detalles:</comment> bedrock profile:show <name>');
        $output->writeln('<comment>Usar profile:</comment> bedrock new <proyecto> --profile=<name>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
