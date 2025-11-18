<?php

namespace Roots\BedrockCli\Commands\Cache;

use Roots\BedrockCli\Services\PremiumCacheService;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCommand extends Command
{
    protected static $defaultName = 'cache:import';
    private const DEFAULT_VERSION = 'imported-zip';
    private PremiumCacheService $cacheService;

    public function __construct(PremiumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cache:import')
            ->setDescription('Import a local .zip file to cache')
            ->addArgument('type', InputArgument::REQUIRED, 'Type: plugin or theme')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to .zip file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');
        $zipPath = $input->getArgument('path');

        if (!in_array($type, ['plugin', 'theme'])) {
            $io->error('Type must be "plugin" or "theme"');
            return Command::FAILURE;
        }

        if (!file_exists($zipPath)) {
            $io->error("File not found: {$zipPath}");
            return Command::FAILURE;
        }

        $io->section("📦 Importando " . basename($zipPath));



        try {
            $io->text('  ⏳ Extrayendo metadata...');
            $metadata = $this->cacheService->extractMetadataFromZip($zipPath, $type);

            $name = $metadata['name'];
            $version = $metadata['version'];
            $source = $metadata['source'];

            if (!$name) {
                $name = $io->ask('  ❓ Ingresa el nombre del ' . $type . ':');
            } else {
                $io->success("  ✓ Nombre detectado: {$name}");
            }

            if ($version && $source) {
                $io->success("  ✓ Versión detectada: {$version} (desde {$source})");

                $action = $io->choice(
                    '  ❓ ¿Qué deseas hacer?',
                    [
                        'usar' => "Usar versión {$version}",
                        'cambiar' => 'Cambiar versión',
                        'cancelar' => 'Cancelar importación'
                    ],
                    'usar'
                );

                if ($action === 'cancelar') {
                    $io->note('Importación cancelada');
                    return Command::SUCCESS;
                }

                if ($action === 'cambiar') {
                    $version = $io->ask('  ❓ Ingresa la versión correcta:', $version);
                }
            } else {
                $io->warning('  ⚠️  No se pudo detectar la versión automáticamente');
                $version = $io->ask(
                    '  ❓ Ingresa la versión (o presiona Enter para usar "' . self::DEFAULT_VERSION . '"):',
                    self::DEFAULT_VERSION
                );
            }

            if ($version !== self::DEFAULT_VERSION && !preg_match('/^\d+\.\d+(\.\d+)?/', $version)) {
                $io->error('  ❌ Formato de versión inválido. Usa: X.Y.Z o "' . self::DEFAULT_VERSION . '"');
                return Command::FAILURE;
            }

            $io->text('  ⏳ Copiando a cache...');
            $io->text('  ⏳ Extrayendo contenido...');
            $io->text('  ⏳ Generando composer.json...');

            $this->cacheService->importToCache($zipPath, $name, $version, $type);

            $io->newLine();
            $io->success("✅ {$name} {$version} importado exitosamente");
            $io->newLine();

            $home = getenv('USERPROFILE') ?: getenv('HOME');
            $typeDir = $type === 'plugin' ? 'plugins' : 'themes';
            $cachePath = "{$home}/.bedrock-cli/cache/premium/{$typeDir}/{$name}/{$version}/";

            $io->text([
                "📍 Ubicación: {$cachePath}",
                "🏷️  Vendor: cached/{$name}",
                ''
            ]);

            if ($version === self::DEFAULT_VERSION) {
                $io->note([
                    '💡 Para actualizar la versión más tarde, usa:',
                    "   bedrock cache:update-version {$name} " . self::DEFAULT_VERSION . " <nueva-version>"
                ]);
            }

            $io->text([
                '💡 Ahora puedes:',
                '   - Agregarlo a un profile',
                '   - Usarlo en manage ' . $type . 's'
            ]);

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
