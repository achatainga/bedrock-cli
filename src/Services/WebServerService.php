<?php

namespace Roots\BedrockCli\Services;

class WebServerService
{
    private const NGINX_PATHS = ['/usr/sbin/nginx', '/usr/bin/nginx'];
    private const APACHE_PATHS = ['/usr/sbin/apache2', '/usr/bin/apache2', '/usr/sbin/httpd'];
    
    /**
     * Detecta qué web server está instalado en el sistema
     * 
     * @return string|null 'nginx', 'apache', o null si ninguno
     */
    public function detectWebServer(): ?string
    {
        // Verificar nginx
        foreach (self::NGINX_PATHS as $path) {
            if (file_exists($path)) {
                return 'nginx';
            }
        }
        
        // Verificar con which
        exec('which nginx 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            return 'nginx';
        }
        
        // Verificar apache
        foreach (self::APACHE_PATHS as $path) {
            if (file_exists($path)) {
                return 'apache';
            }
        }
        
        exec('which apache2 2>/dev/null || which httpd 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            return 'apache';
        }
        
        return null;
    }
    
    /**
     * Verifica si hay otros proyectos en /var/www
     * 
     * @return array Lista de directorios en /var/www
     */
    public function detectExistingProjects(): array
    {
        $projects = [];
        $wwwPath = '/var/www';
        
        if (!is_dir($wwwPath)) {
            return $projects;
        }
        
        $dirs = scandir($wwwPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || $dir === 'html') {
                continue;
            }
            
            $fullPath = $wwwPath . '/' . $dir;
            if (is_dir($fullPath)) {
                $projects[] = $dir;
            }
        }
        
        return $projects;
    }
    
    /**
     * Determina la estrategia recomendada
     * 
     * @param string|null $webServer
     * @param int $httpPort
     * @return array ['strategy' => 'direct'|'reverse-proxy', 'reason' => string]
     */
    public function recommendStrategy(?string $webServer, int $httpPort): array
    {
        // Sin web server del sistema: usar Docker directo
        if ($webServer === null) {
            return [
                'strategy' => 'direct',
                'reason' => 'No hay web server del sistema instalado'
            ];
        }
        
        // Puerto 80 libre y sin otros proyectos: puede usar directo
        $existingProjects = $this->detectExistingProjects();
        if ($httpPort === 80 && empty($existingProjects)) {
            return [
                'strategy' => 'direct',
                'reason' => 'Puerto 80 libre y sin otros proyectos',
                'alternative' => 'reverse-proxy para escalabilidad futura'
            ];
        }
        
        // Puerto diferente a 80 o hay otros proyectos: reverse proxy
        if ($httpPort !== 80 || !empty($existingProjects)) {
            return [
                'strategy' => 'reverse-proxy',
                'reason' => $httpPort !== 80 
                    ? "Puerto {$httpPort} requiere reverse proxy para acceso en puerto 80"
                    : 'Servidor multi-proyecto detectado',
                'projects' => $existingProjects
            ];
        }
        
        return [
            'strategy' => 'reverse-proxy',
            'reason' => 'Recomendado para escalabilidad'
        ];
    }
    
    /**
     * Genera configuración nginx para reverse proxy
     * 
     * @param string $projectName
     * @param string $domain
     * @param int $dockerPort
     * @return string Contenido del archivo de configuración
     */
    public function generateNginxConfig(string $projectName, string $domain, int $port, bool $isHybrid = false): string
    {
        if ($isHybrid) {
            // Modo híbrido: nginx sirve directamente PHP-FPM en el puerto especificado
            return <<<NGINX
server {
    listen {$port};
    server_name {$domain};
    
    root /var/www/{$projectName}/web;
    index index.php index.html;
    
    # Logs
    access_log /var/log/nginx/{$projectName}-access.log;
    error_log /var/log/nginx/{$projectName}-error.log;
    
    # WordPress permalinks
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }
    
    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }
    
    location = /robots.txt {
        log_not_found off;
        access_log off;
    }
    
    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires max;
        log_not_found off;
    }
}

NGINX;
        }
        
        // Modo full: reverse proxy en puerto 80 a Docker
        return <<<NGINX
server {
    listen 80;
    server_name {$domain};
    
    # Logging
    access_log /var/log/nginx/{$projectName}-access.log;
    error_log /var/log/nginx/{$projectName}-error.log;
    
    # Reverse proxy to Docker nginx
    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_buffering off;
        
        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}

