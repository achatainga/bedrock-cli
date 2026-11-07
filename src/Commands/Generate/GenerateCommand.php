<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Generate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\Blueprints\VerticalRegistry;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\VirtualAssistant\ChatWidgetGenerator;

class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generar un sistema empresarial completo listo para producción basado en las 50 verticales')
            ->addArgument('vertical', InputArgument::REQUIRED, 'ID de la vertical empresarial (ej. healthcare-provider, saas-b2b, ecommerce-brand)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Nombre del proyecto (por defecto el ID de la vertical)')
            ->addOption('admin', null, InputOption::VALUE_REQUIRED, 'Override del stack administrativo (acorn-blade-secure, filament-rbac, nextjs-headless)')
            ->addOption('va', null, InputOption::VALUE_REQUIRED, 'Canales del Virtual Assistant (whatsapp, telegram, web, all)', 'all')
            ->addOption('landing', null, InputOption::VALUE_NONE, 'Incluir landing page de alta conversión optimizada con Tailwind CSS v4')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular generación sin crear archivos en disco');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verticalId = $input->getArgument('vertical');
        $projectName = $input->getArgument('name') ?: $verticalId;
        $dryRun = (bool) $input->getOption('dry-run');

        $registry = new VerticalRegistry();
        if (!$registry->hasVertical($verticalId)) {
            $output->writeln("<error>Vertical '{$verticalId}' no encontrada en el catálogo.</error>");
            $output->writeln("Ejecuta <comment>bedrock verticals:list</comment> para ver las 50 opciones disponibles.");
            return Command::FAILURE;
        }

        $vertical = $registry->getVertical($verticalId);
        $output->writeln('<info>========================================================================</info>');
        $output->writeln("<info> GENERANDO PROYECTO 10x: {$projectName} [{$vertical['name']}]</info>");
        $output->writeln('<info>========================================================================</info>');
        $output->writeln("<comment>Sector:</comment> {$vertical['sector']}");
        $output->writeln("<comment>Descripción:</comment> {$vertical['description']}");
        $output->writeln('');

        // 1. Synthesize Bedrock Profile
        $profile = $registry->synthesizeProfile($verticalId, $projectName);
        if ($input->getOption('admin')) {
            $profile['admin_stack'] = $input->getOption('admin');
        }

        $output->writeln('<info>[1/4] Pilar Administrativo & RBAC:</info>');
        $output->writeln("  • Stack: <fg=cyan>{$profile['admin_stack']}</>");
        $output->writeln("  • CRM & Flujos: " . implode(', ', $profile['crm_features']));
        $output->writeln('');

        // 2. Virtual Assistant Hub
        $vaChannels = $input->getOption('va');
        $output->writeln('<info>[2/4] Omnichannel Virtual Assistant (VA Hub):</info>');
        $output->writeln("  • Canales activos: <fg=cyan>{$vaChannels}</>");
        $output->writeln("  • Modelo LLM: <fg=cyan>{$profile['virtual_assistant']['model']}</>");
        $output->writeln("  • Habilidades: " . implode(', ', $profile['virtual_assistant']['capabilities']));

        // Generate web widget snippet
        $widgetGen = new ChatWidgetGenerator();
        $widgetCode = $widgetGen->generateWidgetScript('/wp-json/bedrock/v1/assistant', $vertical['name'] . ' Assistant');
        $output->writeln('  • Web Chatbot Widget: generado para inyección en header/footer');
        $output->writeln('');

        // 3. Landing Page & Form-CRO
        $output->writeln('<info>[3/4] High-Conversion Landing Page:</info>');
        $output->writeln('  • UI Framework: Tailwind CSS v4 (design tokens, sub-second TTFB)');
        $output->writeln('  • Form-CRO: Lead capture multi-step con validación inline');
        $output->writeln('');

        // 4. DevOps & Cloud-Native
        $output->writeln('<info>[4/4] DevOps & Cloud-Native Delivery:</info>');
        $output->writeln("  • SSL Termination: <fg=cyan>{$profile['devops']['ssl']}</>");
        $output->writeln("  • Redis Caching: " . ($profile['devops']['redis_cache'] ? '<fg=green>Activo</>' : 'Desactivado'));
        $output->writeln("  • Retención Backups: {$profile['devops']['backup_retention_days']} días");
        $output->writeln("  • Paridad Git-Sync: preparado para <info>bedrock sync:stage / push</info>");
        $output->writeln('');

        if ($dryRun) {
            $output->writeln('<comment>[SIMULACIÓN] Modo --dry-run activo: ningún archivo fue alterado.</comment>');
            return Command::SUCCESS;
        }

        // Persist profile into ProfileService
        try {
            $profileService = new ProfileService();
            $profileService->saveProfile($projectName, $profile);
            $output->writeln("<fg=green>✓ Profile '{$projectName}' guardado exitosamente.</>");
            $output->writeln("Para instanciar el proyecto completo ejecuta:");
            $output->writeln("  <info>bedrock new {$projectName} --profile={$projectName}</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>Error al persistir el profile: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
