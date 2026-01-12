<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\UnzipService;
use Roots\BedrockCli\Services\ZipService;
use Roots\BedrockCli\Traits\SpinnerTrait;
use Roots\BedrockCli\Traits\FileSystemTrait;

abstract class BaseMenuCommand extends Command
{
    use SpinnerTrait, FileSystemTrait;

    protected DockerService $dockerService;
    protected WpCliService $wpCliService;
    protected UnzipService $unzipService;
    protected ZipService $zipService;

    public function __construct(
        DockerService $dockerService,
        WpCliService $wpCliService,
        UnzipService $unzipService,
        ZipService $zipService
    ) {
        parent::__construct();
        $this->dockerService = $dockerService;
        $this->wpCliService = $wpCliService;
        $this->unzipService = $unzipService;
        $this->zipService = $zipService;
    }

    abstract protected function getItemType(): string; // 'plugin' o 'theme'
    abstract protected function getPluralType(): string; // 'plugins' o 'themes'
    abstract protected function getMenuTitle(): string;

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this
            ->setName($this->getPluralType())
            ->setDescription("Gestión de {$this->getPluralType()}")
            ->addArgument($type, InputArgument::OPTIONAL, "Nombre del {$type}")
            ->addOption('delete', null, InputOption::VALUE_NONE, "Eliminar carpeta del {$type} (filesystem)")
            ->addOption('compress', null, InputOption::VALUE_NONE, "Comprimir {$type} a ZIP");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $item = $input->getArgument($this->getItemType());
        
        if ($item && $input->getOption('delete')) {
            return $this->deleteFolderDirect($output, $item);
        }
        
        if ($item && $input->getOption('compress')) {
            return $this->compressDirect($output, $item);
        }
        
        while (true) {
            $this->displayHeader($output);
            $this->displayOptions($output);
            
            $question = new Question('<fg=yellow>Opción [0-7]: </>', '0');
            $index = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            if ($index === '0') return Command::SUCCESS;

            $output->writeln('');
            if (!$this->handleSelection($index, $input, $output, $helper)) break;
            
            $output->writeln('');
            $output->writeln('<comment>Presiona Enter para continuar...</comment>');
            if ($input->isInteractive()) fgets(STDIN);
        }

