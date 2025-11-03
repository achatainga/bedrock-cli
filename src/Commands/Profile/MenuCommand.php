<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MenuCommand extends Command
{
    private ProfileService $profileService;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
    }

    protected function configure(): void
    {
        $this->setName('profile:menu')
             ->setDescription('Menú de gestión de profiles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>   <fg=yellow;options=bold>📝 GESTIÓN DE PROFILES</><fg=magenta;options=bold>              ║</>');
        $output->writeln('<fg=magenta;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');

        // Detectar contexto
        $inProject = $this->isInBedrockProject();
        if ($inProject) {
            $output->writeln('<info>✓ Proyecto detectado:</info> ' . basename(getcwd()));
        } else {
            $output->writeln('<comment>⚠️  No estás en un proyecto Bedrock</comment>');
        }
        $output->writeln('');

        // Listar profiles disponibles
        $profiles = $this->profileService->listProfiles();
        
        if (empty($profiles)) {
            $output->writeln('<comment>No hay profiles disponibles</comment>');
            $output->writeln('');
            $output->writeln('<info>[C]</info> Crear nuevo profile');
            $output->writeln('<info>[0]</info> Volver al menú principal');
            $output->writeln('');
            
            $question = new Question('<fg=yellow>Opción (C/0):</> ', '0');
            $choice = strtoupper($helper->ask($input, $output, $question));
            
            if ($choice === 'C') {
                $this->runCommand('profile:create', [], $input, $output);
                return 'continue';
            }
            return 'exit';
        }

        $output->writeln('<info>Profiles disponibles:</info>');
        $output->writeln('');
        
        $choices = [];
        $index = 1;
        foreach ($profiles as $profile) {
            $desc = $profile['description'] ?? 'Sin descripción';
            $output->writeln(" <info>[{$index}]</info> {$profile['name']} - {$desc}");
            $choices[$index] = $profile['name'];
            $index++;
        }
        
        $output->writeln('');
        $output->writeln('<info>[C]</info> Crear nuevo profile');
        $output->writeln('<info>[0]</info> Volver al menú principal');
        $output->writeln('');

        $question = new Question('<fg=yellow>Selecciona un profile (1-' . count($profiles) . ') o acción (C/0):</> ', '0');
        $choice = strtoupper($helper->ask($input, $output, $question));

        if ($choice === '0') {
            return 'exit';
        }

        if ($choice === 'C') {
            $this->runCommand('profile:create', [], $input, $output);
            return 'continue';
        }

        $selectedIndex = (int)$choice;
        if (isset($choices[$selectedIndex])) {
            $this->showProfileMenu($choices[$selectedIndex], $inProject, $input, $output);
            return 'continue';
        }

        $output->writeln('<error>Opción inválida</error>');
        return 'continue';
    }

    private function showProfileMenu(string $profileName, bool $inProject, InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>   <fg=yellow;options=bold>Profile: ' . str_pad($profileName, 24) . '</><fg=magenta;options=bold>║</>');
        $output->writeln('<fg=magenta;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<info>¿Qué deseas hacer?</info>');
        $output->writeln('');
        $output->writeln(' <info>[1]</info> 👁️  Ver detalles (JSON completo)');
        $output->writeln(' <info>[2]</info> ✏️  Editar (abrir en editor)');
        
        if ($inProject) {
            $output->writeln(' <info>[3]</info> 📥 Aplicar (a proyecto actual)');
        } else {
            $output->writeln(' <comment>[3]</comment> <fg=gray>📥 Aplicar (requiere proyecto)</>');
        }
        
        $output->writeln(' <info>[4]</info> 🗑️  Eliminar');
        $output->writeln(' <info>[0]</info> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción:</> ', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->runCommand('profile:show', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '2':
                $this->runCommand('profile:edit', ['name' => $profileName], $input, $output);
                break;
            case '3':
                if ($inProject) {
                    $this->runCommand('profile:apply', ['name' => $profileName], $input, $output);
                    $this->waitForEnter($input, $output);
                } else {
                    $output->writeln('<error>Debes estar en un proyecto Bedrock para aplicar un profile</error>');
                    $this->waitForEnter($input, $output);
                }
                break;
            case '4':
                $this->runCommand('profile:delete', ['name' => $profileName], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '0':
                break;
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
        }
    }

    private function isInBedrockProject(): bool
    {
        $cwd = getcwd();
        return file_exists("{$cwd}/web/wp-config.php") || file_exists("{$cwd}/config/application.php");
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
