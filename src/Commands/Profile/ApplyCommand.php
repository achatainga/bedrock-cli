<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ApplyCommand extends Command
{
    use ProjectSelectorTrait;
    
    private ProfileService $profileService;
    private ComposerService $composerService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
        $this->composerService = new ComposerService();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:apply')
            ->setDescription('Aplicar un profile a un proyecto existente')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile')
            ->addOption('yes', 'y', null, 'Confirmar automáticamente sin preguntar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }
        
        $projectRoot = getcwd();

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "<question>¿Aplicar profile '{$name}' a este proyecto? Esto modificará composer.json (Y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        $profile = $this->profileService->loadProfile($name);
        
        $output->writeln('');
        $output->writeln('<info>Aplicando profile...</info>');
        
        // IMPORTANTE: Regenerar composer.json ANTES de copiar el nuevo profile
        // para que cleanPreviousProfilePackages() pueda leer el profile anterior
        $this->composerService->generateFromProfile($profile, $projectRoot);
        $output->writeln('✓ composer.json actualizado');
        
        // Actualizar .bedrock/profile.json DESPUÉS de generar composer.json
        $this->composerService->copyProfileToProject($profile, $projectRoot);
        $output->writeln('✓ .bedrock/profile.json actualizado');
        
        // Copiar archivos .zip custom
        $this->composerService->copyCustomZipFiles($profile, $projectRoot);
        $output->writeln('✓ Archivos .zip copiados');
        
        $output->writeln('');
        $output->writeln('<info>Ejecutando composer update...</info>');
        
        $process = new \Symfony\Component\Process\Process(['composer', 'update', '--no-interaction'], $projectRoot);
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al ejecutar composer update</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<info>✓ Profile aplicado y dependencias instaladas</info>');
        $output->writeln('');

        return Command::SUCCESS;
    }


}
