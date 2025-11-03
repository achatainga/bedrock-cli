<?php

namespace Roots\BedrockCli\Services\Management;

use RuntimeException;
use Symfony\Component\Process\Process;

class DependencyManager
{
    private ManagementService $management;

    public function __construct(ManagementService $management)
    {
        $this->management = $management;
    }

    public function add(string $package, ?string $version = null, bool $dev = false): void
    {
        $versionConstraint = $version ?? '*';

        $this->management->updateComposerJson(function ($composer) use ($package, $versionConstraint, $dev) {
            $section = $dev ? 'require-dev' : 'require';

            if (!isset($composer[$section])) {
                $composer[$section] = [];
            }

            $composer[$section][$package] = $versionConstraint;

            return $composer;
        });

        $this->management->addChange('dependency_added', [
            'package' => $package,
            'version' => $versionConstraint,
            'dev' => $dev
        ]);
    }

    public function remove(string $package): void
    {
        $this->management->updateComposerJson(function ($composer) use ($package) {
            if (isset($composer['require'][$package])) {
                unset($composer['require'][$package]);
            }

            if (isset($composer['require-dev'][$package])) {
                unset($composer['require-dev'][$package]);
            }

            return $composer;
        });

        $this->management->addChange('dependency_removed', [
            'package' => $package
        ]);
    }

    public function list(bool $devOnly = false): array
    {
        $composer = $this->management->readComposerJson();
        $dependencies = [];

        if (!$devOnly && isset($composer['require'])) {
            foreach ($composer['require'] as $package => $version) {
                $dependencies[$package] = [
                    'package' => $package,
                    'version' => $version,
                    'dev' => false
                ];
            }
        }

        if (isset($composer['require-dev'])) {
            foreach ($composer['require-dev'] as $package => $version) {
                $dependencies[$package] = [
                    'package' => $package,
                    'version' => $version,
                    'dev' => true
                ];
            }
        }

        return $dependencies;
    }

    public function exists(string $package): bool
    {
        $composer = $this->management->readComposerJson();

        return isset($composer['require'][$package]) || 
               isset($composer['require-dev'][$package]);
    }

    public function install(bool $noDev = false, ?callable $outputCallback = null): int
    {
        $root = $this->management->getProjectRoot();
        $command = ['composer', 'install'];

        if ($noDev) {
            $command[] = '--no-dev';
        }

        return $this->runComposerCommand($command, $root, $outputCallback);
    }

    public function update(?array $packages = null, ?callable $outputCallback = null): int
    {
        $root = $this->management->getProjectRoot();
        $command = ['composer', 'update'];

        if ($packages) {
            $command = array_merge($command, $packages);
        }

        return $this->runComposerCommand($command, $root, $outputCallback);
    }

    public function require(string $package, ?string $version = null, bool $dev = false, ?callable $outputCallback = null): int
    {
        $root = $this->management->getProjectRoot();
        $packageSpec = $version ? "{$package}:{$version}" : $package;
        
        $command = ['composer', 'require'];
        
        if ($dev) {
            $command[] = '--dev';
        }
        
        $command[] = $packageSpec;

        return $this->runComposerCommand($command, $root, $outputCallback);
    }

    private function runComposerCommand(array $command, string $cwd, ?callable $outputCallback = null): int
    {
        if ($this->management->isDryRun()) {
            if ($outputCallback) {
                $outputCallback("[DRY RUN] " . implode(' ', $command));
            }
            return 0;
        }

        $process = new Process($command, $cwd);
        $process->setTimeout(300);

        if ($outputCallback) {
            $process->run(function ($type, $buffer) use ($outputCallback) {
                $outputCallback($buffer);
            });
        } else {
            $process->run();
        }

        return $process->getExitCode();
    }
}
