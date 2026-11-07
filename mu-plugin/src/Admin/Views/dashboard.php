<div class="wrap bedrock-dashboard">
    <div class="bd-header">
        <h1>🚀 Bedrock Control Center</h1>
        <div class="bd-badges">
            <span id="system-mode" class="bd-badge">Cargando...</span>
            <span id="php-version" class="bd-badge">PHP ...</span>
        </div>
    </div>

    <div class="bd-grid">
        <!-- System Health -->
        <div class="bd-card">
            <h3>🏥 Estado del Sistema</h3>
            <div id="health-status" class="bd-content-loading">
                <div class="bd-spinner"></div> Analizando...
            </div>
        </div>

        <!-- Language Tools -->
        <div class="bd-card">
            <h3>🌐 Idioma</h3>
            <div class="bd-content">
                <p>Idioma actual: <strong id="current-lang">...</strong></p>
                <div class="bd-form-row">
                    <select id="language-select"></select>
                    <button id="btn-change-lang" class="button button-primary">Cambiar</button>
                </div>
            </div>
        </div>

        <!-- Docker Actions -->
        <div class="bd-card">
            <h3>🐳 Docker (Seguro)</h3>
            <div class="bd-content">
                <p class="description">Acciones seguras para contenedores.</p>
                <button id="btn-docker-restart" class="button button-secondary">
                    🔄 Reiniciar Servicios
                </button>
                <div id="docker-output" class="bd-terminal"></div>
            </div>
        </div>
    </div>
</div>
