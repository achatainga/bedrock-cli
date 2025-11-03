<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\Table;

class DependenciesManageCommand extends Command
{
    private ContextDetector $contextDetector;
    private ManagementService $management;
    private DependencyManager $dependencyManager;

    public function __construct()
    {
        parent::__construct();
        $this->contextDetector = new ContextDetector();
        $this->management = new ManagementService($this->contextDetector);
        $this->dependencyManager = new DependencyManager($this->management);
    }

    protected function configure(): void
    {
        $this->setName('manage:dependencies')
             ->setDescription('Gestión de dependencias Composer del proyecto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->management->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        while (true) {
            $result = $this->showMenu($input, $output);
            if ($result === 'exit') {
                return Command::SUCCESS;
            }
        }
    }

    private function showMenu(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $dependencies = $this->dependencyManager->list();

        $output->writeln('');
        $output->writeln('<fg=blue;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=blue;options=bold>║</>   <fg=yellow;options=bold>📦 GESTIÓN DE DEPENDENCIAS</><fg=blue;options=bold>        ║</>');
        $output->writeln('<fg=blue;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');

        if (!empty($dependencies)) {
            $prod = array_filter($dependencies, fn($d) => !$d['dev']);
            $dev = array_filter($dependencies, fn($d) => $d['dev']);

            if (!empty($prod)) {
                $output->writeln("<fg=cyan>Dependencias de producción (" . count($prod) . "):</>");
                $output->writeln('');
                $table = new Table($output);
                $table->setHeaders(['<fg=cyan>Paquete</>', '<fg=cyan>Versión</>']);
                foreach ($prod as $dep) {
                    $table->addRow([$dep['package'], $dep['version']]);
                }
                $table->render();
                $output->writeln('');
            }

            if (!empty($dev)) {
                $output->writeln("<fg=cyan>Dependencias de desarrollo (" . count($dev) . "):</>");
                $output->writeln('');
                $table = new Table($output);
                $table->setHeaders(['<fg=cyan>Paquete</>', '<fg=cyan>Versión</>']);
                foreach (array_slice($dev, 0, 5) as $dep) {
                    $table->addRow([$dep['package'], $dep['version']]);
                }
                if (count($dev) > 5) {
                    $output->writeln("<comment>... y " . (count($dev) - 5) . " más</comment>");
                }
                $table->render();
                $output->writeln('');
            }
        } else {
            $output->writeln('<comment>No hay dependencias instaladas</comment>');
            $output->writeln('');
        }

        $output->writeln('<fg=cyan>¿Qué deseas hacer?</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> ➕ Agregar dependencia');
        $output->writeln(' <fg=cyan>[2]</> 🗑️  Remover dependencia');
        $output->writeln(' <fg=cyan>[3]</> 🔄 Actualizar dependencias');
        $output->writeln(' <fg=cyan>[4]</> 📥 Instalar dependencias');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-4]: </>', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->addDependency($input, $output);
                return 'continue';
            case '2':
                $this->removeDependency($input, $output, $dependencies);
                return 'continue';
            case '3':
                $this->updateDependencies($input, $output);
                return 'continue';
            case '4':
                $this->installDependencies($input, $output);
                return 'continue';
            case '0':
                return 'exit';
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
                return 'continue';
        }
    }

    private function addDependency(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $question = new Question('<fg=yellow>Nombre del paquete (vendor/package):</> ');
        $package = $helper->ask($input, $output, $question);

        if (!$package || !str_contains($package, '/')) {
            $output->writeln('<error>Formato inválido. Usa: vendor/package</error>');
            $this->waitForEnter($input, $output);
            return;
        }

        $question = new Question('<fg=yellow>Versión (Enter para última):</> ', null);
        $version = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('<fg=yellow>¿Es dependencia de desarrollo? (y/N):</> ', false);
        $isDev = $helper->ask($input, $output, $question);

        $output->writeln('');
        $output->writeln("<info>Instalando {$package}...</info>");
        
        $exitCode = $this->dependencyManager->require(
            $package,
            $version,
            $isDev,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Dependencia instalada correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al instalar dependencia</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function removeDependency(InputInterface $input, OutputInterface $output, array $dependencies): void
    {
        if (empty($dependencies)) {
            $output->writeln('<comment>No hay dependencias para remover</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $helper = $this->getHelper('question');
        $choices = [];
        
        foreach ($dependencies as $dep) {
            $type = $dep['dev'] ? 'dev' : 'prod';
            $choices[$dep['package']] = "{$dep['package']} ({$type})";
        }
        $choices['cancel'] = 'Cancelar';

        $question = new ChoiceQuestion('Selecciona dependencia a remover:', $choices, 'cancel');
        $selected = $helper->ask($input, $output, $question);

        if ($selected === 'Cancelar' || $selected === 'cancel') {
            return;
        }

        $package = array_search($selected, $choices);
        if ($package === 'cancel' || !$package) {
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Removiendo {$package}...</info>");
        
        $this->dependencyManager->remove($package);
        
        $exitCode = $this->dependencyManager->update(
            [$package],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Dependencia removida correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al remover dependencia</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function updateDependencies(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $question = new ConfirmationQuestion(
            '<fg=yellow>¿Actualizar todas las dependencias? (y/N):</> ',
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $output->writeln('');
        $output->writeln('<info>Actualizando dependencias...</info>');
        
        $exitCode = $this->dependencyManager->update(
            null,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Dependencias actualizadas correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al actualizar dependencias</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function installDependencies(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $question = new ConfirmationQuestion(
            '<fg=yellow>¿Instalar dependencias? (y/N):</> ',
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $output->writeln('');
        $output->writeln('<info>Instalando dependencias...</info>');
        
        $exitCode = $this->dependencyManager->install(
            false,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Dependencias instaladas correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al instalar dependencias</error>');
        }

        $this->waitForEnter($input, $output);
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
