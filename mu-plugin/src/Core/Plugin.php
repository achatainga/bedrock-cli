<?php
namespace BedrockCli\Plugin\Core;

use BedrockCli\Plugin\API\RestController;
use BedrockCli\Plugin\Admin\LogViewer;

class Plugin
{
    public function __construct()
    {
        $this->initHooks();
    }

    private function initHooks(): void
    {
        add_action('rest_api_init', [$this, 'initRestApi']);
        
        if (is_admin()) {
            add_action('admin_menu', [$this, 'initAdmin'], 5);
        }
    }

    public function initRestApi(): void
    {
        new RestController();
    }

    public function initAdmin(): void
    {
        new LogViewer();
    }
}
