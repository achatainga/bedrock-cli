<?php

namespace Roots\BedrockCli\Traits;

trait VendorExtractionTrait
{
    protected function extractVendorFromPlugin(array $plugin): string
    {
        if ($plugin['source'] === 'cache') {
            return 'cached';
        }
        
        if ($plugin['source'] === 'vcs' && !empty($plugin['url'])) {
            if (preg_match('#[:/]([^/]+)/[^/]+(?:\.git)?$#', $plugin['url'], $matches)) {
                return $matches[1];
            }
        }
        
        if (($plugin['source'] === 'path' || $plugin['source'] === 'zip') && !empty($plugin['path'])) {
            $composerPath = $plugin['path'] . '/composer.json';
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                if (!empty($composer['name'])) {
                    return explode('/', $composer['name'])[0];
                }
            }
        }
        
        return 'local';
    }
}
