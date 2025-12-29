<?php

namespace Roots\BedrockCli\Services;

class OrderValidator
{
    public function validateAgainstProfile(array $order, array $profile): array
    {
        $profilePlugins = $this->extractProfilePlugins($profile);
        $orderPlugins = array_keys($order);
        
        $missing = array_diff($orderPlugins, $profilePlugins);
        $extra = array_diff($profilePlugins, $orderPlugins);
        
        return [
            'valid' => empty($missing),
            'missing' => array_values($missing),
            'extra' => array_values($extra)
        ];
    }
    
    private function extractProfilePlugins(\Roots\BedrockCli\DTOs\Profile|array $profile): array
    {
        $plugins = [];
        
        if (isset($profile['plugins']['public'])) {
            foreach ($profile['plugins']['public'] as $plugin) {
                $plugins[] = $plugin['slug'];
            }
        }
        
        if (isset($profile['plugins']['premium'])) {
            foreach ($profile['plugins']['premium'] as $plugin) {
                $plugins[] = $plugin['slug'];
            }
        }
        
        return $plugins;
    }
}
