<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class EditCommand extends Command
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
            ->setName('profile:edit')
            ->setDescription('Editar un profile en el editor del sistema')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        $profilePath = $this->profileService->getProfilesPath() . "/{$name}.json";
        
        // Detectar editor
        $editor = getenv('EDITOR') ?: (PHP_OS_FAMILY === 'Windows' ? 'notepad' : 'nano');
        
        $output->writeln("<info>Abriendo {$name}.json en {$editor}...</info>");
        
        // Abrir editor
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['cmd', '/c', 'start', '', '/wait', $editor, $profilePath]);
        } else {
            $process = new Process([$editor, $profilePath]);
        }
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al abrir el editor</error>');
            return Command::FAILURE;
        }

        // Validar JSON
        try {
            $this->profileService->loadProfile($name);
            $output->writeln("<info>✅ Profile '{$name}' actualizado correctamente</info>");
        } catch (\RuntimeException $e) {
            $output->writeln("<error>❌ JSON inválido: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