NGINX;
    }
    
    /**
     * Genera configuración Apache para reverse proxy
     * 
     * @param string $projectName
     * @param string $domain
     * @param int $dockerPort
     * @return string Contenido del archivo de configuración
     */
    public function generateApacheConfig(string $projectName, string $domain, int $dockerPort): string
    {
        return <<<APACHE
<VirtualHost *:80>
    ServerName {$domain}
    
    # Logging
    ErrorLog \${APACHE_LOG_DIR}/{$projectName}-error.log
    CustomLog \${APACHE_LOG_DIR}/{$projectName}-access.log combined
    
    # Reverse proxy to Docker nginx
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:{$dockerPort}/
    ProxyPassReverse / http://127.0.0.1:{$dockerPort}/
    
    # WebSocket support
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://127.0.0.1:{$dockerPort}/\$1" [P,L]
</VirtualHost>

APACHE;
    }
    
    /**
     * Instala configuración nginx
     * 
     * @param string $projectName
     * @param string $configContent
     * @return array ['success' => bool, 'message' => string]
     */
    public function installNginxConfig(string $projectName, string $configContent): array
    {
        $availablePath = "/etc/nginx/sites-available/{$projectName}";
        $enabledPath = "/etc/nginx/sites-enabled/{$projectName}";
        
        // Crear archivo en sites-available
        $tempFile = tempnam(sys_get_temp_dir(), 'nginx_');
        file_put_contents($tempFile, $configContent);
        
        // Copiar con sudo
        exec("sudo cp {$tempFile} {$availablePath} 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Error al copiar configuración: ' . implode("\n", $output)
            ];
        }
        
        // Crear symlink en sites-enabled
        exec("sudo ln -sf {$availablePath} {$enabledPath} 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Error al activar sitio: ' . implode("\n", $output)
            ];
        }
        
        // Verificar configuración
        exec('sudo nginx -t 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Revertir cambios
            exec("sudo rm {$enabledPath}");
            exec("sudo rm {$availablePath}");
            
            return [
                'success' => false,
                'message' => 'Configuración nginx inválida: ' . implode("\n", $output)
            ];
        }
        
        // Recargar nginx
        exec('sudo systemctl reload nginx 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Guardar archivo localmente para uso manual
            $localPath = getcwd() . "/{$projectName}/{$projectName}-nginx.conf";
            file_put_contents($localPath, $configContent);
            
            return [
                'success' => false,
                'message' => 'Error al recargar nginx: ' . implode("\n", $output),
                'config_file' => $localPath
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Configuración nginx instalada y activada correctamente'
        ];
    }
    
    /**
     * Verifica si un puerto está libre
     * 
     * @param int $port
     * @return bool
     */
    public function isPortFree(int $port): bool
    {
        // Verificar localhost
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        
        // Verificar 0.0.0.0 (all interfaces)
        $connection = @fsockopen('0.0.0.0', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        
        // NUEVO: Verificar IP externa del servidor (cPanel/GoDaddy)
        $externalIp = $this->getExternalServerIp();
        if ($externalIp) {
            $connection = @fsockopen($externalIp, $port, $errno, $errstr, 1);
            if (is_resource($connection)) {
                fclose($connection);
                return false;
            }
        }
        
        // Verificar con netstat/ss si está disponible (más confiable)
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $cmd = "ss -tuln 2>/dev/null | grep -E ':{$port}\\s' || netstat -tuln 2>/dev/null | grep -E ':{$port}\\s'";
            exec($cmd, $output, $returnCode);
            if (!empty($output)) {
                return false; // Puerto en uso
            }
        }
        
        return true;
    }
    
    /**
     * Encuentra un puerto libre a partir de uno preferido
     * 
     * @param int $preferred
     * @return int
     */
    public function findFreePort(int $preferred): int
    {
        $port = $preferred;
        $maxAttempts = 100;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($this->isPortFree($port)) {
                return $port;
            }
            $port++;
        }
        
        return $preferred;
    }
    
    /**
     * Detecta el document root del web server
     * 
     * @return string|null Ruta base donde se sirven proyectos
     */
    public function detectDocumentRoot(): ?string
    {
        $webServer = $this->detectWebServer();
        
        if ($webServer === 'nginx') {
            return $this->detectNginxDocumentRoot();
        }
        
        if ($webServer === 'apache') {
            return $this->detectApacheDocumentRoot();
        }
        
        return null;
    }
    
    /**
     * Detecta document root de nginx
     */
    private function detectNginxDocumentRoot(): ?string
    {
        $sitesEnabled = '/etc/nginx/sites-enabled';
        
        if (!is_dir($sitesEnabled)) {
            return null;
        }
        
        // Leer todos los archivos de configuración
        $configs = glob($sitesEnabled . '/*');
        $roots = [];
        
        foreach ($configs as $config) {
            if (!is_file($config) || is_link($config)) {
                $config = readlink($config);
            }
            
            $content = @file_get_contents($config);
            if (!$content) continue;
            
            // Buscar directivas root
            if (preg_match_all('/root\s+([^;]+);/i', $content, $matches)) {
                foreach ($matches[1] as $root) {
                    $root = trim($root);
                    // Extraer directorio base común (ej: /var/www/proyecto -> /var/www)
                    // Subir 1 nivel para obtener el directorio padre
                    $parts = explode('/', trim($root, '/'));
                    if (count($parts) >= 2) {
                        // Tomar solo los primeros 2 niveles: /var/www
                        $baseDir = '/' . $parts[0] . '/' . $parts[1];
                        if ($baseDir && is_dir($baseDir)) {
                            $roots[$baseDir] = ($roots[$baseDir] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        
        // Retornar el más común
        if (!empty($roots)) {
            arsort($roots);
            return key($roots);
        }
        
        // Fallback a /var/www si existe
        return is_dir('/var/www') ? '/var/www' : null;
    }
    
    /**
     * Detecta document root de apache
     */
    private function detectApacheDocumentRoot(): ?string
    {
        $sitesEnabled = '/etc/apache2/sites-enabled';
        
        if (!is_dir($sitesEnabled)) {
            // Intentar con httpd.conf
            $sitesEnabled = '/etc/httpd/conf.d';
            if (!is_dir($sitesEnabled)) {
                return null;
            }
        }
        
        // Leer todos los archivos de configuración
        $configs = glob($sitesEnabled . '/*');
        $roots = [];
        
        foreach ($configs as $config) {
            if (!is_file($config) || is_link($config)) {
                $config = readlink($config);
            }
            
            $content = @file_get_contents($config);
            if (!$content) continue;
            
            // Buscar DocumentRoot
            if (preg_match_all('/DocumentRoot\s+["\']?([^"\'
]+)["\']?/i', $content, $matches)) {
                foreach ($matches[1] as $root) {
                    $root = trim($root);
                    // Extraer directorio base común
                    $parts = explode('/', trim($root, '/'));
                    if (count($parts) >= 2) {
                        // Tomar solo los primeros 2 niveles: /var/www o /home/user
                        $baseDir = '/' . $parts[0] . '/' . $parts[1];
                        if ($baseDir && is_dir($baseDir)) {
                            $roots[$baseDir] = ($roots[$baseDir] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        
        // Retornar el más común
        if (!empty($roots)) {
            arsort($roots);
            return key($roots);
        }
        
        // Fallback común en cPanel/GoDaddy
        $homeDir = getenv('HOME');
        if ($homeDir && is_dir($homeDir . '/public_html')) {
            return $homeDir . '/public_html';
        }
        
        return is_dir('/var/www') ? '/var/www' : null;
    }
    
    /**
     * Obtiene la IP externa del servidor
     * 
     * @return string|null
     */
    private function getExternalServerIp(): ?string
    {
        // Método 1: hostname -I (Linux) - más rápido
        exec('hostname -I 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $ips = explode(' ', trim($output[0]));
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIVATE_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Método 2: Variable de entorno SERVER_ADDR (solo si está disponible)
        if (isset($_SERVER['SERVER_ADDR'])) {
            $serverAddr = $_SERVER['SERVER_ADDR'];
            if ($serverAddr && $serverAddr !== '127.0.0.1' && $serverAddr !== '::1') {
                return $serverAddr;
            }
        }
        
        // Método 3: Hardcoded para GoDaddy (evitar timeout)
        if (strpos(gethostname(), 'host') !== false) {
            return '208.109.231.98'; // IP conocida de GoDaddy
        }
        
        return null;
    }
    
    /**
     * Instala configuración Apache
     * 
     * @param string $projectName
     * @param string $configContent
     * @return array ['success' => bool, 'message' => string]
     */
    public function installApacheConfig(string $projectName, string $configContent): array
    {
        $configPath = "/etc/apache2/sites-available/{$projectName}.conf";
        
        // Crear archivo
        $tempFile = tempnam(sys_get_temp_dir(), 'apache_');
        file_put_contents($tempFile, $configContent);
        
        // Copiar con sudo
        exec("sudo cp {$tempFile} {$configPath} 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Error al copiar configuración: ' . implode("\n", $output)
            ];
        }
        
        // Habilitar módulos necesarios
        exec('sudo a2enmod proxy proxy_http rewrite 2>&1', $output, $returnCode);
        
        // Activar sitio
        exec("sudo a2ensite {$projectName} 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Error al activar sitio: ' . implode("\n", $output)
            ];
        }
        
        // Verificar configuración
        exec('sudo apache2ctl configtest 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Revertir cambios
            exec("sudo a2dissite {$projectName}");
            exec("sudo rm {$configPath}");
            
            return [
                'success' => false,
                'message' => 'Configuración Apache inválida: ' . implode("\n", $output)
            ];
        }
        
        // Recargar Apache
        exec('sudo systemctl reload apache2 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Error al recargar Apache: ' . implode("\n", $output)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Configuración Apache instalada y activada correctamente'
        ];
    }
}

