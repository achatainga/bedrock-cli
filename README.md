# Bedrock CLI

Universal CLI tool for managing Roots Bedrock WordPress projects with **Profiles**, **Blueprints**, **Seeders**, and **Enterprise Store Migration**.

## 🚀 Features

### v2.4 - Autonomous Staging Clone & Auto-SSL Ingress
- **📦 1-Click Staging & Production Cloner**: Clonado directo e integral desde un WordPress tradicional (remoto vía SSH o local) a un stack aislado Roots Bedrock (`bedrock clone`).
- **🔒 Auto-SSL Ingress & TLS Termination**: Detección inteligente de certificados cPanel (`/var/cpanel/ssl/apache_tls`), Let's Encrypt o auto-firmados con fallback sudo, mapeo directo `${HTTP_PORT}:443` en Nginx y redirección transparente `error_page 497`.
- **🖼️ Transparent Media Proxy (Zero-Disk)**: Streaming bajo demanda de `/uploads/` desde el servidor origen sin descargar gigabytes de imágenes (`--proxy-uploads`).
- **🛡️ Sandbox WP-CLI & Sanitización SQL**: Ejecución de migraciones y search-replace aislados con `--skip-plugins --skip-themes --all-tables`, permisos duales `${HOST_UID}:www-data` (`2775`), soporte multi-origen ante `.env` y relajación en base de datos de bloqueos JWT (`simple_jwt_login_settings`) para APIs públicas.

### v2.2 - Enterprise Migration & Unified Management
- **🔄 SSH Streaming DB Migration**: One-command remote `mysqldump` with end-of-file integrity verification and automated serialized `search-replace` (`bedrock db:pull`).
- **🔗 Zero-Copy Plugin Linking**: Native NTFS Junctions on Windows (`mklink /J`) and POSIX symlinks (`bedrock plugins:link`) to work directly with shared repositories without duplicating files.
- **🐳 Production-Ready Docker Stack**: Isolated multi-container setup (Nginx, PHP-FPM 8.2, MySQL 8.0, Redis 7) with custom port assignment and zero-conflict networking.
- **🛡️ Hardened Configuration**: Automated reverse-proxy HTTPS detection, memory limits (512M), silent debug display, and `disable-jwt-cli.php` mu-plugin injection to prevent CLI lockouts.
- **🎛️ Interactive Management**: Manage plugins, themes, and dependencies with intuitive interactive menus.
- **⚡ Granular Commands**: Direct, scriptable commands for CI/CD automation and fast local execution.
- **🎨 Profiles**: Reusable project templates with predefined plugins, themes, and configurations.
- **🔍 WordPress.org Search**: Search and inspect plugins/themes directly from terminal (Chocolatey-style).
- **📋 Blueprints**: Environment-specific configurations (`production`, `staging`, `development`).
- **🌱 Seeders**: Hybrid Laravel/WordPress data seeders for rapid local dataset mocking.
- **🩺 Bedrock Doctor**: Comprehensive system health check, dependency diagnostics, and auto-repair (`bedrock doctor --fix`).

---

## 📦 Instalación

### Instalación Global (Recomendada)

Dado que `achatainga/bedrock-cli` es un paquete alojado en GitHub en desarrollo activo, **antes de requerirlo globalmente es indispensable registrar el repositorio VCS y su estabilidad en Composer**:

#### 1. Registrar el Repositorio VCS en Composer Global

```bash
# Vía HTTPS (Recomendado para la mayoría de entornos):
composer global config repositories.bedrock-cli vcs https://github.com/achatainga/bedrock-cli.git

# O vía SSH (si tu clave SSH está autorizada en GitHub):
composer global config repositories.bedrock-cli vcs git@github.com:achatainga/bedrock-cli.git
```

#### 2. Configurar la Estabilidad Mínima

```bash
composer global config minimum-stability dev
composer global config prefer-stable true
```

