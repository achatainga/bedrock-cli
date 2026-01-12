<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Traits\SpinnerTrait;
use Roots\BedrockCli\Traits\FileSystemTrait;

class PluginActivationService
{
    use SpinnerTrait, FileSystemTrait;
    
    private WpCliService $wpCliService;

    public function __construct(WpCliService $wpCliService)
    {
        $this->wpCliService = $wpCliService;
    }

    /**
     * Get available plugins from filesystem
     */
    public function getAvailablePlugins(string $projectRoot): array
    {
        return $this->scanDirectory($projectRoot . '/web/app/plugins');
    }

    /**
     * Parse input string to plugin slugs
     * Supports: "1,3,5" or "1-5,7,9"
     */
    public function parsePluginSelection(string $input, array $availablePlugins): array
    {
        $parts = array_map('trim', explode(',', $input));
        $resolved = [];

        foreach ($parts as $part) {
            // Range: 1-5
            if (strpos($part, '-') !== false) {
                [$start, $end] = explode('-', $part);
                $start = (int)trim($start);
                $end = (int)trim($end);
                
                for ($i = $start; $i <= $end; $i++) {
                    $index = $i - 1;
                    if (isset($availablePlugins[$index])) {
                        $resolved[] = $availablePlugins[$index];
                    }
                }
            }
            // Single number
            elseif (is_numeric($part)) {
                $index = (int)$part - 1;
                if (isset($availablePlugins[$index])) {
                    $resolved[] = $availablePlugins[$index];
                }
            }
        }

        return array_unique($resolved);
    }

    /**
     * Activate multiple plugins in order
     */
    public function activateMultiple(array $plugins, OutputInterface $output): array
    {
        $results = [];

        foreach ($plugins as $plugin) {
            $process = $this->wpCliService->custom("plugin activate {$plugin}");
            $this->runWithLoader($process, $output, "Activando {$plugin}");

            $results[$plugin] = [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput()
            ];

            if ($process->isSuccessful()) {
                $output->writeln("<info>✓ Plugin '{$plugin}' activado</info>");
            } else {
                $output->writeln("<error>✗ Error activando '{$plugin}'</error>");
            }
        }

        return $results;
    }


}
