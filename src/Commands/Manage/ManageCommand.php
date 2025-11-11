<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ManageCommand extends Command
{
    use ProjectSelectorTrait;

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
        if (!$this->ensureBedrockProject($input, $output)) {
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
        $output->writeln('<fg=cyan>¿Qué deseas gestionar?</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 🔌 Plugins');
        $output->writeln(' <fg=cyan>[2]</> 🎨 Themes');
        $output->writeln(' <fg=cyan>[3]</> 📦 Dependencias Composer');
        $output->writeln(' <fg=cyan>[4]</> 🔧 MU-Plugins');
        $output->writeln(' <fg=cyan>[0]</> ❌ Salir');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-4]: </>', '0');
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
            case '4':
                $this->showMuPluginsMenu($input, $output);
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

    private function showMuPluginsMenu(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');

        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln('<fg=magenta;options=bold>  🔧 MU-PLUGINS MANAGER</>');
        $output->writeln('<fg=magenta;options=bold>════════════════════════════════════════</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 📥 Instalar Bedrock CLI Plugin');
        $output->writeln(' <fg=cyan>[2]</> 🔄 Actualizar desde versión antigua');
        $output->writeln(' <fg=cyan>[3]</> ✅ Verificar instalación');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-3]: </>', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->runCommand('install:mu-plugin', [], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '2':
                $this->runCommand('install:update-mu-plugin', [], $input, $output);
                $this->waitForEnter($input, $output);
                break;
            case '3':
                $this->verifyMuPlugin($output);
                $this->waitForEnter($input, $output);
                break;
        }
    }

    private function verifyMuPlugin(OutputInterface $output): void
    {
        $oldPlugin = getcwd() . '/web/app/mu-plugins/bedrock-cli-api.php';
        $newPlugin = getcwd() . '/web/app/mu-plugins/bedrock-cli-plugin';

        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>🔍 Verificando instalación...</>');
        $output->writeln('');

        // Check old plugin
        if (file_exists($oldPlugin)) {
            $output->writeln('<fg=yellow>⚠ Plugin antiguo detectado: bedrock-cli-api.php</>');
            $output->writeln('<comment>  Usa opción [2] para actualizar a la nueva versión</comment>');
            $output->writeln('');
        }

        // Check new plugin
        if (!file_exists($newPlugin)) {
            $output->writeln('<fg=red>✗ Bedrock CLI Plugin NO instalado</>');
            $output->writeln('<comment>Ejecuta la opción [1] para instalarlo</comment>');
            return;
        }

        $output->writeln('<fg=green>✓ Bedrock CLI Plugin instalado</>');
        $output->writeln("<fg=gray>  Ubicación: {$newPlugin}</>");
        $output->writeln('');

        // Verificar token
        $output->writeln('<fg=cyan>Verificando token...</>');
        
        $process = new \Symfony\Component\Process\Process([
            'docker-compose', 'exec', '-T', 'web', 'wp', 'eval',
            "echo get_option('bedrock_cli_token') ? 'EXISTS' : 'MISSING';"
        ]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            if (trim($process->getOutput()) === 'EXISTS') {
                $output->writeln('<fg=green>✓ Token generado</>');
                $output->writeln('<comment>  REST API lista para usar</comment>');
            } else {
                $output->writeln('<fg=yellow>⚠ Token no generado aún</>');
                $output->writeln('<comment>  Se generará automáticamente en la primera petición</comment>');
            }
        } else {
            $output->writeln('<fg=yellow>⚠ No se pudo verificar token (WordPress no responde)</>');
            $output->writeln('<comment>  Asegúrate de que Docker esté corriendo</comment>');
        }

        $output->writeln('');
    }
}
