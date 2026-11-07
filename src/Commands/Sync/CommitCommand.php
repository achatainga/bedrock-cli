<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Sync;

use Roots\BedrockCli\Services\Sync\EntitySyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CommitCommand extends Command
{
    private EntitySyncService $syncService;

    public function __construct(?EntitySyncService $syncService = null)
    {
        parent::__construct();
        $this->syncService = $syncService ?? new EntitySyncService();
    }

    protected function configure(): void
    {
        $this->setName('sync:commit')
             ->setDescription('Empaqueta las entidades preparadas en un changeset inmutable (estilo git commit)')
             ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Mensaje descriptivo del changeset')
             ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'Autor del commit', 'developer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $message = $input->getOption('message');
        $author = (string) $input->getOption('author');

        if (!$message) {
            $output->writeln('<error>Especifica un mensaje descriptivo con -m "mensaje".</error>');
            return Command::FAILURE;
        }

        try {
            $manifest = $this->syncService->commitChangeset($projectRoot, $message, $author);
            $output->writeln("<info>✓ Commit exitoso: Changeset '{$manifest['id']}' creado ({$manifest['count']} entidades).</info>");
            $output->writeln("<comment>   Mensaje: \"{$manifest['message']}\"</comment>");
            $output->writeln("<comment>   Usa 'bedrock sync:push --target=staging' para aplicarlo en destino.</comment>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>✗ Error: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
