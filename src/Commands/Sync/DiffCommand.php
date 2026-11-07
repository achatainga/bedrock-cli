<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:diff')
             ->setDescription('Muestra las diferencias campo por campo de una entidad entre entornos (estilo git diff)')
             ->addArgument('sku', InputArgument::OPTIONAL, 'SKU o identificador de producto')
             ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Entorno origen', '9010')
             ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Entorno destino', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sku = $input->getArgument('sku');
        $sourceEnv = (string) $input->getOption('source');
        $targetEnv = (string) $input->getOption('target');

        $output->writeln("<info>🔍 Comparando diferencias: {$sourceEnv} vs {$targetEnv}</info>");

        $sourceCatalog = $this->syncService->fetchCatalog($sourceEnv);
        $targetCatalog = $this->syncService->fetchCatalog($targetEnv);

        $diff = $this->syncService->getComparator()->compare($sourceCatalog, $targetCatalog);

        if ($sku) {
            if (isset($diff['new'][$sku])) {
                $output->writeln("<info>+ Entidad NUEVA en origen (no existe en destino {$targetEnv}):</info>");
                $this->printEntityDetails($diff['new'][$sku]['entity'], $output);
                return Command::SUCCESS;
            }

            if (isset($diff['deleted'][$sku])) {
                $output->writeln("<comment>- Entidad SOLO en destino (no existe en origen {$sourceEnv}):</comment>");
                $this->printEntityDetails($diff['deleted'][$sku]['entity'], $output);
                return Command::SUCCESS;
            }

            if (isset($diff['identical'][$sku])) {
                $output->writeln("<info>✓ Entidad '{$sku}' es IDÉNTICA en ambos entornos.</info>");
                return Command::SUCCESS;
            }

            if (!isset($diff['modified'][$sku])) {
                $output->writeln("<error>✗ No se encontró la entidad con SKU '{$sku}'</error>");
                return Command::FAILURE;
            }

            $item = $diff['modified'][$sku];
            $output->writeln("<comment>Diferencias para SKU: {$sku} ({$item['source']['name']}):</comment>");
            $this->printDiffs($item['diffs'], $output);
            return Command::SUCCESS;
        }

        // Si no se pasó SKU específico, listar todas las diferencias
        if (empty($diff['modified']) && empty($diff['new'])) {
            $output->writeln('<info>✓ No hay diferencias entre los entornos.</info>');
            return Command::SUCCESS;
        }

        foreach ($diff['new'] as $s => $item) {
            $output->writeln("<info>+ [NUEVO] {$s}: {$item['entity']['name']} ($" . number_format($item['entity']['price'], 2) . ")</info>");
        }

        foreach ($diff['modified'] as $s => $item) {
            $output->writeln("<comment>M [MODIFICADO] {$s}: {$item['source']['name']}</comment>");
            $this->printDiffs($item['diffs'], $output);
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function printDiffs(array $diffs, OutputInterface $output): void
    {
        foreach ($diffs as $field => $d) {
            $fromStr = is_array($d['from']) ? json_encode($d['from']) : (string) ($d['from'] ?? 'null');
            $toStr   = is_array($d['to'])   ? json_encode($d['to'])   : (string) ($d['to'] ?? 'null');
            $output->writeln("    <comment>{$field}:</comment>");
            $output->writeln("      <error>- destino: {$fromStr}</error>");
            $output->writeln("      <info>+ origen:  {$toStr}</info>");
        }
    }

    private function printEntityDetails(array $entity, OutputInterface $output): void
    {
        $output->writeln("  Nombre:       {$entity['name']}");
        $output->writeln("  Slug:         {$entity['slug']}");
        $output->writeln("  Precio:       $" . number_format($entity['price'], 2));
        $output->writeln("  Stock:        " . ($entity['stock_quantity'] ?? 'N/A') . " ({$entity['stock_status']})");
        $cats = array_column($entity['categories'] ?: [], 'name');
        $output->writeln("  Categorías:   " . implode(', ', $cats));
    }
}
