<?php
namespace BedrockCli\Plugin\Admin;

use BedrockCli\Plugin\Utils\Logger;

class LogViewer
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_bedrock_cli_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_bedrock_cli_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_bedrock_cli_toggle_debug', [$this, 'ajax_toggle_debug']);
    }

    public function add_menu()
    {
        add_management_page(
            'Bedrock CLI Logs',
            'Bedrock CLI Logs',
            'manage_options',
            'bedrock-cli-logs',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'tools_page_bedrock-cli-logs') return;

        wp_enqueue_style(
            'bedrock-cli-logs',
            plugins_url('../../assets/css/admin-logs.css', __FILE__),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'bedrock-cli-logs',
            plugins_url('../../assets/js/admin-logs.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('bedrock-cli-logs', 'bedrockCliLogs', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bedrock_cli_logs')
        ]);
    }

    public function render_page()
    {
        $stats = Logger::get_log_stats();
        $debug_mode = Logger::is_debug_mode();
        ?>
        <div class="wrap bedrock-cli-logs">
            <h1>Bedrock CLI Plugin - Logs</h1>
            
            <div class="log-controls">
                <button id="refresh-logs" class="button">Refresh</button>
                <button id="clear-logs" class="button">Clear Logs</button>
                <label>
                    <input type="checkbox" id="debug-mode" <?php checked($debug_mode); ?>>
                    Debug Mode
                </label>
                <span class="log-stats">
                    Size: <?php echo size_format($stats['size']); ?> | 
                    Lines: <?php echo number_format($stats['lines']); ?>
                </span>
            </div>

            <div id="log-content" class="log-content">
                <pre><?php echo esc_html(Logger::get_logs(500)); ?></pre>
            </div>
        </div>
        <?php
    }

    public function ajax_get_logs()
    {
        check_ajax_referer('bedrock_cli_logs', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        wp_send_json_success([
            'logs' => Logger::get_logs(500),
            'stats' => Logger::get_log_stats()
        ]);
    }

    public function ajax_clear_logs()
    {
        check_ajax_referer('bedrock_cli_logs', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        Logger::clear_logs();
        wp_send_json_success(['message' => 'Logs cleared']);
    }

    public function ajax_toggle_debug()
    {
        check_ajax_referer('bedrock_cli_logs', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        Logger::set_debug_mode($enabled);
        wp_send_json_success(['debug_mode' => $enabled]);
    }
}
