<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntityComparatorService;
use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:status')
             ->setDescription('Muestra el estado diferencial de entidades entre entornos (estilo git status)')
             ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Entorno origen (ej. 9010, local, staging)', '9010')
             ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Entorno destino (ej. staging, 443, local)', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceEnv = (string) $input->getOption('source');
        $targetEnv = (string) $input->getOption('target');
        $projectRoot = getcwd();

        $output->writeln('<info>====================================================</info>');
        $output->writeln("<info>🔍 BEDROCK ENTITY SYNC STATUS: {$sourceEnv} ➔ {$targetEnv}</info>");
        $output->writeln('<info>====================================================</info>');

        $output->writeln("<comment>Consultando catálogo en origen ({$sourceEnv})...</comment>");
        try {
            $sourceCatalog = $this->syncService->fetchCatalog($sourceEnv);
            $output->writeln("<info>✓ Origen: " . count($sourceCatalog) . " productos encontrados</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>✗ Error en origen: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        $output->writeln("<comment>Consultando catálogo en destino ({$targetEnv})...</comment>");
        try {
            $targetCatalog = $this->syncService->fetchCatalog($targetEnv);
            $output->writeln("<info>✓ Destino: " . count($targetCatalog) . " productos encontrados</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>✗ Error en destino: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        $output->writeln('');
        $diff = $this->syncService->getComparator()->compare($sourceCatalog, $targetCatalog);

        $staged = $this->syncService->getStaged($projectRoot);

        // Tabla de Cambios
        $newCount = count($diff['new']);
        $modCount = count($diff['modified']);
        $delCount = count($diff['deleted']);
        $ideCount = count($diff['identical']);

        $output->writeln("<comment>Resumen: {$newCount} nuevos (+), {$modCount} modificados (M), {$delCount} solo en destino (?), {$ideCount} idénticos (=)</comment>");
        $output->writeln('');

        if ($newCount > 0 || $modCount > 0) {
            $table = new Table($output);
            $table->setHeaders(['Estado', 'SKU', 'Nombre', 'Precio Origen', 'Precio Destino', 'Diff Resumen', 'Staged']);

            foreach ($diff['new'] as $sku => $item) {
                $isStaged = isset($staged[$sku]) ? '<info>SI</info>' : '<comment>NO</comment>';
                $table->addRow([
                    '<info>+ NUEVO</info>',
                    $sku,
                    mb_substr($item['entity']['name'], 0, 35),
                    '$' . number_format($item['entity']['price'], 2),
                    'N/A',
                    'Nuevo producto',
                    $isStaged,
                ]);
            }

            foreach ($diff['modified'] as $sku => $item) {
                $isStaged = isset($staged[$sku]) ? '<info>SI</info>' : '<comment>NO</comment>';
                $diffSummary = implode(', ', array_keys($item['diffs']));
                $table->addRow([
                    '<comment>M MODIF</comment>',
                    $sku,
                    mb_substr($item['source']['name'], 0, 35),
                    '$' . number_format($item['source']['price'], 2),
                    '$' . number_format($item['target']['price'], 2),
                    $diffSummary,
                    $isStaged,
                ]);
            }

            $table->render();
            $output->writeln('');
        } else {
            $output->writeln('<info>✓ Catálogos completamente sincronizados. No hay diferencias pendientes.</info>');
            $output->writeln('');
        }

        if (!empty($staged)) {
            $output->writeln("<info>📦 Área de preparación (Staged para Commit): " . count($staged) . " entidad(es)</info>");
            $output->writeln("<comment>   Usa 'bedrock sync:commit -m \"mensaje\"' para crear un changeset.</comment>");
        } else {
            $output->writeln("<comment>💡 Sugerencia: Usa 'bedrock sync:stage <sku>' para preparar entidades para sync.</comment>");
            $output->writeln("<comment>   Usa 'bedrock sync:diff <sku>' para inspeccionar cambios a nivel de campo.</comment>");
        }

        return Command::SUCCESS;
    }
}
