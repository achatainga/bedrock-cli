<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
{
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:export')
            ->setDescription('Exportar profile desde un proyecto existente')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre para el profile exportado');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        // Detectar proyecto Bedrock
        $projectRoot = $this->detectProjectRoot();
        if (!$projectRoot) {
            $output->writeln('<error>No estás en un proyecto Bedrock</error>');
            return Command::FAILURE;
        }

        $profilePath = "{$projectRoot}/.bedrock/profile.json";
        
        if (!file_exists($profilePath)) {
            $output->writeln('<error>Este proyecto no tiene un profile asociado</error>');
            return Command::FAILURE;
        }

        if ($this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' ya existe</error>");
            return Command::FAILURE;
        }

        $profile = json_decode(file_get_contents($profilePath), true);
        $profile['name'] = $name;
        
        $this->profileService->saveProfile($name, $profile);
        
        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' exportado exitosamente</info>");
        $output->writeln('');
        $output->writeln('<comment>Ubicación:</comment> ' . $this->profileService->getProfilesPath() . "/{$name}.json");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function detectProjectRoot(): ?string
    {
        $current = getcwd();
        
        while ($current !== dirname($current)) {
            if (file_exists("{$current}/web/wp-config.php") || file_exists("{$current}/config/application.php")) {
                return $current;
            }
            $current = dirname($current);
        }
        
        return null;
    }
}