> **Nota (GitHub Tokens / Rate Limits):**
> Si trabajas con repositorios privados o para evitar límites de la API pública de GitHub, registra tu Personal Access Token:
> ```bash
> composer config --global --auth github-oauth.github.com <TU_GITHUB_TOKEN>
> ```

#### 3. Instalar `bedrock-cli` Globalmente

```bash
# Instalar la rama feature activa / recomendada:
composer global require achatainga/bedrock-cli:dev-feature/unified-management-system

# O la rama develop:
composer global require achatainga/bedrock-cli:dev-develop
```

#### 4. Configurar el Directorio de Binarios en el `PATH`

Asegúrate de que el directorio global de Composer esté incluido en el `PATH` del sistema para ejecutar el comando `bedrock` desde cualquier directorio:

- **Linux / macOS**:
  Añade a tu archivo `~/.bashrc`, `~/.zshrc` o perfil de shell:
  ```bash
  export PATH="$HOME/.config/composer/vendor/bin:$HOME/.composer/vendor/bin:$PATH"
  ```
  Aplica los cambios en tu sesión:
  ```bash
  source ~/.bashrc
  ```

- **Windows (PowerShell)**:
  El directorio por defecto es `%APPDATA%\Composer\vendor\bin` (ej. `C:\Users\<Usuario>\AppData\Roaming\Composer\vendor\bin`).
  Para agregarlo a las variables de entorno de usuario:
  ```powershell
  [Environment]::SetEnvironmentVariable("Path", $env:Path + ";$env:APPDATA\Composer\vendor\bin", "User")
  ```

#### 5. Verificar Instalación y Diagnóstico del Sistema

```bash
# Comprobar versión instalada
bedrock --version

# Ejecutar chequeo de salud del entorno
bedrock doctor
```

#### 🔄 Actualizaciones Futuras

Para actualizar la herramienta a la versión más reciente tras nuevos commits en el repositorio:

```bash
composer global update achatainga/bedrock-cli
```

---

### Instalación Local (Por Proyecto)

```bash
composer require --dev achatainga/bedrock-cli
```

---

## Quick Start

### 1. Create a Profile
```bash
# Interactive wizard with plugin search
bedrock profile:create my-profile

# List all profiles
bedrock profile:list

# View profile details
bedrock profile:show my-profile
```

### 2. Create Project from Profile
```bash
# Create new project with profile
bedrock new my-site --profile=my-profile
```

### 3. Initialize Environment
```bash
cd my-site

# Initialize production environment
bedrock init --env=production

# Initialize staging with fake data
bedrock init --env=staging

# Initialize development environment
bedrock init --env=development
```

### 4. Clone Existing WordPress Site (1-Click Staging / Snapshot)
```bash
# Clone remote WordPress into an isolated Bedrock stack with native SSL & media proxy:
bedrock clone my-staging \
  --remote=alias-ssh \
  --domain=staging.misitio.com:9010 \
  --proxy-uploads=https://misitio.com \
  --ssl
```

---

## 🔄 Enterprise Store Migration Guide (Step-by-Step)

Guía completa y probada para migrar una tienda WooCommerce enterprise en producción (ej. cPanel, GoDaddy, VPS) a una arquitectura contenerizada Roots Bedrock en Docker bajo Windows 11 / WSL2 / Linux.

### 📋 Requisitos Previos

- **PHP 8.2+** y **Composer 2.x** instalados en el host.
- **Docker Desktop** (con backend WSL2 habilitado en Windows).
- **Acceso SSH** al servidor de producción (configurado en `~/.ssh/config` o accesible con clave SSH).
- **Git** configurado.

---

### Paso 0: Optimización de Recursos del Host (Windows 11 / WSL2)

Para prevenir congelamientos del sistema anfitrión (*memory thrashing*) en equipos con 12–16 GB de RAM causados por la retención del Linux Page Cache en WSL2 y MySQL:

