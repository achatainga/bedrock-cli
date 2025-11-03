<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ManageCommand extends Command
{
    private ContextDetector $contextDetector;

    public function __construct()
    {
        parent::__construct();
        $this->contextDetector = new ContextDetector();
    }

    protected function configure(): void
    {
        $this->setName('manage')
             ->setDescription('Sistema unificado de gestión de proyectos Bedrock');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->contextDetector->isBedrockProject()) {
            $output->writeln('<error>Este comando debe ejecutarse dentro de un proyecto Bedrock</error>');
            $output->writeln('<info>Usa "bedrock new <nombre>" para crear un nuevo proyecto</info>');
            return Command::FAILURE;
        }

        while (true) {
            $result = $this->showMainMenu($input, $output);
            if ($result === 'exit') {
                return Command::SUCCESS;
            }
        }
    }

    private function showMainMenu(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $root = $this->contextDetector->getProjectRoot();
        $projectName = basename($root);
        $profile = $this->contextDetector->getActiveProfile();

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</>   <fg=yellow;options=bold>🎛️  BEDROCK PROJECT MANAGER</><fg=cyan;options=bold>       ║</>');
        $output->writeln('<fg=cyan;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln("<info>📂 Proyecto:</info> {$projectName}");
        
        if ($profile) {
            $output->writeln("<info>📋 Profile:</info> {$profile['name']}");
        }
        
        $output->writeln('');
        $output->writeln('<info>¿Qué deseas gestionar?</info>');
        $output->writeln('');
        $output->writeln(' <info>[1]</info> 🔌 Plugins');
        $output->writeln(' <info>[2]</info> 🎨 Themes');
        $output->writeln(' <info>[3]</info> 📦 Dependencias Composer');
        $output->writeln(' <info>[0]</info> ❌ Salir');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción:</> ', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->runCommand('manage:plugins', [], $input, $output);
                return 'continue';
            case '2':
                $this->runCommand('manage:themes', [], $input, $output);
                return 'continue';
            case '3':
                $this->runCommand('manage:dependencies', [], $input, $output);
                return 'continue';
            case '0':
                return 'exit';
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
                return 'continue';
        }
    }

    private function runCommand(string $commandName, array $arguments, InputInterface $input, OutputInterface $output): void
    {
        try {
            $command = $this->getApplication()->find($commandName);
            $commandInput = new ArrayInput($arguments);
            $command->run($commandInput, $output);
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
        }
    }

    private function waitForEnter(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');
        $output->write('<comment>Presiona Enter para continuar...</comment>');
        if ($input->isInteractive()) {
            fgets(STDIN);
        }
    }
}
