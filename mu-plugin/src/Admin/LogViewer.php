<?php

namespace BedrockCli\Plugin\Admin;

use BedrockCli\Plugin\Utils\Logger;

class LogViewer
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        add_action('wp_ajax_bedrock_cli_get_logs', [$this, 'ajaxGetLogs']);
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Bedrock CLI Logs</h1>
            
            <div class="bedrock-cli-logs-controls">
                <button id="refresh-logs" class="button">Refresh</button>
                <label>
                    <input type="checkbox" id="auto-refresh"> Auto-refresh (5s)
                </label>
                <select id="log-level">
                    <option value="">All Levels</option>
                    <option value="DEBUG">Debug</option>
                    <option value="INFO">Info</option>
                    <option value="WARNING">Warning</option>
                    <option value="ERROR">Error</option>
                </select>
            </div>

            <div id="logs-container" class="bedrock-cli-logs">
                <p>Loading logs...</p>
            </div>
        </div>
        <?php
    }

    public function ajaxGetLogs(): void
    {
        if (!check_ajax_referer('bedrock_cli_logs', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $lines = isset($_POST['lines']) ? intval($_POST['lines']) : 100;
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';

        $logs = $this->logger->getLogs($lines);

        if ($level) {
            $logs = array_filter($logs, fn($log) => strpos($log, "[{$level}]") !== false);
        }

        wp_send_json_success(['logs' => array_values($logs)]);
    }
}
