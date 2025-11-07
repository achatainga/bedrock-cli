<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ExportProfileCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:export')
            ->setDescription('Exportar profile a archivo JSON')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path destino (default: ./profile-name.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $path = $input->getArgument('path');
        $helper = $this->getHelper('question');

        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        if (!$path) {
            $path = getcwd() . "/{$name}.json";
        }

        if (file_exists($path)) {
            $question = new ConfirmationQuestion(
                "<question>El archivo '{$path}' ya existe. ¿Sobrescribir? (Y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Exportación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        $profile = $this->profileService->loadProfile($name);
        file_put_contents($path, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' exportado exitosamente</info>");
        $output->writeln('');
        $output->writeln("<comment>Ubicación:</comment> {$path}");
        $output->writeln('');

        return Command::SUCCESS;
    }
}
