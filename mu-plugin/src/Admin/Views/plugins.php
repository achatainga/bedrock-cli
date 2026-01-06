<div class="wrap bedrock-dashboard">
    <div class="bd-header">
        <h1>🔌 Gestión de Plugins</h1>
    </div>

    <div class="bd-tabs">
        <button class="bd-tab active" data-tab="installed">Instalados</button>
        <button class="bd-tab" data-tab="search">Buscar e Instalar</button>
    </div>

    <!-- Tab: Instalados -->
    <div class="bd-tab-content active" id="tab-installed">
        <div id="plugins-list" class="bd-content-loading">
            <div class="bd-spinner"></div> Cargando plugins...
        </div>
    </div>

    <!-- Tab: Buscar -->
    <div class="bd-tab-content" id="tab-search">
        <div class="bd-search-box">
            <input type="text" id="plugin-search" placeholder="Buscar plugins en WordPress.org..." />
            <button id="btn-search" class="button button-primary">Buscar</button>
        </div>
        <div id="search-results"></div>
    </div>
</div>
