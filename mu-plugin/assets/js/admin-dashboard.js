jQuery(document).ready(function($) {
    const API_ROOT = '/wp-json/bedrock-cli/v1';
    const NONCE = bedrockCliSettings.nonce;

    function api(endpoint, method = 'GET', data = {}) {
        return $.ajax({
            url: API_ROOT + endpoint,
            method: method,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
            data: JSON.stringify(data),
            contentType: 'application/json'
        });
    }

    // 1. Cargar Estado
    function loadStatus() {
        api('/system/status').done(function(res) {
            if (!res.success) return;
            const data = res.data;
            
            $('#system-mode').text(data.system.os);
            $('#php-version').text('PHP ' + data.system.php_version);
            
            let html = '<ul class="bd-status-list">';
            html += `<li><strong>CLI:</strong> ${data.cli_status}</li>`;
            html += `<li><strong>OS:</strong> ${data.system.os}</li>`;
            html += `<li><strong>PHP:</strong> ${data.system.php_version}</li>`;
            html += '</ul>';
            
            $('#health-status').html(html).removeClass('bd-content-loading');
        }).fail(function() {
            $('#health-status').html('<p style="color:red;">Error loading status</p>').removeClass('bd-content-loading');
        });
    }

    // 2. Cargar Idiomas
    function loadLanguages() {
        api('/languages').done(function(res) {
            $('#current-lang').text(res.current);
            const select = $('#language-select');
            $.each(res.available, function(code, name) {
                select.append(new Option(name, code, false, code === res.current));
            });
        });
    }

    // Eventos
    $('#btn-change-lang').click(function() {
        const btn = $(this);
        const locale = $('#language-select').val();
        btn.prop('disabled', true).text('Instalando...');
        
        api('/languages/install', 'POST', { locale: locale })
            .done(function(res) {
                alert(res.message);
                location.reload();
            })
            .fail(function() { alert('Error al cambiar idioma'); })
            .always(function() { btn.prop('disabled', false).text('Cambiar'); });
    });

    $('#btn-docker-restart').click(function() {
        if (!confirm('¿Seguro que deseas reiniciar los servicios?')) return;
        
        const term = $('#docker-output');
        term.text('Reiniciando...').show();
        
        api('/docker/control', 'POST', { action: 'restart' })
            .done(function(res) {
                term.text(res.output || res.message);
            })
            .fail(function(xhr) {
                term.text('Error: ' + (xhr.responseJSON?.error || 'Desconocido'));
            });
    });

    // Init
    loadStatus();
    loadLanguages();
});
