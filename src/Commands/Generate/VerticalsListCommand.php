<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Generate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Roots\BedrockCli\Services\Blueprints\VerticalRegistry;

class VerticalsListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('verticals:list')
            ->setDescription('Listar las 50 verticales empresariales pre-diseñadas para Bedrock')
            ->addOption('sector', 's', InputOption::VALUE_REQUIRED, 'Filtrar por macro-sector (regulated, professional_services, ecommerce_logistics, media_creators, consumer_hospitality)')
            ->addOption('prefix', 'p', InputOption::VALUE_REQUIRED, 'Prefijo agnóstico para plugins personalizados (ej. core-, plugin-)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = new VerticalRegistry();
        $prefix = $input->getOption('prefix');
        if ($prefix !== null) {
            $registry->setPluginPrefix((string) $prefix);
        }

        $sectorFilter = $input->getOption('sector');

        $output->writeln('<info>========================================================================</info>');
        $output->writeln('<info> BEDROCK CLI — CORE ENGINE VERTICALS (50 PRESETS)</info>');
        $output->writeln('<info>========================================================================</info>');
        $output->writeln('');

        $sectors = $registry->getSectors();

        foreach ($sectors as $sectorKey => $sectorName) {
            if ($sectorFilter && $sectorFilter !== $sectorKey) {
                continue;
            }

            $verticals = $registry->getVerticalsBySector($sectorKey);
            if (empty($verticals)) {
                continue;
            }

            $output->writeln("<comment>▶ {$sectorName} (" . count($verticals) . " verticales)</comment>");

            $table = new Table($output);
            $table->setHeaders(['ID', 'Nombre', 'Admin Stack', 'VA Capabilities', 'Plugins']);

            foreach ($verticals as $v) {
                $plugins = array_map(fn(string $p) => $registry->normalizePlugin($p), $v['plugins'] ?? []);
                $table->addRow([
                    "<fg=cyan>{$v['id']}</>",
                    $v['name'],
                    $v['admin_stack'],
                    implode(', ', array_slice($v['va_capabilities'] ?? [], 0, 2)),
                    implode(', ', array_slice($plugins, 0, 3)),
                ]);
            }

            $table->render();
            $output->writeln('');
        }

        $output->writeln("<comment>Total disponibles: {$registry->count()} verticales</comment>");
        $output->writeln("Para generar un proyecto, ejecuta: <info>bedrock generate <id> [nombre-proyecto]</info>");

        return Command::SUCCESS;
    }
}
