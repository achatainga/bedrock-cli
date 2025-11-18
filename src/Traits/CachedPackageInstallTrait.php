<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Output\OutputInterface;

trait CachedPackageInstallTrait
{
    private function installCachedPackage(
        string $package,
        string $version,
        string $cachePath,
        OutputInterface $output
    ): int {
        $this->management->updateComposerJson(function ($composer) use ($package, $version, $cachePath) {
            if (!isset($composer['repositories'])) {
                $composer['repositories'] = [];
            }

            $composer['repositories'][] = [
                'type' => 'path',
                'url' => $cachePath,
                'options' => ['symlink' => true]
            ];

            if (!isset($composer['require'])) {
                $composer['require'] = [];
            }

            $composer['require'][$package] = $version;

            return $composer;
        });

        return $this->dependencyManager->update(
            [$package],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );
    }
}