        return Command::SUCCESS;
    }

    protected function displayHeader(OutputInterface $output): void
    {
        $title = str_pad($this->getMenuTitle(), 30, ' ', STR_PAD_BOTH);
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln("<fg=cyan;options=bold>║</> <fg=yellow;options=bold>{$title}</> <fg=cyan;options=bold>║</>");
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
    }

    protected function displayOptions(OutputInterface $output): void
    {
        $type = ucfirst($this->getItemType());
        $output->writeln(" <fg=cyan>[1]</> ⚙️  Gestionar {$this->getItemType()} específico");
        $output->writeln(' <fg=cyan>[2]</> 📋 Listar desde WordPress');
        $output->writeln(' <fg=cyan>[3]</> ⬇️  Instalar desde repositorio');
        $output->writeln(' <fg=cyan>[4]</> 🔄 Actualizar todos');
        $output->writeln(' <fg=cyan>[5]</> 📦 Descomprimir ZIPs');
        
        if ($this->getItemType() === 'plugin') {
            $output->writeln(' <fg=cyan>[6]</> 🔢 Orden de activación');
            $output->writeln(' <fg=cyan>[7]</> 🛠️  Constructor de orden');
        }
        
        $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
        $output->writeln('');
    }

    protected function handleSelection(string $index, InputInterface $input, OutputInterface $output, $helper): bool
    {
        switch ($index) {
            case '1': $this->manageSpecificItem($input, $output); break;
            case '2': $this->listFromWordPress($output); break;
            case '3': 
                $item = $helper->ask($input, $output, new Question("<cyan>Slug del {$this->getItemType()}:</cyan> "));
                $this->install($output, $item); 
                break;
            case '4': $this->updateAll($output); break;
            case '5': $this->unzipAssets($input, $output); break;
            case '6': 
                if ($this->getItemType() === 'plugin') $this->runExternalCommand('plugins:order:menu', $input, $output);
                break;
            case '7':
                if ($this->getItemType() === 'plugin') $this->runExternalCommand('plugins:order:build', $input, $output);
                break;
            default: return false;
        }
        return true;
    }

    protected function runExternalCommand(string $name, InputInterface $input, OutputInterface $output): void
    {
        $command = $this->getApplication()->find($name);
        $command->run($input, $output);
    }

    protected function listFromWordPress(OutputInterface $output): int
    {
        $process = $this->wpCliService->custom("{$this->getItemType()} list --format=table");
        $this->runWithLoader($process, $output, "Consultando {$this->getPluralType()} desde WordPress");
        
        if ($process->isSuccessful()) {
            $output->writeln('');
            $output->write($process->getOutput());
            return Command::SUCCESS;
        }
        
        // Aquí se podría meter la lógica de detección de errores comunes (Docker, DB, etc)
        // Pero para DRY simplificado, mostramos el error
        $output->writeln("<error>✗ Error: " . trim($process->getErrorOutput()) . "</error>");
        return Command::FAILURE;
    }

    protected function install(OutputInterface $output, string $item): int
    {
        $method = $this->getItemType() . 'Install';
        $process = $this->wpCliService->$method($item);
        $this->runWithLoader($process, $output, "Instalando {$this->getItemType()}: {$item}");
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    protected function updateAll(OutputInterface $output): int
    {
        $method = $this->getItemType() . 'Update';
        $process = $this->wpCliService->$method();
        $this->runWithLoader($process, $output, "Actualizando {$this->getPluralType()}");
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    private function manageSpecificItem(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $itemsDir = $this->detectProjectRoot() . "/web/app/{$this->getPluralType()}";
        $items = $this->scanDirectory($itemsDir);

        if (empty($items)) {
            $output->writeln("<comment>No hay {$this->getPluralType()} instalados</comment>");
            return;
        }

        $choices = [];
        foreach ($items as $idx => $item) $choices[(string)($idx + 1)] = "<fg=green>{$item}</>";
        $choices['0'] = '<fg=yellow>Volver</>';

        $choice = $helper->ask($input, $output, new ChoiceQuestion("<fg=yellow>Seleccione un {$this->getItemType()}:</>", $choices, '0'));
        if ($choice === '<fg=yellow>Volver</>') return;

        preg_match('/<fg=green>(.*?)<\/>/', $choice, $matches);
        $selectedItem = $matches[1] ?? null;
        if ($selectedItem) $this->itemActionsMenu($input, $output, $selectedItem, $itemsDir);
    }

    protected function itemActionsMenu(InputInterface $input, OutputInterface $output, string $item, string $itemsDir): void
    {
        $helper = $this->getHelper('question');
        while (true) {
            $output->writeln("\n<fg=cyan;options=bold>" . ucfirst($this->getItemType()) . ": {$item}</>");
            $choices = [
                1 => '<fg=green>Activar</>',
                2 => '<fg=yellow>Desactivar</>',
                3 => '<fg=red>Desinstalar (WP-CLI)</>',
                4 => '<fg=cyan>Estado / info</>',
                5 => '<fg=red;options=bold>Eliminar carpeta</>',
                6 => '<fg=cyan>Comprimir a ZIP</>',
                0 => '<fg=yellow>Volver</>',
            ];

            $answer = $helper->ask($input, $output, new ChoiceQuestion('<fg=cyan>Selecciona una acción:</>', $choices, 0));
            $index = is_numeric($answer) ? (int)$answer : array_search($answer, $choices);
            if ($index === 0) return;

            $output->writeln('');
            $this->executeItemAction($index, $item, $input, $output, $itemsDir);
        }
    }

    protected function executeItemAction(int $index, string $item, InputInterface $input, OutputInterface $output, string $itemsDir): void
    {
        $type = $this->getItemType();
        switch ($index) {
            case 1: $this->runWithLoader($this->wpCliService->{$type . 'Activate'}($item), $output, "Activando {$item}"); break;
            case 2: $this->runWithLoader($this->wpCliService->{$type . 'Deactivate'}($item), $output, "Desactivando {$item}"); break;
            case 3: $this->runWithLoader($this->wpCliService->{$type . 'Uninstall'}($item), $output, "Desinstalando {$item}"); break;
            case 4: 
                 $process = $this->wpCliService->custom("{$type} get {$item}");
                 $this->runWithLoader($process, $output, "Obteniendo info de {$item}");
                 if ($process->isSuccessful()) $output->writeln($process->getOutput());
                 break;
            case 5: $this->confirmAndDeleteFolder($input, $output, $item, $itemsDir); break;
            case 6: $this->compressItem($output, $item, $itemsDir); break;
        }
    }

    protected function confirmAndDeleteFolder(InputInterface $input, OutputInterface $output, string $item, string $itemsDir): void
    {
        $helper = $this->getHelper('question');
        $path = "{$itemsDir}/{$item}";
        $output->writeln("<fg=red;options=bold>⚠️  ADVERTENCIA: Se eliminará {$path}</>");
        if ($helper->ask($input, $output, new Question('<fg=red>Escribe "ELIMINAR" para confirmar:</> ')) === 'ELIMINAR') {
            if ($this->removeDirectory($path)) $output->writeln('<info>✓ Carpeta eliminada</info>');
        }
    }

    protected function compressItem(OutputInterface $output, string $item, string $itemsDir): void
    {
        $zipPath = $this->detectProjectRoot() . "/{$this->getPluralType()}/{$item}.zip";
        if ($this->zipService->compress("{$itemsDir}/{$item}", $zipPath, $output)) {
            $output->writeln("<info>✓ Comprimido en: {$zipPath}</info>");
        }
    }

    protected function unzipAssets(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $zipDir = $this->detectProjectRoot() . "/{$this->getPluralType()}";
        $installDir = $this->detectProjectRoot() . "/web/app/{$this->getPluralType()}";

        if (!is_dir($zipDir)) {
            $zipDir = $helper->ask($input, $output, new Question("<question>Ruta de origen ZIPs [{$zipDir}]: </question>", $zipDir));
        }

        $zips = $this->unzipService->listZipFiles($zipDir);
        if (empty($zips)) { $output->writeln('<comment>No hay ZIPs</comment>'); return; }

        $choices = ['1' => '<fg=green>Todos</>'];
        foreach ($zips as $idx => $zip) $choices[(string)($idx + 2)] = $zip;
        $choices['0'] = 'Volver';

        $choice = $helper->ask($input, $output, new ChoiceQuestion('Seleccione ZIP:', $choices, '0'));
        if ($choice === 'Volver') return;

        $toUnzip = ($choice === '<fg=green>Todos</>') ? $zips : [$choice];
        foreach ($toUnzip as $zip) {
            $output->writeln("<info>Descomprimiendo {$zip}...</info>");
            $this->unzipService->unzip("{$zipDir}/{$zip}", $installDir, $output);
        }
    }
    
    protected function deleteFolderDirect(OutputInterface $output, string $item): int
    {
        $path = $this->detectProjectRoot() . "/web/app/{$this->getPluralType()}/{$item}";
        return $this->removeDirectory($path) ? Command::SUCCESS : Command::FAILURE;
    }

    protected function compressDirect(OutputInterface $output, string $item): int
    {
        $this->compressItem($output, $item, $this->detectProjectRoot() . "/web/app/{$this->getPluralType()}");
        return Command::SUCCESS;
    }
}
