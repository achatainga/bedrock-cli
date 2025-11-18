(function($) {
    'use strict';

    let autoRefreshInterval = null;

    function loadLogs() {
        const level = $('#log-level').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bedrock_cli_get_logs',
                nonce: bedrockCliLogs.nonce,
                lines: 100,
                level: level
            },
            success: function(response) {
                if (response.success) {
                    displayLogs(response.data.logs);
                }
            }
        });
    }

    function displayLogs(logs) {
        const container = $('#logs-container');
        
        if (logs.length === 0) {
            container.html('<p>No logs found.</p>');
            return;
        }

        let html = '<pre>';
        logs.forEach(function(log) {
            const level = log.match(/\[(DEBUG|INFO|WARNING|ERROR)\]/);
            const levelClass = level ? 'log-level-' + level[1] : '';
            html += '<div class="log-line ' + levelClass + '">' + escapeHtml(log) + '</div>';
        });
        html += '</pre>';
        
        container.html(html);
        container.scrollTop(container[0].scrollHeight);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    $(document).ready(function() {
        loadLogs();

        $('#refresh-logs').on('click', function() {
            loadLogs();
        });

        $('#log-level').on('change', function() {
            loadLogs();
        });

        $('#auto-refresh').on('change', function() {
            if ($(this).is(':checked')) {
                autoRefreshInterval = setInterval(loadLogs, 5000);
            } else {
                clearInterval(autoRefreshInterval);
            }
        });
    });
})(jQuery);
