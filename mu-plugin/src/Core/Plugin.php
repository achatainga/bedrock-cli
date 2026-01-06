<?php

namespace BedrockCli\Plugin\Core;

use BedrockCli\Plugin\API\RestController;
use BedrockCli\Plugin\API\DashboardController;
use BedrockCli\Plugin\API\DockerController;
use BedrockCli\Plugin\API\LanguageController;
use BedrockCli\Plugin\API\PluginController;
use BedrockCli\Plugin\Admin\AdminMenu;
use BedrockCli\Plugin\Admin\LogViewer;
use BedrockCli\Plugin\Utils\Logger;
use BedrockCli\Plugin\Core\Security;

class Plugin
{
    private Logger $logger;
    private RestController $restController;
    private DashboardController $dashboardController;
    private DockerController $dockerController;
    private LanguageController $languageController;
    private PluginController $pluginController;
    private AdminMenu $adminMenu;
    private LogViewer $logViewer;
    private Security $security;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->restController = new RestController($this->logger);
        $this->dashboardController = new DashboardController();
        $this->dockerController = new DockerController();
        $this->languageController = new LanguageController();
        $this->pluginController = new PluginController();
        $this->adminMenu = new AdminMenu();
        $this->logViewer = new LogViewer($this->logger);
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
        $this->pluginController->registerRoutes();
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['toplevel_page_bedrock-cli', 'bedrock-cli_page_bedrock-cli-plugins', 'bedrock-cli_page_bedrock-cli-logs'])) {
            return;
        }

        // Dashboard assets
        if ($hook === 'toplevel_page_bedrock-cli') {
            wp_enqueue_style(
                'bedrock-cli-dashboard',
                BEDROCK_CLI_PLUGIN_URL . '/assets/css/admin-dashboard.css',
                [],
                BEDROCK_CLI_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'bedrock-cli-dashboard',
                BEDROCK_CLI_PLUGIN_URL . '/assets/js/admin-dashboard.js',
                ['jquery'],
                BEDROCK_CLI_PLUGIN_VERSION,
                true
            );

            wp_localize_script('bedrock-cli-dashboard', 'bedrockCliSettings', [
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        }

        // Plugins assets
        if ($hook === 'bedrock-cli_page_bedrock-cli-plugins') {
            wp_enqueue_style(
                'bedrock-cli-dashboard',
                BEDROCK_CLI_PLUGIN_URL . '/assets/css/admin-dashboard.css',
                [],
                BEDROCK_CLI_PLUGIN_VERSION
            );

            wp_enqueue_style(
                'bedrock-cli-plugins',
                BEDROCK_CLI_PLUGIN_URL . '/assets/css/admin-plugins.css',
                [],
                BEDROCK_CLI_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'bedrock-cli-plugins',
                BEDROCK_CLI_PLUGIN_URL . '/assets/js/admin-plugins.js',
                ['jquery'],
                BEDROCK_CLI_PLUGIN_VERSION,
                true
            );

            wp_localize_script('bedrock-cli-plugins', 'bedrockCliSettings', [
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        }

        // Logs assets
        wp_enqueue_style(
            'bedrock-cli-logs',
            BEDROCK_CLI_PLUGIN_URL . '/assets/css/admin-logs.css',
            [],
            BEDROCK_CLI_PLUGIN_VERSION
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
