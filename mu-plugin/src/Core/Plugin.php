<?php

namespace BedrockCli\Plugin\Core;

use BedrockCli\Plugin\API\RestController;
use BedrockCli\Plugin\Admin\LogViewer;
use BedrockCli\Plugin\Utils\Logger;

class Plugin
{
    private Logger $logger;
    private RestController $restController;
    private LogViewer $logViewer;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->restController = new RestController($this->logger);
        $this->logViewer = new LogViewer($this->logger);

        add_action('rest_api_init', [$this->restController, 'registerRoutes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        $this->logger->info('Bedrock CLI Plugin initialized');
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'tools_page_bedrock-cli-logs') {
            return;
        }

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
