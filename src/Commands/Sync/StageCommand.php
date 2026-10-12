<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StageCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:stage')
             ->setAliases(['sync:add'])
             ->setDescription('Prepara entidades en el área de staging para commit (estilo git add)')
             ->addArgument('skus', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'SKUs o identificadores a preparar')
             ->addOption('all', 'a', InputOption::VALUE_NONE, 'Preparar todas las entidades que tengan cambios pendientes')
             ->addOption('unstage', 'u', InputOption::VALUE_NONE, 'Quitar del área de preparación (unstage)')
             ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Entorno origen', '9010')
             ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Entorno destino', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $isUnstage = (bool) $input->getOption('unstage');
        $skus = (array) $input->getArgument('skus');
        $isAll = (bool) $input->getOption('all');

        if ($isUnstage) {
            if (empty($skus)) {
                $this->syncService->unstage($projectRoot);
                $output->writeln('<info>✓ Se limpió toda el área de preparación (unstage all).</info>');
            } else {
                foreach ($skus as $sku) {
                    $this->syncService->unstage($projectRoot, $sku);
                    $output->writeln("<info>✓ Entidad '{$sku}' retirada del área de preparación.</info>");
                }
            }
            return Command::SUCCESS;
        }

        $sourceEnv = (string) $input->getOption('source');
        $targetEnv = (string) $input->getOption('target');

        $output->writeln("<comment>Consultando entidades desde '{$sourceEnv}'...</comment>");
        $sourceCatalog = $this->syncService->fetchCatalog($sourceEnv);
        $targetCatalog = $this->syncService->fetchCatalog($targetEnv);
        $diff = $this->syncService->getComparator()->compare($sourceCatalog, $targetCatalog);

        $toStage = [];

        if ($isAll) {
            foreach ($diff['new'] as $s => $item) {
                $toStage[$s] = $item['entity'];
            }
            foreach ($diff['modified'] as $s => $item) {
                $toStage[$s] = $item['source'];
            }
        } elseif (!empty($skus)) {
            foreach ($skus as $sku) {
                if (isset($sourceCatalog[$sku])) {
                    $toStage[$sku] = $sourceCatalog[$sku];
                } else {
                    $output->writeln("<error>✗ SKU '{$sku}' no existe en el catálogo de origen ({$sourceEnv}).</error>");
                }
            }
        } else {
            $output->writeln('<error>Especifica uno o más SKUs o usa --all para preparar todos los cambios.</error>');
            return Command::FAILURE;
        }

        if (empty($toStage)) {
            $output->writeln('<comment>No hay entidades para preparar.</comment>');
            return Command::SUCCESS;
        }

        $count = $this->syncService->stageEntities($projectRoot, $toStage);
        $output->writeln("<info>✓ Preparadas {$count} entidad(es) en el área de staging.</info>");
        $output->writeln("<comment>Usa 'bedrock sync:commit -m \"mensaje\"' para empaquetar el changeset.</comment>");

        return Command::SUCCESS;
    }
}
