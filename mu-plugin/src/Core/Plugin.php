<?php

namespace BedrockCli\Plugin\Core;

use BedrockCli\Plugin\API\RestController;
use BedrockCli\Plugin\API\DashboardController;
use BedrockCli\Plugin\API\DockerController;
use BedrockCli\Plugin\API\LanguageController;
use BedrockCli\Plugin\Admin\AdminMenu;
use BedrockCli\Plugin\Utils\Logger;
use BedrockCli\Plugin\Core\Security;

class Plugin
{
    private Logger $logger;
    private RestController $restController;
    private DashboardController $dashboardController;
    private DockerController $dockerController;
    private LanguageController $languageController;
    private AdminMenu $adminMenu;
    private Security $security;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->restController = new RestController($this->logger);
        $this->dashboardController = new DashboardController();
        $this->dockerController = new DockerController();
        $this->languageController = new LanguageController();
        $this->adminMenu = new AdminMenu();
        $this->security = new Security();

        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        $this->logger->info('Bedrock CLI Plugin initialized');
    }

    public function registerRoutes(): void
    {
        $this->restController->registerRoutes();
        $this->dashboardController->registerRoutes();
        $this->dockerController->registerRoutes();
        $this->languageController->registerRoutes();
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['toplevel_page_bedrock-cli', 'bedrock-cli_page_bedrock-cli-logs'])) {
            return;
        }

        // Dashboard assets (FASE 1)
        if ($hook === 'toplevel_page_bedrock-cli') {
            wp_enqueue_style(
                'bedrock-cli-dashboard',
                plugins_url('assets/css/admin-dashboard.css', dirname(__DIR__, 2) . '/bedrock-cli-plugin.php'),
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'bedrock-cli-dashboard',
                plugins_url('assets/js/admin-dashboard.js', dirname(__DIR__, 2) . '/bedrock-cli-plugin.php'),
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('bedrock-cli-dashboard', 'bedrockCliSettings', [
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        }

        // Logs assets
        wp_enqueue_style(
            'bedrock-cli-logs',
            plugins_url('assets/css/admin-logs.css', dirname(__DIR__, 2) . '/bedrock-cli-plugin.php'),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'bedrock-cli-logs',
            plugins_url('assets/js/admin-logs.js', dirname(__DIR__, 2) . '/bedrock-cli-plugin.php'),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('bedrock-cli-logs', 'bedrockCliLogs', [
            'nonce' => wp_create_nonce('bedrock_cli_logs')
        ]);
    }
}