1. Crea o edita el archivo `%USERPROFILE%\.wslconfig`:
   ```ini
   [wsl2]
   memory=5GB
   swap=2GB
   autoMemoryReclaim=dropcache
   processors=4
   ```
2. Aplica los cambios reiniciando WSL desde PowerShell:
   ```powershell
   wsl --shutdown
   ```
3. Verifica la salud del entorno:
   ```bash
   bedrock doctor
   ```

---

### Paso 1: Crear el Proyecto Bedrock con Stack Docker

Crea el nuevo proyecto especificando puertos personalizados para evitar colisiones con servicios locales existentes (como MySQL 3306 o Apache 80):

```bash
bedrock new mi-tienda-bedrock \
  --http-port=8080 \
  --mysql-port=3307 \
  --redis-port=6380
```

> **¿Qué automatiza este comando?**
> - Descarga e instala Roots Bedrock con Composer.
> - Configura `.env` con variables seguras y sales de cifrado de WordPress.
> - Genera la infraestructura Docker multi-contenedor (`web` PHP-FPM 8.2, `nginx`, `mysql` 8.0 y `redis` 7).
> - Inyecta la configuración endurecida en `config/application.php` (detección de HTTPS reverso, límites de memoria a 512M, supresión de warnings en pantalla).
> - Instala el mu-plugin `disable-jwt-cli.php` para que comandos de WP-CLI nunca sean interceptados por plugins JWT.

Entra al directorio del proyecto:
```bash
cd mi-tienda-bedrock
```

---

### Paso 2: Vincular Plugins Locales y Propietarios (Zero Copy)

Si tienes repositorios locales con los plugins de la tienda (por ejemplo, plugins propietarios en `C:/code/dt24`), vincúlalos directamente sin duplicar archivos ni consumir espacio en disco:

```bash
bedrock plugins:link C:/code/dt24
```

> **Ventajas de `plugins:link`:**
> - En **Windows**, crea *NTFS Junctions* (`mklink /J`) que no requieren permisos de Administrador ni Developer Mode.
> - En **Linux/macOS**, genera symlinks relativos estándar.
> - Cualquier cambio en el código fuente de los plugins se refleja instantáneamente en el entorno Bedrock y dentro del contenedor Docker sin sincronizaciones ni retrasos.

---

### Paso 3: Levantar los Contenedores Docker

Inicia los servicios de Docker (Nginx, PHP-FPM, MySQL y Redis):

```bash
bedrock docker --up
```

*(O alternativamente: `docker compose up -d`)*

Verifica que todos los contenedores estén en estado saludable (`Up`):
```bash
bedrock docker --status
```

---

### Paso 4: Descarga y Migración Automatizada de la Base de Datos

Descarga la base de datos de producción mediante streaming SSH, verifica su integridad de fin de archivo, impórtala en el contenedor MySQL y ejecuta la sustitución de URLs (`search-replace`) en una sola orden:

```bash
bedrock db:pull \
  --remote=alias-ssh-servidor \
  --db=nombre_bd_remota \
  --user=usuario_bd_remoto \
  --source-url="https://mitienda.com" \
  --target-url="http://localhost:8080"
```

> **Garantías de Seguridad de `db:pull`:**
> 1. **Streaming directo SSH:** Ejecuta `mysqldump` con `--single-transaction --quick --default-character-set=utf8mb4` sin saturar la RAM del servidor remoto ni almacenar volcados temporales en su disco.
> 2. **Verificación de integridad:** Lee el final del archivo SQL para verificar la presencia de la cabecera `-- Dump completed on ...`. Si la conexión SSH se interrumpió a la mitad, detiene el proceso antes de importar un archivo corrupto.
> 3. **Importación a MySQL Docker:** Carga el volcado respetando `lower_case_table_names=0`.
> 4. **Búsqueda y Reemplazo Serializado:** Ejecuta `wp search-replace` mediante WP-CLI para migrar URLs en opciones y tablas de metadatos respetando la serialización de PHP.

