<?php

namespace BedrockCli\Plugin\Core;

class Security
{
    public function __construct()
    {
        add_filter('user_has_cap', [$this, 'disablePluginThemeInstallation'], 10, 4);
    }

    public function disablePluginThemeInstallation(array $allcaps, array $caps, array $args, $user): array
    {
        if (isset($args[0]) && in_array($args[0], [
            'install_plugins',
            'activate_plugins',
            'deactivate_plugins',
            'delete_plugins',
            'install_themes',
            'switch_themes',
            'delete_themes',
            'edit_plugins',
            'edit_themes'
        ])) {
            $allcaps[$args[0]] = false;
        }

        return $allcaps;
    }
}
