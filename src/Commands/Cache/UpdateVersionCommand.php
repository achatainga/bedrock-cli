<?php

namespace Roots\BedrockCli\Commands\Cache;

use Roots\BedrockCli\Services\PremiumCacheService;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateVersionCommand extends Command
{
    protected static $defaultName = 'cache:update-version';
    private PremiumCacheService $cacheService;

    public function __construct(PremiumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cache:update-version')
            ->setDescription('Update version of cached plugin/theme')
            ->addArgument('name', InputArgument::REQUIRED, 'Plugin/theme name')
            ->addArgument('old-version', InputArgument::REQUIRED, 'Current version')
            ->addArgument('new-version', InputArgument::REQUIRED, 'New version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $oldVersion = $input->getArgument('old-version');
        $newVersion = $input->getArgument('new-version');

        if (!preg_match('/^\d+\.\d+(\.\d+)?/', $newVersion)) {
            $io->error('Formato de versión inválido. Usa: X.Y.Z');
            return Command::FAILURE;
        }



        try {
            $type = $this->cacheService->updateCacheVersion($name, $oldVersion, $newVersion);

            $io->success("✅ Versión actualizada: {$name} {$oldVersion} → {$newVersion}");

            $home = getenv('USERPROFILE') ?: getenv('HOME');
            $typeDir = $type === 'plugin' ? 'plugins' : 'themes';
            $cachePath = "{$home}/.bedrock-cli/cache/premium/{$typeDir}/{$name}/{$newVersion}/";

            $io->text([
                '',
                "📍 Nueva ubicación: {$cachePath}",
                "🏷️  Vendor: cached/{$name}:{$newVersion}"
            ]);

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