*(Si ya dispones de un volcado SQL local previo, usa `--file=mi-dump.sql` para omitir la descarga SSH y realizar directamente la verificación, importación y reemplazo).*

---

### Paso 5: Streaming Transparente de Imágenes de Producción (Proxy Nginx)

Las tiendas enterprise suelen acumular decenas de gigabytes de imágenes en `/uploads/`. Para trabajar de inmediato sin descargar 20+ GB de archivos:

Edita `docker/nginx/default.conf` y añade las directivas de proxy para los uploads justo antes del bloque PHP:

```nginx
    # Proxy transparente de imágenes faltantes hacia el servidor de producción
    location ~* ^/(app|wp-content)/uploads/(.*)$ {
        try_files $uri @production_uploads;
    }

    location @production_uploads {
        resolver 8.8.8.8 1.1.1.1 valid=300s ipv6=off;
        proxy_pass https://mitienda.com;
        proxy_set_header Host mitienda.com;
        proxy_ssl_server_name on;
        proxy_ssl_name mitienda.com;
        proxy_ssl_protocols TLSv1.2 TLSv1.3;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto https;
        proxy_hide_header Content-Disposition;
        proxy_intercept_errors on;
        recursive_error_pages on;
        error_page 404 = /wp/wp-includes/images/media/default.png;
        proxy_cache_valid 200 30d;
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
```

Aplica la configuración recargando Nginx:
```bash
docker compose exec nginx nginx -s reload
```

Cualquier producto, banner o logo cuya imagen no esté localmente se descargará y renderizará en tiempo real desde producción.

---

### Paso 6: Optimización de Rendimiento (OPcache & Redis Object Cache)

Para alcanzar tiempos de respuesta inferiores a un segundo en WooCommerce:

1. **Activar Redis Object Cache:**
   ```bash
   docker compose exec web wp plugin install redis-cache --activate
   docker compose exec web wp redis enable
   ```
2. **Asegurar OPcache en Memoria:**
   Verifica que `docker/php/conf.d/opcache.ini` tenga habilitado:
   ```ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.interned_strings_buffer=16
   opcache.max_accelerated_files=20000
   opcache.validate_timestamps=1
   ```
3. **Workers PHP-FPM Estáticos:**
   En `docker/php-fpm.d/www.conf`, utiliza trabajadores estáticos para evitar picos de consumo de CPU:
   ```ini
   pm = static
   pm.max_children = 2
   ```

---

### Paso 7: Verificación y Pruebas de Paridad

Ejecuta comprobaciones para certificar que el entorno responde con paridad total:

```bash
# Validar instalación del núcleo
docker compose exec web wp core is-installed

# Listar estado de plugins
docker compose exec web wp plugin list

# Comprobar la respuesta de la API REST
curl -I http://localhost:8080/wp-json/
```

Accede desde tu navegador a `http://localhost:8080` (sitio público) y `http://localhost:8080/wp/wp-admin` (panel de administración).

---

## 📚 Documentation

- **[Profiles Guide](docs/PROFILES.md)** - Complete profile system documentation
- **[Blueprints Guide](docs/BLUEPRINTS.md)** - Environment-specific configurations
- **[Seeders Guide](docs/SEEDERS.md)** - Data seeding system
- **[Unified Management](docs/UNIFIED_MANAGEMENT.md)** - Interactive management workflows

---

## Commands Reference

### 🎛️ Unified Management (v2.0)

```bash
# Menús interactivos
bedrock manage                      # Menú principal de gestión
bedrock manage:plugins              # Gestión interactiva de plugins
bedrock manage:themes               # Gestión interactiva de temas
bedrock manage:dependencies         # Gestión interactiva de dependencias

# Comandos directos para scripts y CI/CD
bedrock add:plugin <slug> [--version=VERSION] [--activate]
bedrock add:theme <slug> [--version=VERSION] [--activate]
bedrock add:dependency <vendor/package> [--version=VERSION] [--dev]

bedrock remove:plugin <slug>
bedrock remove:theme <slug>
bedrock remove:dependency <vendor/package>
```

