<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;

class PluginActivationService
{
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
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            return [];
        }

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        return array_values($plugins);
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

    private function runWithLoader(\Symfony\Component\Process\Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
