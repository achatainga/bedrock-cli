<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ImportProfileCommand extends Command
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
            ->setName('profile:import')
            ->setDescription('Importar profile desde archivo JSON')
            ->addArgument('path', InputArgument::REQUIRED, 'Path al archivo JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $helper = $this->getHelper('question');

        if (!file_exists($path)) {
            $output->writeln("<error>Archivo no encontrado: {$path}</error>");
            return Command::FAILURE;
        }

        $content = file_get_contents($path);
        $profile = json_decode($content, true);

        if (!$profile || !isset($profile['name'])) {
            $output->writeln('<error>JSON inválido o falta campo "name"</error>');
            return Command::FAILURE;
        }

        $name = $profile['name'];

        if ($this->profileService->profileExists($name)) {
            $question = new ConfirmationQuestion(
                "<question>El profile '{$name}' ya existe. ¿Sobrescribir? (Y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Importación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        $this->profileService->saveProfile($name, $profile);

        $output->writeln('');
        $output->writeln("<info>✅ Profile '{$name}' importado exitosamente</info>");
        $output->writeln('');
        $output->writeln('<comment>Ubicación:</comment> ' . $this->profileService->getProfilesPath() . "/{$name}.json");
        $output->writeln('');

        return Command::SUCCESS;
    }
}
