<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PushCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:push')
             ->setDescription('Aplica un changeset en el entorno destino de forma idempotente y segura (estilo git push)')
             ->addOption('changeset', 'c', InputOption::VALUE_OPTIONAL, 'ID del changeset a aplicar (por defecto el último)')
             ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Entorno destino (staging, bedrock, 443, 9010)', 'staging')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular sin aplicar cambios en el destino');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $changesetId = $input->getOption('changeset');
        $targetEnv = (string) $input->getOption('target');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$changesetId) {
            $list = $this->syncService->listChangesets($projectRoot);
            if (empty($list)) {
                $output->writeln('<error>No hay changesets disponibles para aplicar. Usa sync:stage y sync:commit primero.</error>');
                return Command::FAILURE;
            }
            $changesetId = $list[0]['id'];
            $output->writeln("<comment>Seleccionado automáticamente el changeset más reciente: {$changesetId}</comment>");
        }

        $manifest = $this->syncService->loadChangeset($projectRoot, $changesetId);
        if (!$manifest) {
            $output->writeln("<error>✗ No se encontró el changeset '{$changesetId}'.</error>");
            return Command::FAILURE;
        }

        $output->writeln('<info>====================================================</info>');
        $output->writeln("<info>🚀 PUSHING CHANGESET: {$manifest['id']} ➔ {$targetEnv}</info>");
        $output->writeln("<info>   Mensaje: \"{$manifest['message']}\" ({$manifest['count']} entidades)</info>");
        $output->writeln('<info>====================================================</info>');

        try {
            $result = $this->syncService->push($projectRoot, $manifest, $targetEnv, $dryRun, $output);

            if ($dryRun) {
                $output->writeln('<info>✓ Simulación de push completada exitosamente sin alterar la base de datos de destino.</info>');
                return Command::SUCCESS;
            }

            $applied = $result['result'] ?? [];
            $created = $applied['created'] ?? [];
            $updated = $applied['updated'] ?? [];
            $errors  = $applied['errors'] ?? [];

            $output->writeln('');
            $output->writeln("<info>✓ Sincronización finalizada exitosamente en '{$targetEnv}'.</info>");
            $output->writeln("<info>  - Nuevos productos creados:   " . count($created) . "</info>");
            $output->writeln("<info>  - Productos actualizados:     " . count($updated) . "</info>");

            if (!empty($errors)) {
                $output->writeln("<error>  - Errores encontrados:        " . count($errors) . "</error>");
                foreach ($errors as $err) {
                    $output->writeln("<error>    * [{$err['sku']}]: {$err['error']}</error>");
                }
            }

            if (!empty($created) || !empty($updated)) {
                $table = new Table($output);
                $table->setHeaders(['Acción', 'SKU', 'Destino ID', 'Nombre']);
                foreach ($created as $c) {
                    $table->addRow(['<info>INSERTADO</info>', $c['sku'], $c['id'], mb_substr($c['name'], 0, 35)]);
                }
                foreach ($updated as $u) {
                    $table->addRow(['<comment>ACTUALIZADO</comment>', $u['sku'], $u['id'], mb_substr($u['name'], 0, 35)]);
                }
                $table->render();
            }

            $output->writeln('');
            $output->writeln("<comment>Punto de restauración creado: {$result['backup_id']}</comment>");
            $output->writeln("<comment>Para revertir este push en cualquier momento: bedrock sync:rollback --backup={$result['backup_id']} --target={$targetEnv}</comment>");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>✗ Error durante push: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