### Profile Management

```bash
bedrock profile:create <name>      # Crear perfil (asistente interactivo)
bedrock profile:list                # Listar perfiles disponibles
bedrock profile:show <name>         # Mostrar detalles del perfil
bedrock profile:edit <name>         # Editar perfil en editor del sistema
bedrock profile:delete <name>       # Eliminar perfil
bedrock profile:export <name>       # Exportar perfil desde el proyecto actual
bedrock profile:apply <name>        # Aplicar perfil a proyecto existente
bedrock profile:menu                # Menú interactivo de perfiles

# Edición granular de perfiles
bedrock profile:add-plugin <profile> <slug> [--version=VERSION]
bedrock profile:remove-plugin <profile> <slug>
bedrock profile:set-theme <profile> <slug> [--version=VERSION]
bedrock profile:add-repo <profile> --type=<type> --url=<url>
```

### WordPress.org Search

```bash
bedrock plugin:search <query> [--profile=NAME]   # Buscar plugins en el directorio oficial
bedrock plugin:info <slug>                       # Información y metadatos de un plugin
bedrock theme:search <query> [--profile=NAME]    # Buscar temas en el directorio oficial
bedrock theme:info <slug>                        # Información y metadatos de un tema
```

### Project Creation

```bash
bedrock new <name> [OPTIONS]

Opciones:
  --profile=NAME       Perfil a utilizar (default: "default")
  --no-acorn           No instalar Roots Acorn (instalado por defecto)
  --no-docker          No generar archivos Docker (generado por defecto)
  --no-redis           No configurar Redis (configurado por defecto)
  --db-name=NAME       Nombre de la base de datos local
  --db-user=USER       Usuario de BD (default: "root")
  --db-pass=PASS       Contraseña de BD (default: "mysql")
  --http-port=PORT     Puerto HTTP para Nginx (ej: 8080)
  --mysql-port=PORT    Puerto MySQL en el host (ej: 3307)
  --redis-port=PORT    Puerto Redis en el host (ej: 6380)
  --force              Sobrescribir si el directorio ya existe
  --verify             Verificar y auto-reparar el proyecto post-creación
```

### Environment Initialization

```bash
bedrock init [OPTIONS]

Opciones:
  --env=ENV            Entorno: production|staging|development
  --db-file=FILE       Archivo SQL a importar
  --skip-db            Omitir importación de base de datos
  --skip-seeders       Omitir ejecución de seeders
  --skip-plugins       Omitir activación de plugins
  --old-url=URL        URL original para search-replace
  --new-url=URL        Nueva URL (auto-detectada desde .env)
```

### 📦 Snapshot & Staging Clone (v2.4)

Clona una instancia completa de WordPress tradicional (local o remota vía SSH) hacia un entorno aislado contenerizado Roots Bedrock en un solo comando:

```bash
bedrock clone <name> [OPTIONS]
# Alias equivalente:
bedrock snapshot:clone <name> [OPTIONS]
```

#### Ejemplos de Uso:

```bash
# 1. Clonar desde servidor remoto SSH con SSL nativo y proxy de imágenes:
bedrock clone mi-staging \
  --remote=dt24-godaddy \
  --remote-path=/home/usuario/public_html \
  --domain=staging.midominio.com:9010 \
  --proxy-uploads=https://midominio.com \
  --ssl

# 2. Clonar desde una instalación local en el mismo host:
bedrock clone mi-clon-local \
  --source=/var/www/wordpress \
  --http-port=8080 \
  --mysql-port=3307 \
  --proxy-uploads=https://midominio.com

# 3. Forzar sobreescritura y desmantelamiento de contenedores previos:
bedrock clone mi-staging --remote=dt24-godaddy --force --ssl
```

