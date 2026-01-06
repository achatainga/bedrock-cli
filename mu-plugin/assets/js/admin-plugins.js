jQuery(document).ready(function($) {
    console.log('Bedrock CLI Plugins JS loaded');
    const API_ROOT = '/wp-json/bedrock-cli/v1';
    const NONCE = bedrockCliSettings.nonce;
    console.log('API_ROOT:', API_ROOT, 'NONCE:', NONCE);

    function api(endpoint, method = 'GET', data = {}) {
        return $.ajax({
            url: API_ROOT + endpoint,
            method: method,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
            data: method === 'GET' ? data : JSON.stringify(data),
            contentType: 'application/json'
        });
    }

    // Tabs
    $('.bd-tab').click(function() {
        $('.bd-tab').removeClass('active');
        $('.bd-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#tab-' + $(this).data('tab')).addClass('active');
    });

    // Cargar plugins instalados
    function loadInstalledPlugins() {
        api('/plugins').done(function(res) {
            if (!res.success) return;
            
            let html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Plugin</th><th>Versión</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
            
            res.plugins.forEach(function(plugin) {
                html += '<tr>';
                html += `<td><strong>${plugin.slug}</strong></td>`;
                html += `<td>${plugin.version}</td>`;
                html += `<td>${plugin.active ? '<span class="dashicons dashicons-yes-alt" style="color:green;"></span> Activo' : '<span class="dashicons dashicons-minus"></span> Inactivo'}</td>`;
                html += '<td>';
                if (plugin.active) {
                    html += `<button class="button btn-deactivate" data-slug="${plugin.slug}">Desactivar</button> `;
                } else {
                    html += `<button class="button button-primary btn-activate" data-slug="${plugin.slug}">Activar</button> `;
                }
                html += `<button class="button btn-uninstall" data-slug="${plugin.slug}" style="color:#b32d2e;">Desinstalar</button>`;
                html += '</td></tr>';
            });
            
            html += '</tbody></table>';
            $('#plugins-list').html(html).removeClass('bd-content-loading');
        });
    }

    // Buscar plugins
    $('#btn-search').click(function() {
        const query = $('#plugin-search').val();
        if (!query) return;

        $('#search-results').html('<p>Buscando...</p>');
        
        api('/plugins/search?q=' + encodeURIComponent(query)).done(function(res) {
            if (!res.success || !res.plugins.length) {
                $('#search-results').html('<p>No se encontraron plugins</p>');
                return;
            }

            let html = '<div class="bd-plugin-grid">';
            res.plugins.forEach(function(plugin) {
                html += '<div class="bd-plugin-card">';
                html += `<h3>${plugin.name}</h3>`;
                html += `<p>${plugin.short_description || ''}</p>`;
                html += `<p><small>Por ${plugin.author} | ${plugin.active_installs}+ instalaciones</small></p>`;
                html += `<button class="button button-primary bd-btn-install" data-slug="${plugin.slug}">Instalar</button>`;
                html += '</div>';
            });
            html += '</div>';
            
            $('#search-results').html(html);
        });
    });

    // Instalar plugin
    $(document).on('click', '.bd-btn-install', function() {
        console.log('Install button clicked!');
        const btn = $(this);
        const slug = btn.data('slug');
        console.log('Installing:', slug);
        
        if (!confirm(`¿Instalar ${slug}?`)) {
            console.log('Installation cancelled');
            return;
        }
        
        console.log('Starting installation...');
        btn.prop('disabled', true).text('Instalando...');
        
        console.log('Calling API:', '/plugins/install', {slug: slug});
        api('/plugins/install', 'POST', { slug: slug }).done(function(res) {
            console.log('API response:', res);
            alert(res.message);
            if (res.success) {
                loadInstalledPlugins();
                $('.bd-tab[data-tab="installed"]').click();
            }
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'No se pudo instalar'));
        }).always(function() {
            btn.prop('disabled', false).text('Instalar');
        });
    });

    // Activar plugin
    $(document).on('click', '.btn-activate', function() {
        const btn = $(this);
        const slug = btn.data('slug');
        
        btn.prop('disabled', true).text('Activando...');
        
        api('/plugin/activate', 'POST', { slug: slug }).done(function(res) {
            alert(res.message);
            if (res.success) loadInstalledPlugins();
        }).always(function() {
            btn.prop('disabled', false).text('Activar');
        });
    });

    // Desactivar plugin
    $(document).on('click', '.btn-deactivate', function() {
        const btn = $(this);
        const slug = btn.data('slug');
        
        btn.prop('disabled', true).text('Desactivando...');
        
        api('/plugin/deactivate', 'POST', { slug: slug }).done(function(res) {
            alert(res.message);
            if (res.success) loadInstalledPlugins();
        }).always(function() {
            btn.prop('disabled', false).text('Desactivar');
        });
    });

    // Desinstalar plugin
    $(document).on('click', '.btn-uninstall', function() {
        const btn = $(this);
        const slug = btn.data('slug');
        
        if (!confirm(`¿DESINSTALAR ${slug}? Esto eliminará el plugin completamente.`)) return;
        
        btn.prop('disabled', true).text('Desinstalando...');
        
        api('/plugin/uninstall', 'POST', { slug: slug }).done(function(res) {
            alert(res.message);
            if (res.success) loadInstalledPlugins();
        }).fail(function() {
            alert('Error al desinstalar');
        }).always(function() {
            btn.prop('disabled', false).text('Desinstalar');
        });
    });

    // Enter en búsqueda
    $('#plugin-search').keypress(function(e) {
        if (e.which === 13) $('#btn-search').click();
    });

    // Init
    loadInstalledPlugins();
});
