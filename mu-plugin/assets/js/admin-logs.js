jQuery(document).ready(function($) {
    const config = bedrockCliLogs;

    $('#refresh-logs').on('click', function() {
        $.post(config.ajax_url, {
            action: 'bedrock_cli_get_logs',
            nonce: config.nonce
        }, function(response) {
            if (response.success) {
                $('#log-content pre').text(response.data.logs);
                $('.log-stats').html(
                    'Size: ' + formatBytes(response.data.stats.size) + ' | ' +
                    'Lines: ' + response.data.stats.lines.toLocaleString()
                );
            }
        });
    });

    $('#clear-logs').on('click', function() {
        if (!confirm('Clear all logs?')) return;
        
        $.post(config.ajax_url, {
            action: 'bedrock_cli_clear_logs',
            nonce: config.nonce
        }, function(response) {
            if (response.success) {
                $('#log-content pre').text('');
                $('.log-stats').html('Size: 0 B | Lines: 0');
            }
        });
    });

    $('#debug-mode').on('change', function() {
        $.post(config.ajax_url, {
            action: 'bedrock_cli_toggle_debug',
            nonce: config.nonce,
            enabled: $(this).is(':checked') ? 'true' : 'false'
        });
    });

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