#### Opciones de `bedrock clone`:
```bash
Argumentos:
  name                 Nombre del nuevo proyecto Bedrock y prefijo de contenedores

Opciones:
  --source=PATH        Ruta local al WordPress original (ej. /home/user/public_html)
  --remote=HOST        Alias SSH del servidor remoto (ej. dt24-godaddy) si el origen es remoto
  --remote-path=PATH   Ruta en el host remoto (default: "/home/qqgi77wff00i/public_html")
  --domain=DOMAIN      Dominio y puerto destino (ej. staging.detodo24.com:9010 o localhost)
  --http-port=PORT     Puerto HTTP / SSL forzado para Nginx (auto-detecta puerto libre si se omite)
  --mysql-port=PORT    Puerto MySQL forzado en el host (auto-detecta libre si se omite)
  --redis-port=PORT    Puerto Redis forzado en el host (auto-detecta libre si se omite)
  --db-name=NAME       Nombre de base de datos MySQL destino
  --db-user=USER       Usuario MySQL contenedor (default: "root")
  --db-pass=PASS       Contraseña MySQL contenedor (default: "mysql")
  --proxy-uploads=URL  URL de producción para proxy transparente Nginx de medios (ej. https://detodo24.com)
  --ssl                Habilitar terminación SSL/HTTPS nativa en el contenedor Nginx
  --ssl-cert=PATH      Ruta personalizada al certificado SSL / bundle
  --ssl-key=PATH       Ruta personalizada a la clave privada SSL
  --auto-ingress       Configurar automáticamente proxy reverso en el Nginx del host (cPanel)
  --skip-db            Omitir volcado e importación de base de datos
  --skip-assets        Omitir copia de temas y plugins
  --force              Desmantelar contenedores previos y sobrescribir directorio
  --dry-run            Modo simulación sin escribir cambios
```

### Database Management

```bash
bedrock db:pull [OPTIONS]          # Descarga SSH + integridad + importación + search-replace
bedrock migrate                    # Migrar e importar volcado SQL local
bedrock snapshot --create          # Crear snapshot rápido de base de datos
bedrock snapshot --restore         # Restaurar snapshot de base de datos
bedrock db:clean                   # Limpiar y normalizar prefijos de tablas
bedrock import:core                # Importar golden-image.sql
```

#### Opciones de `bedrock db:pull`:
```bash
  --remote=HOST        Alias SSH del servidor remoto (default: "dt24-godaddy")
  --db=NAME            Nombre de la base de datos remota
  --user=USER          Usuario MySQL remoto
  --password=PASS      Contraseña MySQL remota (o variable DB_REMOTE_PASSWORD en .env)
  --file=FILE          Archivo SQL local existente (omite descarga SSH)
  --source-url=URL     URL de producción a reemplazar (default: "https://detodo24.com")
  --target-url=URL     URL local destino (default: "http://localhost:8080")
  --skip-replace       Omitir búsqueda y reemplazo de URLs
  --dry-run            Comprobar conectividad SSH sin descargar ni importar
```

### Plugins & Linking

```bash
bedrock plugins:link [SOURCE]      # Vincular plugins locales vía NTFS Junctions / symlinks
bedrock plugins:order              # Aplicar orden de activación guardado
bedrock plugins:order:builder      # Asistente interactivo para orden de plugins
bedrock plugins:order:menu         # Administrar órdenes guardados
```

#### Opciones de `bedrock plugins:link`:
```bash
  SOURCE               Ruta al directorio de plugins (ej: C:/code/dt24 o ../dt24)
  --target=PATH        Ruta de destino en Bedrock (default: "web/app/plugins")
  --dry-run            Simular la creación de enlaces sin modificar el sistema
```

### Configuration

