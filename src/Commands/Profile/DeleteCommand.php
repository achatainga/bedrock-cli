<?php

namespace BedrockCli\Commands\Profile;

use BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCommand extends Command
{
    protected static $defaultName = 'profile:delete';
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Eliminar un profile')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $helper = $this->getHelper('question');

        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        if ($name === 'default') {
            $output->writeln('<error>No se puede eliminar el profile default</error>');
            return Command::FAILURE;
        }

        $question = new ConfirmationQuestion(
            "<question>¿Estás seguro de eliminar el profile '{$name}'? (y/N):</question> ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }

        try {
            $this->profileService->deleteProfile($name);
            $output->writeln('');
            $output->writeln("<info>✅ Profile '{$name}' eliminado exitosamente</info>");
            $output->writeln('');
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
