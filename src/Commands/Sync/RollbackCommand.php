<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:rollback')
             ->setDescription('Revierte un changeset aplicado restaurando el snapshot atómico previo (estilo git revert)')
             ->addOption('backup', 'b', InputOption::VALUE_REQUIRED, 'ID o nombre del backup a restaurar')
             ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Entorno destino donde revertir', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $backupId = (string) $input->getOption('backup');
        $targetEnv = (string) $input->getOption('target');

        if (empty($backupId)) {
            $output->writeln('<error>Especifica el ID del backup a restaurar con --backup=backup_id.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>====================================================</info>');
        $output->writeln("<info>⏪ REVIRTIENDO CAMBIOS (ROLLBACK): {$backupId} en {$targetEnv}</info>");
        $output->writeln('<info>====================================================</info>');

        try {
            $res = $this->syncService->rollback($projectRoot, $backupId, $targetEnv, $output);
            $deletedCount = $res['deleted'] ?? 0;
            $restoredCount = $res['restored'] ?? 0;
            $output->writeln("<info>✓ Rollback completado exitosamente ({$restoredCount} entidades restauradas, {$deletedCount} nuevas entidades eliminadas).</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>✗ Error al revertir: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