```bash
bedrock export:config [--all]      # Exportar opciones de WordPress a JSON
bedrock options:pull [--all]       # Extraer opciones hacia config/options/
bedrock options:push               # Importar opciones desde config/options/
```

### System & Containers

```bash
bedrock docker [OPTIONS]           # Gestión del ciclo de vida Docker
bedrock doctor [--fix]             # Diagnóstico integral del sistema y auto-reparación
```

#### Opciones de `bedrock docker`:
```bash
  --up                 Levantar los contenedores del proyecto
  --down               Detener y bajar contenedores
  --restart            Reiniciar contenedores
  --status             Comprobar estado de los servicios
  --build              Reconstruir imágenes al levantar
```

### 🔐 Authentication & Private Repositories

Gestión de credenciales Composer en `auth.json` para repositorios privados de GitHub, GitLab y Bitbucket:

```bash
bedrock auth:add                   # Asistente interactivo para registrar tokens OAuth / HTTP Basic
bedrock auth:list                  # Listar credenciales configuradas en el entorno
bedrock auth:remove                # Eliminar credenciales registradas
bedrock auth:menu                  # Menú interactivo de autenticación
```

---

## Project Structure

```
my-site/
├── .bedrock/
│   └── profile.json              # Configuración y metadatos del perfil
├── blueprints/
│   ├── production.json           # Blueprint de producción
│   ├── staging.json              # Blueprint de staging
│   └── development.json          # Blueprint de desarrollo
├── config/
│   ├── application.php           # Configuración principal endurecida (salts, redis, reverse-proxy)
│   ├── environments/             # Overrides por entorno (development.php, staging.php, etc.)
│   └── plugins/                  # Configuraciones declarativas de plugins
├── database/
│   ├── migrations/               # Migraciones de base de datos
│   ├── seeders/                  # Seeders de datos (Core, WooCommerce, Products)
│   └── snapshots/                # Snapshots locales SQL
├── docker/
│   ├── nginx/
│   │   └── default.conf          # Configuración Nginx (FastCGI, REST API, uploads proxy)
│   └── mysql/
│       ├── client.cnf            # Codificación utf8mb4 del cliente
│       └── my.cnf                # Optimización de memoria MySQL y buffer pool
├── scripts/                      # Scripts de sanitización y limpieza
├── web/
│   ├── app/
│   │   ├── mu-plugins/           # Must-use plugins (disable-jwt-cli.php, acorn-boot.php)
│   │   ├── plugins/              # Plugins instalados / junctions vinculados
│   │   ├── themes/               # Temas de WordPress
│   │   └── uploads/              # Archivos multimedia locales
│   ├── wp/                       # Núcleo de WordPress (gestionado por Composer)
│   ├── index.php                 # Punto de entrada de Bedrock
│   └── wp-config.php             # Bootstrap de configuración
├── docker-compose.yml            # Orquestación de contenedores (web, nginx, mysql, redis)
├── Dockerfile.web                # Definición de contenedor PHP-FPM con extensiones optimizadas
├── composer.json                 # Dependencias del proyecto
└── .env                          # Variables de entorno y secretos
```

---

## Profile Storage

Los perfiles se almacenan de manera global en:
- **Linux/Mac**: `~/.bedrock-cli/profiles/`
- **Windows**: `%USERPROFILE%\.bedrock-cli\profiles\`

Cada proyecto también almacena su propio perfil en `.bedrock/profile.json` para portabilidad entre equipos de desarrollo.

---

## Requirements

- **PHP**: 8.1+ (8.2+ recomendado)
- **Composer**: 2.2+
- **Docker & Docker Compose**
- **WP-CLI** (opcional en host, preinstalado en contenedor web)
- **Git**
- **SSH Client** (para `db:pull`)

---

## License

MIT License.

## Contributing

¡Las contribuciones son bienvenidas! Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para más detalles.

## Credits

Desarrollado con ❤️ por [Alejandro Chataing](https://github.com/achatainga).
