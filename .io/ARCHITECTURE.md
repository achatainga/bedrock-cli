# ARQUITECTURA COMPLETA: BEDROCK-CLI

**Fecha de Generación**: 2025-01-30  
**Última Actualización**: 2025-01-30  
**Versión del Proyecto**: 1.0.0  
**Total de Archivos Analizados**: 7 (Fase 1 completada)

---

## 📋 ÍNDICE NAVEGABLE

1. [Visión General](#1-visión-general)
2. [Arquitectura de Alto Nivel](#2-arquitectura-de-alto-nivel)
3. [Comandos Core](#3-comandos-core)
4. [Comandos de Menús](#4-comandos-de-menús)
5. [Comandos Docker y Database](#5-comandos-docker-y-database)
6. [Comandos Plugins](#6-comandos-plugins)
7. [Comandos Themes y Options](#7-comandos-themes-y-options)
8. [Comandos Utilidades](#8-comandos-utilidades)
9. [Servicios](#9-servicios)
10. [Stubs y Plantillas](#10-stubs-y-plantillas)
11. [Flujos de Trabajo](#11-flujos-de-trabajo)
12. [Casos de Uso](#12-casos-de-uso)
13. [Diagramas](#13-diagramas)
14. [Dependencias](#14-dependencias)
15. [Problemas Conocidos](#15-problemas-conocidos)
16. [Roadmap](#16-roadmap)

---

## 1. VISIÓN GENERAL

### 1.1. ¿Qué es Bedrock CLI?

**Bedrock CLI** es una herramienta de línea de comandos universal para gestionar proyectos WordPress basados en Roots Bedrock. Automatiza tareas comunes de desarrollo, despliegue y mantenimiento, proporcionando una interfaz consistente para operaciones que normalmente requerirían múltiples comandos manuales.

**Características principales:**
- ✅ Creación de proyectos Bedrock con configuración Docker automática
- ✅ Migración de bases de datos existentes con limpieza y transformación
- ✅ Gestión completa de plugins y temas (activación, compresión, ordenamiento)
- ✅ Sistema de menús interactivos para operaciones comunes
- ✅ Snapshots de base de datos para backup/restore rápido
- ✅ Integración con Roots Acorn (Laravel components)
- ✅ Mecanismos de seguridad para operaciones destructivas

### 1.2. Propósito y Alcance

**Problema que resuelve:**
WordPress tradicional tiene una estructura monolítica donde el core, plugins, temas y contenido están mezclados. Bedrock moderniza esto separando el código de la aplicación, pero requiere conocimiento de Composer, Docker, WP-CLI y configuraciones complejas.

**Solución:**
Bedrock CLI abstrae toda esta complejidad en comandos simples:
- `bedrock new my-site --with-docker` → Proyecto completo en segundos
- `bedrock migrate --sql-file=dump.sql` → Migración automatizada
- `bedrock plugins:order` → Gestión de orden de activación

**Alcance:**
- **Desarrollo local**: Configuración de entornos Docker
- **Migración**: Importación de sitios existentes
- **Gestión**: Plugins, temas, opciones, base de datos
- **Seguridad**: Confirmaciones con código aleatorio para operaciones destructivas
- **Automatización**: Scripts y flujos de trabajo repetibles

**Fuera de alcance:**
- Hosting/despliegue en producción (usar Trellis)
- Edición de código de plugins/temas
- Gestión de contenido (usar WP Admin)

### 1.3. Tecnologías Principales

| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **PHP** | ≥8.0 | Lenguaje base |
| **Symfony Console** | ^6.0\|^7.0\|^8.0 | Framework CLI |
| **Symfony Process** | ^6.0\|^7.0\|^8.0 | Ejecución de procesos externos |
| **Symfony Filesystem** | ^6.0\|^7.0\|^8.0 | Operaciones de archivos |
| **Symfony YAML** | ^6.0\|^7.0\|^8.0 | Parsing de configuraciones |
| **Composer** | - | Gestor de dependencias |
| **Docker** | - | Contenedores (opcional) |
| **WP-CLI** | - | Gestión de WordPress |
| **Git** | - | Control de versiones |

**Dependencias de Composer:**
```json
{
  "require": {
    "php": ">=8.0",
    "symfony/console": "^6.0|^7.0|^8.0",
    "symfony/process": "^6.0|^7.0|^8.0",
    "symfony/filesystem": "^6.0|^7.0|^8.0",
    "symfony/yaml": "^6.0|^7.0|^8.0"
  }
}
```

**Autoloading PSR-4:**
```json
{
  "autoload": {
    "psr-4": {
      "Roots\\BedrockCli\\": "src/"
    }
  }
}
```

### 1.4. Modos de Instalación

#### Instalación Global (Recomendada)
```bash
composer global require achatainga/bedrock-cli:dev-develop
```
- Comando disponible globalmente: `bedrock`
- Requiere `~/.composer/vendor/bin` en PATH
- Ideal para desarrolladores que trabajan en múltiples proyectos

#### Instalación Local (Por Proyecto)
```bash
composer require --dev achatainga/bedrock-cli
```
- Comando disponible: `vendor/bin/bedrock`
- Aislado por proyecto
- Ideal para equipos con versiones específicas

#### Instalación desde Repositorio
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/achatainga/bedrock-cli.git"
    }
  ],
  "require-dev": {
    "roots/bedrock-cli": "dev-develop"
  }
}
```

---

## 2. ARQUITECTURA DE ALTO NIVEL

### 2.1. Diagrama de Componentes

```
┌─────────────────────────────────────────────────────────────┐
│                      BEDROCK CLI                            │
│                    (bin/bedrock)                            │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              Roots\BedrockCli\Application                   │
│              (Symfony Console Application)                  │
│                                                             │
│  - Registra 38 comandos                                    │
│  - Maneja routing de comandos                              │
│  - Proporciona help y autocompletado                       │
└────────────────────────┬────────────────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         ▼               ▼               ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  Commands   │  │  Services   │  │   Stubs     │
│             │  │             │  │             │
│ 38 clases   │  │  6 clases   │  │ 14 archivos │
│ Command.php │  │ Service.php │  │ .stub       │
└──────┬──────┘  └──────┬──────┘  └─────────────┘
       │                │
       │ usa            │ usa
       ▼                ▼
┌─────────────────────────────────┐
│     Herramientas Externas       │
│                                 │
│  - Docker / docker-compose      │
│  - WP-CLI                       │
│  - Composer                     │
│  - Git                          │
│  - MySQL                        │
└─────────────────────────────────┘
```

### 2.2. Patrón de Diseño

**Patrón Principal: Command Pattern (Symfony Console)**

Cada comando es una clase independiente que extiende `Symfony\Component\Console\Command\Command`:

```php
namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('example')
             ->setDescription('Descripción del comando')
             ->addOption('option', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Lógica del comando
        return Command::SUCCESS;
    }
}
```

**Patrones Secundarios:**

1. **Service Layer**: Lógica reutilizable en `Services/`
   - `DockerService`: Wrapper de docker-compose
   - `WpCliService`: Wrapper de WP-CLI
   - `SecurityService`: Confirmaciones de seguridad

2. **Template Method**: Stubs con variables reemplazables
   - `{{PROJECT_NAME}}` → Nombre del proyecto
   - `{{DB_NAME}}` → Nombre de base de datos
   - `{{HTTP_PORT}}` → Puerto HTTP

3. **Facade**: `Application.php` simplifica acceso a Symfony Console

### 2.3. Flujo de Datos

```
Usuario ejecuta comando
    ↓
bin/bedrock (entry point)
    ↓
Autoloader de Composer
    ↓
Roots\BedrockCli\Application::__construct()
    ├─ Registra 38 comandos
    └─ Configura Symfony Console
    ↓
Application::run()
    ├─ Parsea argumentos CLI
    ├─ Identifica comando solicitado
    └─ Ejecuta Command::execute()
        ↓
        ├─ Valida inputs (InputInterface)
        ├─ Llama a Services si es necesario
        │   ├─ DockerService::isRunning()
        │   ├─ WpCliService::run('plugin list')
        │   └─ SecurityService::confirmDangerousAction()
        ├─ Ejecuta lógica del comando
        │   ├─ Symfony\Process para comandos externos
        │   ├─ Symfony\Filesystem para archivos
        │   └─ Symfony\Yaml para configs
        ├─ Genera output (OutputInterface)
        │   ├─ SymfonyStyle para formato
        │   ├─ ProgressBar para progreso
        │   └─ Table para tablas
        └─ Retorna exit code
            ├─ Command::SUCCESS (0)
            ├─ Command::FAILURE (1)
            └─ Command::INVALID (2)
```

### 2.4. Estructura de Directorios

```
bedrock-cli/
├── bin/
│   └── bedrock                    # Entry point (ejecutable)
├── src/
│   ├── Application.php            # App principal Symfony Console
│   ├── Commands/                  # 38 comandos
│   │   ├── NewCommand.php         # Crear proyecto
│   │   ├── MigrateCommand.php     # Migrar DB
│   │   ├── MainMenuCommand.php    # Menú principal
│   │   ├── AcornCommand.php       # Menú Acorn
│   │   ├── DockerCommand.php      # Gestión Docker
│   │   ├── DatabaseCommand.php    # Gestión DB
│   │   ├── Plugins*.php           # 10 comandos plugins
│   │   ├── Themes*.php            # 5 comandos themes
│   │   ├── Options*.php           # 5 comandos options
│   │   └── ...                    # 17 comandos más
│   └── Services/                  # 6 servicios
│       ├── DockerService.php      # Wrapper docker-compose
│       ├── WpCliService.php       # Wrapper WP-CLI
│       ├── SecurityService.php    # Confirmaciones
│       ├── ZipService.php         # Comprimir archivos
│       ├── UnzipService.php       # Descomprimir archivos
│       └── ProgressService.php    # Barras de progreso
├── stubs/                         # 14 plantillas
│   ├── .env.stub                  # Variables de entorno
│   ├── docker-compose.yml.stub    # Configuración Docker
│   ├── Dockerfile.web.stub        # Imagen PHP-FPM
│   ├── config/                    # Configs Bedrock
│   ├── docker/                    # Configs Docker
│   ├── mu-plugins/                # Must-use plugins
│   └── scripts/                   # Scripts auxiliares
├── vendor/                        # Dependencias Composer
├── .gitignore
├── composer.json                  # Definición del paquete
├── composer.lock
├── README.md                      # Documentación usuario
├── DEVELOPMENT.md                 # Guía desarrollo
├── MIGRATION_GUIDE.md             # Guía migración
└── SECURITY.md                    # Mecanismos seguridad
```

### 2.5. Ciclo de Vida de un Comando

```
1. CONFIGURACIÓN (configure())
   ├─ Definir nombre: setName('comando')
   ├─ Definir descripción: setDescription('...')
   ├─ Definir argumentos: addArgument('name', ...)
   └─ Definir opciones: addOption('--flag', ...)

2. INICIALIZACIÓN (initialize())
   ├─ Validar entorno (Docker, WP-CLI)
   ├─ Cargar configuraciones (.env)
   └─ Preparar servicios

3. INTERACCIÓN (interact())
   ├─ Solicitar inputs faltantes
   ├─ Mostrar menús interactivos
   └─ Confirmar acciones peligrosas

4. EJECUCIÓN (execute())
   ├─ Validar inputs
   ├─ Ejecutar lógica principal
   │   ├─ Llamar servicios
   │   ├─ Ejecutar procesos externos
   │   └─ Manipular archivos
   ├─ Mostrar progreso
   └─ Retornar exit code

5. FINALIZACIÓN
   ├─ Limpiar recursos temporales
   ├─ Mostrar resumen
   └─ Sugerir próximos pasos
```

### 2.6. Convenciones de Código

**Namespaces:**
- Comandos: `Roots\BedrockCli\Commands\`
- Servicios: `Roots\BedrockCli\Services\`

**Nombres de Clases:**
- Comandos: `{Nombre}Command.php` (PascalCase)
- Servicios: `{Nombre}Service.php` (PascalCase)

**Nombres de Comandos CLI:**
- Formato: `grupo:accion` (kebab-case)
- Ejemplos: `plugins:list`, `themes:activate`, `db:clean`

**Exit Codes:**
- `0` (SUCCESS): Comando ejecutado correctamente
- `1` (FAILURE): Error durante ejecución
- `2` (INVALID): Argumentos inválidos

**Estilos de Output:**
- `<info>`: Información general (verde)
- `<comment>`: Comentarios (amarillo)
- `<question>`: Preguntas (cyan)
- `<error>`: Errores (rojo)

---


## 3. COMANDOS CORE

### 3.1. NewCommand - Creación de Proyectos

**Archivo**: `src/Commands/NewCommand.php`  
**Líneas**: 1-400  
**Propósito**: Crear nuevos proyectos Bedrock con configuración completa (Docker, Acorn, Redis)

#### Firma del Comando
```bash
bedrock new <name> [opciones]
```

#### Opciones
| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `name` | Argumento | - | Nombre del proyecto (requerido) |
| `--with-docker` | Flag | false | Generar archivos Docker |
| `--no-acorn` | Flag | false | NO instalar Roots Acorn |
| `--no-redis` | Flag | false | NO instalar Redis |
| `--db-name` | String | {name} | Nombre de BD |
| `--db-user` | String | root | Usuario de BD |
| `--db-pass` | String | mysql | Contraseña de BD |
| `--http-port` | Int | 80 (auto) | Puerto HTTP |
| `--mysql-port` | Int | 3306 (auto) | Puerto MySQL |
| `--redis-port` | Int | 6379 (auto) | Puerto Redis |
| `--force` | Flag | false | Sobrescribir si existe |

#### Flujo de Ejecución
```
1. Validar nombre del proyecto
   ├─ Verificar si directorio existe
   └─ Requerir --force si existe

2. Crear proyecto Bedrock base
   └─ composer create-project roots/bedrock {name}

3. Instalar Acorn (si --no-acorn NO está presente)
   ├─ composer config allow-plugins
   ├─ composer require roots/acorn
   └─ Copiar acorn-boot.php a mu-plugins/

4. Instalar Redis (si --no-redis NO está presente)
   └─ composer require rhubarbgroup/redis-cache

5. Configurar Docker (si --with-docker)
   ├─ Detectar puertos libres
   ├─ Copiar docker-compose.yml.stub
   ├─ Copiar Dockerfile.web.stub
   ├─ Copiar configs nginx/
   └─ Copiar configs mysql/

6. Crear estructura extendida
   ├─ database/migrations/
   ├─ database/seeders/
   ├─ database/snapshots/
   ├─ config/plugins/
   ├─ scripts/
   ├─ scripts/sanitize-db.sh
   └─ scripts/clean-database.php

7. Generar archivo .env
   ├─ Generar 8 salts aleatorios
   ├─ Generar APP_KEY para Acorn
   └─ Configurar credenciales BD

8. Copiar application.php con guards
   ├─ config/application.php
   ├─ config/environments/development.php
   └─ config/environments/staging.php

9. Agregar bedrock-cli a composer.json
   ├─ Agregar repositorio VCS
   └─ composer require achatainga/bedrock-cli

10. Inicializar Git
    ├─ git init
    ├─ git add .
    └─ git commit -m "Initial commit"
```

#### Métodos Principales
| Método | Descripción |
|--------|-------------|
| `configure()` | Define nombre, descripción y opciones |
| `execute()` | Orquesta el flujo completo |
| `createBedrockProject()` | Ejecuta composer create-project |
| `installAcorn()` | Instala y configura Acorn |
| `installRedis()` | Instala Redis Object Cache |
| `setupDocker()` | Genera archivos Docker |
| `createExtendedStructure()` | Crea directorios adicionales |
| `generateEnvFile()` | Genera .env con salts |
| `copyApplicationConfig()` | Copia configs con guards |
| `addBedrockCliToComposer()` | Agrega bedrock-cli al proyecto |
| `initGit()` | Inicializa repositorio Git |
| `findFreePort()` | Detecta puerto libre |
| `generateKey()` | Genera salt aleatorio (64 chars) |
| `copyStub()` | Copia stub reemplazando variables |

#### Dependencias
- `Symfony\Component\Console\Command\Command`
- `Symfony\Component\Process\Process`
- Composer (externo)
- Git (externo)

#### Ejemplo de Uso
```bash
# Proyecto básico
bedrock new mi-sitio

# Proyecto completo con Docker y Acorn
bedrock new mi-sitio --with-docker

# Proyecto sin Acorn
bedrock new mi-sitio --with-docker --no-acorn

# Proyecto con puertos personalizados
bedrock new mi-sitio --with-docker --http-port=8080 --mysql-port=3307
```

#### Salida Esperada
```
Creando proyecto Bedrock: mi-sitio

Instalando Roots Bedrock...
✓ Instalando Roots Bedrock completado

Instalando Roots Acorn...
✓ Acorn instalado

Instalando Redis Object Cache...
✓ Redis instalado

Configurando Docker...
✓ Archivos Docker creados

Creando estructura extendida...
✓ Estructura creada

Generando archivo .env...
✓ Archivo .env generado

Configurando application.php con guards de constantes...
✓ application.php y environments configurados

Configurando bedrock-cli...
✓ bedrock-cli configurado

Inicializando Git...
✓ Git inicializado

✓ Proyecto 'mi-sitio' creado exitosamente

Próximos pasos:
  cd mi-sitio
  docker-compose up -d

Instalar WordPress:
  docker-compose exec web wp core install \
    --url=http://localhost:80 \
    --title="Mi Sitio" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com

⚠️  ACORN INSTALADO - Configuración requerida:
  Después de instalar WordPress, ejecuta:
    docker-compose exec web wp plugin activate acorn
    docker-compose exec web wp acorn acorn:init storage
    docker-compose exec web wp acorn vendor:publish --tag=acorn
```

---

### 3.2. MigrateCommand - Migración de Bases de Datos

**Archivo**: `src/Commands/MigrateCommand.php`  
**Líneas**: 1-250  
**Propósito**: Automatizar migración completa de bases de datos existentes a Bedrock

#### Firma del Comando
```bash
bedrock migrate --sql-file=dump.sql --old-url=https://old.com [opciones]
```

#### Opciones
| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `--sql-file` | String | - | Ruta al SQL dump (requerido) |
| `--old-prefix` | String | wp_ | Prefijo antiguo de tablas |
| `--new-prefix` | String | wp_ | Prefijo nuevo de tablas |
| `--old-url` | String | - | URL antigua (requerido) |
| `--new-url` | String | auto | URL nueva (detecta desde .env) |
| `--skip-acorn` | Flag | false | Saltar configuración Acorn |
| `--default-theme` | Flag | false | Activar twentytwentyfive |
| `--yes` / `-y` | Flag | false | Confirmar automáticamente |

#### Flujo de Ejecución
```
1. Validar inputs
   ├─ Verificar --sql-file existe
   ├─ Verificar --old-url presente
   └─ Detectar --new-url desde .env si no se proporciona

2. Leer credenciales desde .env
   ├─ DB_NAME
   ├─ DB_USER
   ├─ DB_PASSWORD
   └─ DB_HOST

3. Verificar Docker está corriendo
   └─ docker-compose ps --services --filter "status=running"

4. Confirmar con usuario (si no --yes)
   └─ "¿Continuar? Esto sobrescribirá la BD"

5. Importar SQL dump
   └─ docker-compose exec -T mysql mysql < dump.sql
   └─ Timeout: 600 segundos (10 minutos)

6. Limpiar BD (si old-prefix ≠ new-prefix)
   ├─ Ejecutar scripts/clean-database.php
   ├─ Renombrar tablas: old_ → new_
   ├─ Eliminar tablas multisite
   └─ Limpiar usermeta y options

7. Reemplazar URLs
   └─ wp search-replace old-url new-url --all-tables

8. Configurar Acorn (si no --skip-acorn)
   ├─ wp acorn acorn:init storage
   └─ wp acorn vendor:publish --tag=acorn

9. Activar tema (si --default-theme)
   └─ wp theme activate twentytwentyfive

10. Mostrar resumen y próximos pasos
```

#### Métodos Principales
| Método | Descripción |
|--------|-------------|
| `configure()` | Define opciones del comando |
| `execute()` | Orquesta migración completa |
| `isDockerRunning()` | Verifica estado de Docker |
| `getEnvValue()` | Lee valores de .env |

#### Dependencias
- `Symfony\Component\Console\Command\Command`
- `Symfony\Component\Console\Question\ConfirmationQuestion`
- `Symfony\Component\Process\Process`
- Docker + docker-compose (externo)
- WP-CLI (externo, dentro de contenedor)
- `scripts/clean-database.php` (generado por NewCommand)

#### Ejemplo de Uso
```bash
# Migración básica
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://old-site.com

# Migración con cambio de prefijo
bedrock migrate \
  --sql-file=dump.sql \
  --old-prefix=hp2f_ \
  --new-prefix=wp_ \
  --old-url=https://detodo24.com

# Migración sin Acorn
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://site.com \
  --skip-acorn

# Migración con confirmación automática
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://site.com \
  --yes
```

#### Salida Esperada
```
🚀 Iniciando migración de base de datos...

1. Verificando Docker...
✓ Docker corriendo

¿Continuar con la importación? Esto sobrescribirá la BD 'my_database' (y/n): y

2. Importando SQL dump...
   Archivo: dump.sql
✓ SQL importado correctamente

3. Limpiando base de datos...
   Renombrando: hp2f_ → wp_
✓ Base de datos limpiada

4. Reemplazando URLs...
   https://old-site.com → http://localhost:8080
✓ 1247 reemplazos realizados

5. Configurando Acorn...
✓ Storage inicializado
✓ Configs publicados

✅ Migración completada exitosamente

Próximos pasos:
  • Accede a: http://localhost:8080
  • Verifica plugins: docker-compose exec web wp plugin list
  • Verifica tema: docker-compose exec web wp theme list
```

#### Problemas Conocidos
Ver `PLAN_MEJORAS_MIGRATE.md` para problemas detectados:
- Claves duplicadas en SQL
- Tablas wp_ pre-existentes
- Falta validación de estado

---

### 3.3. SetupCommand - Setup Automatizado

**Archivo**: `src/Commands/SetupCommand.php`  
**Líneas**: 1-150  
**Propósito**: Automatizar instalación de WordPress y configuración de Acorn

#### Firma del Comando
```bash
bedrock setup [opciones]
```

#### Opciones
| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `--url` | String | http://localhost | URL del sitio |
| `--title` | String | Mi Sitio | Título del sitio |
| `--admin-user` | String | admin | Usuario admin |
| `--admin-password` | String | admin | Contraseña admin |
| `--admin-email` | String | admin@example.com | Email admin |
| `--skip-wp-install` | Flag | false | Saltar instalación WP |
| `--skip-acorn` | Flag | false | Saltar configuración Acorn |

#### Flujo de Ejecución
```
1. Verificar proyecto Bedrock
   └─ Verificar composer.json existe

2. Verificar Docker corriendo
   └─ docker-compose ps

3. Instalar WordPress (si no --skip-wp-install)
   └─ wp core install --url=... --title=... --admin_user=...

4. Configurar Acorn (si no --skip-acorn y Acorn instalado)
   ├─ wp acorn acorn:init storage
   └─ wp acorn vendor:publish --tag=acorn

5. Mostrar credenciales y próximos pasos
```

#### Métodos Principales
| Método | Descripción |
|--------|-------------|
| `configure()` | Define opciones |
| `execute()` | Ejecuta setup completo |

#### Dependencias
- `Symfony\Component\Console\Command\Command`
- `Symfony\Component\Process\Process`
- Docker + WP-CLI (externo)

#### Ejemplo de Uso
```bash
# Setup básico
bedrock setup

# Setup con URL personalizada
bedrock setup --url=http://localhost:8080

# Setup sin instalar WordPress (si ya importaste SQL)
bedrock setup --skip-wp-install

# Setup sin Acorn
bedrock setup --skip-acorn
```

#### Salida Esperada
```
🚀 Iniciando setup automático...

Verificando Docker...
✓ Docker corriendo

Instalando WordPress...
✓ WordPress instalado

Configurando Acorn...
✓ Acorn configurado

✅ Setup completado exitosamente

Próximos pasos:
  - Visita: http://localhost
  - Usuario: admin
  - Contraseña: admin

Comandos útiles:
  php vendor/bin/bedrock db:clean --old-prefix=hp2f_ --new-prefix=wp_
  docker-compose exec web wp plugin list
  docker-compose exec web wp acorn --help
```

---


## 4. COMANDOS DE MENÚS

### 4.1. MainMenuCommand - Menú Principal Interactivo

**Archivo**: `src/Commands/MainMenuCommand.php`  
**Líneas**: 1-80  
**Propósito**: Menú interactivo principal con acceso a todos los comandos

#### Firma del Comando
```bash
bedrock menu
```

#### Opciones del Menú
| # | Comando | Descripción |
|---|---------|-------------|
| 1 | Setup | Configuración inicial |
| 2 | Docker | Gestión de contenedores |
| 3 | Database | Gestión de base de datos |
| 4 | Options | Gestión de opciones WP |
| 5 | Install | Instalar WordPress |
| 6 | Plugins | Gestión de plugins |
| 7 | Themes | Gestión de temas |
| 8 | Acorn | Gestión de Roots Acorn |
| 9 | Backup | Crear backup |
| 10 | Reinstall | Reinstalar aplicación (DESTRUCTIVO) |
| 11 | Doctor | Verificar dependencias |
| 0 | Salir | - |

#### Flujo de Ejecución
```
1. Mostrar menú con diseño ASCII
2. Solicitar selección del usuario
3. Mapear selección a comando
4. Ejecutar comando seleccionado
5. Volver al menú (loop infinito hasta salir)
```

#### Métodos Principales
| Método | Descripción |
|--------|-------------|
| `configure()` | Define nombre y descripción |
| `execute()` | Loop principal del menú |

#### Dependencias
- `Symfony\Component\Console\Question\ChoiceQuestion`
- `Symfony\Component\Console\Cursor`

---

### 4.2. AcornCommand - Menú Acorn Fase 1 (COMPLETADO)

**Archivo**: `src/Commands/AcornCommand.php`  
**Líneas**: 1-450  
**Propósito**: Gestión completa de Roots Acorn con menú interactivo de 11 opciones

#### Firma del Comando
```bash
bedrock acorn
```

#### Opciones del Menú
| # | Acción | Descripción |
|---|--------|-------------|
| 1 | Instalar paquete | composer require roots/acorn |
| 2 | Inicializar storage | wp acorn acorn:init storage |
| 3 | Publicar configs | wp acorn vendor:publish --tag=acorn |
| 4 | Instalar completo | Ejecuta 1+2+3 |
| 5 | Limpiar cache | Elimina cache de storage/ |
| 6 | Ver estado detallado | Muestra versión, tamaño, configs |
| 7 | Eliminar configs y storage | Elimina archivos generados |
| 8 | Desinstalar paquete | composer remove roots/acorn |
| 9 | Desinstalar completo | Ejecuta 7+8 |
| 10 | ¿Qué es Acorn? | Ayuda educativa |
| 11 | Ventajas y desventajas | Pros/cons de usar Acorn |
| 0 | Salir | - |

#### Estado Visual
```
Estado Actual:
 Paquete Composer: ✓ Instalado / ✗ No instalado
 Storage:          ✓ Inicializado / ✗ No inicializado
 Configs:          ✓ Publicados / ✗ No publicados
```

#### Métodos Principales
| Método | Descripción |
|--------|-------------|
| `configure()` | Define comando |
| `execute()` | Muestra menú y ejecuta acción |
| `showStatus()` | Muestra estado actual |
| `showDetailedStatus()` | Estado detallado con tamaños |
| `showHelp()` | Ayuda educativa sobre Acorn |
| `showProsAndCons()` | Ventajas y desventajas |
| `installPackage()` | composer require roots/acorn |
| `removePackage()` | composer remove roots/acorn |
| `fullInstall()` | Instalación completa (1+2+3) |
| `fullUninstall()` | Desinstalación completa (7+8) |
| `removeFiles()` | Elimina configs y storage |
| `initStorage()` | wp acorn acorn:init storage |
| `publishConfigs()` | wp acorn vendor:publish |
| `cleanStorage()` | Limpia cache |
| `isPackageInstalled()` | Verifica composer.json |
| `getAcornVersion()` | Lee versión de composer.lock |
| `getDirectorySize()` | Calcula tamaño de directorio |
| `formatBytes()` | Formatea bytes a KB/MB/GB |

#### Dependencias
- `Symfony\Component\Console\Question\ChoiceQuestion`
- `Symfony\Component\Console\Question\ConfirmationQuestion`
- `Symfony\Component\Process\Process`
- Composer (externo)
- WP-CLI + Acorn (externo, en contenedor)

#### Ejemplo de Uso
```bash
bedrock acorn
```

#### Salida Esperada
```
╔═══════════════════════════════════════╗
║  Acorn - Gestión Completa             ║
╚═══════════════════════════════════════╝

Estado Actual:
 Paquete Composer: ✓ Instalado
 Storage:          ✓ Inicializado
 Configs:          ✓ Publicados

Selecciona una acción:
  [1] Instalar paquete Composer
  [2] Inicializar storage
  [3] Publicar configs
  [4] Instalar completo (1+2+3)
  [5] Limpiar cache
  [6] Ver estado detallado
  [7] Eliminar configs y storage
  [8] Desinstalar paquete Composer
  [9] Desinstalar completo (7+8)
  [10] ¿Qué es Acorn? (Ayuda)
  [11] Ventajas y desventajas
  [0] Salir
```

#### Características Innovadoras
1. **Validación de Composer**: Verifica si paquete está en composer.json
2. **Estado Visual**: Muestra qué está instalado/configurado
3. **Instalación Completa**: Opción 4 ejecuta todo el flujo
4. **Desinstalación Segura**: Confirmación antes de eliminar
5. **Ayuda Educativa**: Explica qué es Acorn, cuándo usarlo
6. **Estado Detallado**: Muestra versión, tamaño de storage, configs
7. **Limpieza de Cache**: Elimina cache sin desinstalar
8. **Formato de Bytes**: Muestra tamaños en KB/MB/GB

---

### 4.3. Sistema de Menús Inteligentes (DISEÑO FUTURO)

**Archivo**: `.io/DESIGN_MENU_SYSTEM.md`  
**Estado**: Fase 1 completada (AcornCommand), Fases 2-4 pendientes

#### Filosofía del Diseño
1. **Guiado por Contexto**: Detecta estado y sugiere siguiente paso
2. **Educativo**: Explica qué hace cada opción
3. **Validación Inteligente**: Detecta dependencias faltantes
4. **Orden Lógico**: Pasos en orden correcto
5. **Reversible**: Toda acción tiene opción de deshacer

#### Fases de Implementación

**Fase 1: Menú Acorn Mejorado** ✅ COMPLETADA
- Validación de paquete Composer
- Opciones de instalar/desinstalar
- Estado visual detallado
- Sección de ayuda integrada

**Fase 2: Wizard de Inicio Rápido** ⏳ PENDIENTE
- Detección de estado del proyecto
- Flujo guiado paso a paso
- Modo tutorial opcional
- Comandos: WizardCommand, SetupMenuCommand

**Fase 3: Sistema de Tareas Pendientes** ⏳ PENDIENTE
- Detección automática de problemas
- Sugerencias contextuales
- Priorización de tareas
- Servicios: StateDetector, DependencyValidator

**Fase 4: Características Avanzadas** ⏳ PENDIENTE
- Perfiles de configuración
- Health check automático
- Historial de acciones
- Exportar/importar configs
- Servicios: TutorialService, SnapshotService

#### Ideas Innovadoras (10)
1. Sistema de Tareas Pendientes
2. Historial de Acciones
3. Perfiles de Configuración
4. Comparador de Estados
5. Asistente de Troubleshooting
6. Modo Experto vs Principiante
7. Generador de Documentación
8. Integración con Git
9. Health Check Automático
10. Exportar/Importar Configuración

Ver `DESIGN_MENU_SYSTEM.md` para detalles completos.

---


## 5. COMANDOS DOCKER Y DATABASE

### 5.1. DockerCommand - Gestión de Contenedores

**Archivo**: `src/Commands/DockerCommand.php` (250 líneas)  
**Propósito**: Gestión completa de contenedores Docker con menú interactivo

#### Opciones CLI
| Opción | Descripción |
|--------|-------------|
| `--up` | Levantar contenedores |
| `--down` | Bajar contenedores |
| `--restart` | Reiniciar contenedores |
| `--status` | Ver estado |
| `--build` | Rebuild al levantar |

#### Menú Interactivo (7 opciones)
1. Levantar contenedores
2. Bajar contenedores
3. Reiniciar contenedores
4. Reconstruir (sin caché)
5. Reconstruir (con caché)
6. Ver estado
7. Ver logs

#### Métodos Principales
- `execute()`: Verifica Docker, ejecuta opciones o muestra menú
- `showMenu()`: Loop de menú interactivo
- `up()`: docker-compose up -d [--build]
- `down()`: docker-compose down
- `restart()`: docker-compose restart
- `status()`: docker-compose ps
- `rebuild()`: docker-compose build [--no-cache]
- `logs()`: docker-compose logs --tail=100
- `startDockerDesktop()`: Inicia Docker Desktop si no está corriendo

---

### 5.2. DatabaseCommand - Gestión de Base de Datos

**Archivo**: `src/Commands/DatabaseCommand.php` (400 líneas)  
**Propósito**: Gestión completa de BD con menú interactivo y seeders

#### Opciones CLI
| Opción | Formato | Descripción |
|--------|---------|-------------|
| `--create` | Flag | Crear BD |
| `--drop` | Flag | Eliminar BD |
| `--import` | File | Importar SQL |
| `--export` | File | Exportar SQL |
| `--search-replace` | "old\|new" | Buscar/reemplazar |
| `--prefix-replace` | "old\|new" | Cambiar prefijo |

#### Menú Interactivo (9 opciones)
1. Crear base de datos
2. Eliminar base de datos (confirmación de seguridad)
3. Resetear base de datos (confirmación de seguridad)
4. Importar SQL
5. Exportar SQL
6. Buscar/Reemplazar en DB
7. Cambiar Prefijo de tablas
8. Ejecutar Query SQL
9. Seeders (submenú)

#### Submenú Seeders
- Ejecutar todos los seeders
- Ejecutar todos (fresh - resetea DB)
- Crear nuevo seeder
- Ejecutar seeder específico

#### Métodos Principales
- `execute()`: Ejecuta opciones CLI o muestra menú
- `showMenu()`: Loop de menú interactivo
- `create()`: wp db create
- `drop()`: wp db drop (con confirmación)
- `reset()`: wp db reset (con confirmación)
- `import()`: Importa SQL desde archivo
- `export()`: Exporta SQL a archivo
- `searchReplace()`: wp search-replace --all-tables
- `prefixReplace()`: wp db prefix replace
- `query()`: wp db query
- `seedersMenu()`: Gestión de seeders
- `runSeed()`: Ejecuta seeders
- `createSeeder()`: Crea plantilla de seeder

---

### 5.3. DbCleanCommand - Limpieza de Base de Datos

**Archivo**: `src/Commands/DbCleanCommand.php` (60 líneas)  
**Propósito**: Limpiar BD de multisite y cambiar prefijos

#### Opciones
| Opción | Default | Descripción |
|--------|---------|-------------|
| `--from-multisite` | - | Eliminar tablas multisite |
| `--old-prefix` | hp2f_ | Prefijo antiguo |
| `--new-prefix` | wp_ | Prefijo nuevo |

#### Flujo
1. Verifica que exista `scripts/clean-database.php`
2. Ejecuta script PHP con prefijos
3. Renombra tablas y limpia opciones

---

### 5.4. BackupCommand - Backup Completo

**Archivo**: `src/Commands/BackupCommand.php` (20 líneas)  
**Estado**: ⏳ Pendiente de implementación

---

### 5.5. SnapshotCommand - Snapshots de BD

**Archivo**: `src/Commands/SnapshotCommand.php` (180 líneas)  
**Propósito**: Crear y restaurar snapshots rápidos de BD

#### Opciones
| Opción | Descripción |
|--------|-------------|
| `--create` | Crear snapshot |
| `--restore` | Restaurar snapshot |
| `--name` | Nombre del snapshot |

#### Flujo Create
1. Genera nombre: `{name}_{timestamp}.sql`
2. Ejecuta mysqldump vía docker-compose
3. Guarda en `database/snapshots/`

#### Flujo Restore
1. Lista snapshots disponibles
2. Permite selección interactiva
3. Importa SQL vía mysql

#### Métodos Principales
- `createSnapshot()`: Exporta BD a archivo
- `restoreSnapshot()`: Importa BD desde archivo
- `loadEnv()`: Lee credenciales de .env

---


## 6. COMANDOS PLUGINS (10 comandos)

**Archivos**: `src/Commands/Plugins*.php` (~1,500 líneas total)

### Comandos Principales
- **PluginsCommand**: Menú interactivo con 7 opciones (gestionar, listar, instalar, actualizar, descomprimir, orden)
- **PluginsListCommand**: Lista plugins desde filesystem
- **PluginsStatusCommand**: Estado de plugins vía WP-CLI
- **PluginsActivateCommand**: Activar plugin específico
- **PluginsDeactivateCommand**: Desactivar plugin
- **PluginsCompressCommand**: Comprimir plugin a ZIP
- **PluginsOrderCommand**: Gestionar orden de activación (save/list/activate)
- **PluginsOrderMenuCommand**: Menú interactivo para órdenes guardados
- **PluginsOrderBuilderCommand**: Constructor interactivo de orden

### Características Clave
- Descompresión masiva de ZIPs
- Gestión de orden de activación con dependencias
- Compresión de plugins a ZIP
- Eliminación directa de carpetas (filesystem)
- Detección inteligente de errores (DB, Docker, WP no instalado)

---

## 7. COMANDOS THEMES Y OPTIONS (8 comandos)

**Archivos**: `src/Commands/Themes*.php` + `Options*.php` (~800 líneas)

### Themes (5 comandos)
- **ThemesCommand**: Menú interactivo
- **ThemesListCommand**: Lista temas
- **ThemesStatusCommand**: Estado de temas
- **ThemesActivateCommand**: Activar tema
- **ThemesCompressCommand**: Comprimir tema a ZIP

### Options (5 comandos)
- **OptionsCommand**: Menú interactivo
- **OptionsListCommand**: Lista opciones WP
- **OptionsManageCommand**: Gestión de opciones
- **OptionsPullCommand**: Exportar opciones a JSON
- **OptionsPushCommand**: Importar opciones desde JSON

---

## 8. COMANDOS UTILIDADES (6 comandos)

**Archivos**: `src/Commands/*.php` (~400 líneas)

- **DoctorCommand**: Verifica dependencias del sistema (Docker, WP-CLI, Composer, Git)
- **InstallCommand**: Instalación interactiva de WordPress
- **ReinstallCommand**: Reinstalación completa (DESTRUCTIVO con confirmación)
- **SeedCommand**: Ejecutar seeders de BD
- **UpdateCommand**: Actualizar WordPress core
- **ExportConfigCommand**: Exportar configuración completa
- **ImportCoreCommand**: Importar golden-image.sql

---

## 9. SERVICIOS (6 clases)

**Archivos**: `src/Services/*.php` (~600 líneas)

### DockerService
- `isRunning()`: Verifica si Docker está corriendo
- `up()`, `down()`, `restart()`: Gestión de contenedores
- `status()`, `logs()`: Información de contenedores

### WpCliService
- `run()`: Ejecuta comandos WP-CLI
- `pluginInstall()`, `pluginActivate()`, `pluginDeactivate()`: Gestión plugins
- `dbCreate()`, `dbDrop()`, `dbReset()`: Gestión BD
- `custom()`: Ejecutar comando WP-CLI personalizado

### SecurityService
- `confirmDangerousAction()`: Confirmación con código aleatorio de 6 dígitos
- Usado en: drop, reset, reinstall

### ZipService
- `compress()`: Comprimir carpeta a ZIP
- Usado para comprimir plugins/temas

### UnzipService
- `unzip()`: Descomprimir ZIP
- `listZipFiles()`: Listar ZIPs en carpeta
- `detectProjectRoot()`: Detectar raíz del proyecto

### ProgressService
- `runWithLoader()`: Barra de progreso animada
- Frames: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏

---

## 10. STUBS Y PLANTILLAS (14 archivos)

**Ubicación**: `stubs/` (~1,000 líneas)

### 10.1. .env.stub - Variables de Entorno

**Propósito**: Configuración de entorno del proyecto Bedrock

**Contenido Completo**:
```env
DB_NAME='{{DB_NAME}}'
DB_USER='{{DB_USER}}'
DB_PASSWORD='{{DB_PASSWORD}}'
DB_HOST='mysql'

WP_ENV='development'
WP_HOME='http://localhost:{{HTTP_PORT}}'
WP_SITEURL="${WP_HOME}/wp"

AUTH_KEY='{{AUTH_KEY}}'
SECURE_AUTH_KEY='{{SECURE_AUTH_KEY}}'
LOGGED_IN_KEY='{{LOGGED_IN_KEY}}'
NONCE_KEY='{{NONCE_KEY}}'
AUTH_SALT='{{AUTH_SALT}}'
SECURE_AUTH_SALT='{{SECURE_AUTH_SALT}}'
LOGGED_IN_SALT='{{LOGGED_IN_SALT}}'
NONCE_SALT='{{NONCE_SALT}}'

# Acorn
APP_KEY='{{APP_KEY}}'

# Redis
WP_CACHE=true
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
WP_REDIS_PREFIX='wp'
```

**Variables de Reemplazo**:
- `{{DB_NAME}}` → Nombre de BD (default: nombre-proyecto con guiones reemplazados por _)
- `{{DB_USER}}` → Usuario de BD (default: root)
- `{{DB_PASSWORD}}` → Contraseña de BD (default: mysql)
- `{{HTTP_PORT}}` → Puerto HTTP auto-detectado (default: 8080)
- `{{AUTH_KEY}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{SECURE_AUTH_KEY}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{LOGGED_IN_KEY}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{NONCE_KEY}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{AUTH_SALT}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{SECURE_AUTH_SALT}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{LOGGED_IN_SALT}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{NONCE_SALT}}` → Salt generado con `bin2hex(random_bytes(32))`
- `{{APP_KEY}}` → Clave Acorn: `'base64:' . base64_encode(random_bytes(32))`

**Usado Por**: `NewCommand::generateEnvFile()`

**Ejemplo de Resultado**:
```env
DB_NAME='mi_proyecto'
DB_USER='root'
DB_PASSWORD='mysql'
DB_HOST='mysql'

WP_ENV='development'
WP_HOME='http://localhost:8080'
WP_SITEURL="${WP_HOME}/wp"

AUTH_KEY='a1b2c3d4e5f6...'
SECURE_AUTH_KEY='f6e5d4c3b2a1...'
...

APP_KEY='base64:xYz123...'
```

---

### 10.2. docker-compose.yml.stub - Orquestación de Contenedores

**Propósito**: Define servicios Docker (web, nginx, mysql, redis)

**Contenido Completo**:
```yaml
services:
  web:
    build:
      context: .
      dockerfile: Dockerfile.web
    container_name: {{PROJECT_NAME}}_web
    working_dir: /var/www/html
    environment:
      - MYSQL_PWD={{DB_PASSWORD}}
      - WP_CLI_ALLOW_ROOT=1
      - WP_CLI_STRICT_ARGS_MODE=0
    volumes:
      - ./:/var/www/html
      - ./docker/mysql/client.cnf:/etc/mysql/conf.d/client.cnf:ro
    depends_on:
      - mysql

  nginx:
    image: nginx:alpine
    container_name: {{PROJECT_NAME}}_nginx
    ports:
      - "{{HTTP_PORT}}:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - web

  mysql:
    image: mysql:5.7
    container_name: {{PROJECT_NAME}}_mysql
    restart: always
    command: --skip-ssl --require_secure_transport=OFF
    environment:
      MYSQL_ROOT_PASSWORD: {{DB_PASSWORD}}
      MYSQL_DATABASE: {{DB_NAME}}
    ports:
      - "{{MYSQL_PORT}}:3306"
    volumes:
      - dbdata:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/ssl.cnf:ro

  redis:
    image: redis:7-alpine
    container_name: {{PROJECT_NAME}}_redis
    restart: always
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru
    ports:
      - "{{REDIS_PORT}}:6379"
    volumes:
      - redisdata:/data

volumes:
  dbdata:
  redisdata:
```

**Variables de Reemplazo**:
- `{{PROJECT_NAME}}` → Nombre del proyecto (usado en nombres de contenedores)
- `{{DB_PASSWORD}}` → Contraseña de MySQL
- `{{DB_NAME}}` → Nombre de la base de datos
- `{{HTTP_PORT}}` → Puerto HTTP externo (ej: 8080)
- `{{MYSQL_PORT}}` → Puerto MySQL externo (ej: 3306)
- `{{REDIS_PORT}}` → Puerto Redis externo (ej: 6379)

**Servicios Definidos**:
1. **web**: PHP-FPM 8.4 con WP-CLI y Composer
2. **nginx**: Servidor web (proxy a PHP-FPM)
3. **mysql**: Base de datos MySQL 5.7
4. **redis**: Caché de objetos Redis 7

**Usado Por**: `NewCommand::setupDocker()`

---

### 10.3. Dockerfile.web.stub - Imagen PHP-FPM

**Propósito**: Imagen Docker con PHP 8.4, WP-CLI, Composer, extensiones

**Características**:
- Base: `php:8.4-fpm`
- PHP Extensions: `zip`, `mysqli`, `pdo`, `pdo_mysql`
- Herramientas: Composer, WP-CLI, Git, Python3, Subversion
- Wrappers: MariaDB con `--skip-ssl` automático
- PHP-FPM: Configurado para 50 workers máximo

**Usado Por**: `NewCommand::setupDocker()`

---

### 10.4. docker/nginx/default.conf.stub - Configuración Nginx

**Propósito**: Configuración de servidor web Nginx

**Contenido Completo**:
```nginx
server {
    listen 80;
    server_name 127.0.0.1;
    root /var/www/html/web;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass web:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

**Características**:
- Document root: `/var/www/html/web` (estructura Bedrock)
- FastCGI: Proxy a contenedor `web:9000`
- Permalinks: Soporte para URLs amigables

**Usado Por**: `NewCommand::setupDocker()`

---

### 10.5. docker/mysql/my.cnf.stub - Configuración MySQL

**Propósito**: Deshabilitar SSL en MySQL (desarrollo local)

**Contenido Completo**:
```ini
[mysqld]
skip-ssl
require_secure_transport=OFF
```

**Razón**: Evita errores de SSL en desarrollo local

**Usado Por**: `NewCommand::setupDocker()`

---

### 10.6. config/application.php.stub - Configuración Principal Bedrock

**Propósito**: Archivo de configuración principal de WordPress con guards

**Características Clave**:
- **Guards de constantes**: Usa `if (!defined())` para prevenir redefiniciones
- **Dotenv**: Carga variables de `.env` y `.env.local`
- **Redis**: Configuración completa de Object Cache
- **Seguridad**: `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`
- **Performance**: Memory limits 512M, `CONCATENATE_SCRIPTS` false
- **HTTPS Detection**: Soporte para proxies inversos

**Constantes Definidas**:
- `WP_ENV`, `WP_ENVIRONMENT_TYPE`
- `WP_HOME`, `WP_SITEURL`
- `CONTENT_DIR`, `WP_CONTENT_DIR`, `WP_CONTENT_URL`
- `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `DB_CHARSET`
- `AUTH_KEY`, `SECURE_AUTH_KEY`, etc. (8 salts)
- `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_PREFIX`
- `WP_MEMORY_LIMIT`, `WP_MAX_MEMORY_LIMIT`
- `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`
- `WP_POST_REVISIONS`, `CONCATENATE_SCRIPTS`

**Usado Por**: `NewCommand::copyApplicationConfig()`

---

### 10.7. mu-plugins/acorn-boot.php.stub - Bootloader de Acorn

**Propósito**: Carga Roots Acorn en WordPress

**Contenido Completo**:
```php
<?php
/*
Plugin Name:  Acorn Bootloader
Description:  Boots the Acorn framework.
*/

// Solo cargar si WordPress está instalado
if (! defined('ABSPATH') || ! is_blog_installed()) {
    return;
}

if (! class_exists(\Roots\Acorn\Application::class)) {
    return;
}

// Boot Acorn after WordPress is fully loaded
add_action('after_setup_theme', function () {
    try {
        \Roots\Acorn\Application::configure()
            ->withProviders([
                \App\Providers\AppServiceProvider::class,
            ])
            ->boot();
    } catch (\Throwable $e) {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::error('Acorn boot failed: ' . $e->getMessage());
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Acorn boot error: ' . $e->getMessage());
        }
    }
}, 0);
```

**Características**:
- Verifica que WordPress esté instalado
- Verifica que Acorn esté disponible
- Manejo de errores con try/catch
- Logging de errores en WP-CLI y WP_DEBUG
- Hook: `after_setup_theme` (prioridad 0)

**Usado Por**: `NewCommand::installAcorn()`

**IMPORTANTE**: Sin este archivo, Acorn NO funcionará

---

### 10.8. scripts/clean-database.php.stub - Limpieza de BD

**Propósito**: Script CLI para limpiar BD de WordPress

**Funcionalidades**:
1. **Cambiar prefix de tablas**: `old_posts` → `new_posts`
2. **Eliminar tablas multisite**: `wp_blogs`, `wp_site`, etc.
3. **Limpiar opciones obsoletas**: `active_sitewide_plugins`, etc.
4. **Actualizar usermeta**: Reemplazar prefix en meta_keys
5. **Optimizar tablas**: `OPTIMIZE TABLE` en todas las tablas

**Uso**:
```bash
php scripts/clean-database.php <old-prefix> <new-prefix>

# Ejemplo
php scripts/clean-database.php hp2f_ wp_
```

**Variables de Entorno**:
- `DB_HOST` (default: mysql)
- `DB_NAME` (default: {{DB_NAME}})
- `DB_USER` (default: root)
- `DB_PASSWORD` (default: mysql)

**Usado Por**: `MigrateCommand` (Paso 3)

---

### 10.9. Otros Stubs

**`.gitignore.stub`**: Archivos a ignorar en Git
**`README.project.stub`**: README del proyecto generado
**`config/environments/development.php.stub`**: Configuración de desarrollo
**`config/environments/staging.php.stub`**: Configuración de staging
**`docker/mysql/client.cnf.stub`**: Configuración de cliente MySQL
**`scripts/sanitize-db.sh.stub`**: Script bash de sanitización

---

## 11. FLUJOS DE TRABAJO

### 11.1. Flujo: Creación de Proyecto Completo
```
bedrock new mi-sitio --with-docker
  ↓
1. composer create-project roots/bedrock
2. composer require roots/acorn
3. composer require rhubarbgroup/redis-cache
4. Generar docker-compose.yml, Dockerfile, configs
5. Crear estructura (database/, config/, scripts/)
6. Generar .env con salts aleatorios
7. Copiar application.php con guards
8. composer require achatainga/bedrock-cli
9. git init && git commit
  ↓
docker-compose up -d
  ↓
bedrock setup (o wp core install)
  ↓
✅ Proyecto listo
```

### 11.2. Flujo: Migración de Base de Datos
```
bedrock migrate --sql-file=dump.sql --old-url=https://old.com
  ↓
1. Validar Docker corriendo
2. Confirmar con usuario
3. Importar SQL (timeout 10 min)
4. Limpiar BD (si cambio de prefijo)
   - Renombrar tablas
   - Eliminar multisite
   - Limpiar usermeta/options
5. wp search-replace old-url new-url
6. Configurar Acorn (storage + configs)
7. Activar tema (opcional)
  ↓
✅ Migración completada
```

### 11.3. Flujo: Sistema de Menús Inteligentes
```
bedrock menu
  ↓
MainMenuCommand (11 opciones)
  ↓
bedrock acorn
  ↓
AcornCommand (11 opciones)
  ├─ Instalar completo (1+2+3)
  ├─ Ver estado detallado
  ├─ Ayuda educativa
  └─ Desinstalar completo (7+8)
  ↓
✅ Gestión completa de Acorn
```

---

## 12. CASOS DE USO

### 12.1. Caso de Uso 1: Migrar Sitio WordPress Existente a Bedrock

**Contexto**: Tienes un sitio WordPress tradicional en producción y quieres modernizarlo usando Bedrock con Docker.

**Problema**: El sitio usa estructura monolítica, prefijo de tablas personalizado (`hp2f_`), y está en `https://detodo24.com`.

**Objetivo**: Migrar a Bedrock local con Docker, cambiar prefijo a `wp_`, y configurar en `http://localhost:8080`.

#### Paso 1: Exportar Base de Datos del Sitio Original
```bash
# En el servidor de producción
mysqldump -u usuario -p nombre_bd > detodo24_dump.sql

# Descargar el archivo a tu máquina local
scp usuario@servidor:/ruta/detodo24_dump.sql ~/Downloads/
```

#### Paso 2: Crear Proyecto Bedrock con Docker
```bash
# Crear proyecto con Docker, Acorn y Redis
bedrock new detodo24-local --with-docker

# Resultado esperado:
# ✓ Proyecto Bedrock creado
# ✓ Docker configurado (web, nginx, mysql, redis)
# ✓ Acorn instalado
# ✓ Redis instalado
# ✓ Git inicializado
```

#### Paso 3: Levantar Contenedores Docker
```bash
cd detodo24-local
docker-compose up -d

# Verificar que todos los servicios estén corriendo
docker-compose ps

# Resultado esperado:
# detodo24-local_web     running
# detodo24-local_nginx   running
# detodo24-local_mysql   running
# detodo24-local_redis   running
```

#### Paso 4: Migrar Base de Datos
```bash
# Copiar dump SQL al proyecto
cp ~/Downloads/detodo24_dump.sql .

# Ejecutar migración automática
bedrock migrate \
  --sql-file=detodo24_dump.sql \
  --old-prefix=hp2f_ \
  --new-prefix=wp_ \
  --old-url=https://detodo24.com \
  --new-url=http://localhost:8080

# El comando ejecutará automáticamente:
# 1. Importar SQL (timeout 10 min)
# 2. Renombrar tablas: hp2f_posts → wp_posts
# 3. Eliminar tablas multisite (si existen)
# 4. Limpiar usermeta y options
# 5. Reemplazar URLs: https://detodo24.com → http://localhost:8080
# 6. Configurar Acorn (storage + configs)
```

#### Paso 5: Verificar Migración
```bash
# Listar plugins
docker-compose exec web wp plugin list

# Resultado esperado:
# +--------------------------+----------+--------+---------+
# | name                     | status   | update | version |
# +--------------------------+----------+--------+---------+
# | dt24-metodos-envio       | active   | none   | 1.0.0   |
# | woocommerce              | active   | none   | 8.5.2   |
# +--------------------------+----------+--------+---------+

# Verificar tema activo
docker-compose exec web wp theme list

# Verificar opciones críticas
docker-compose exec web wp option get siteurl
# http://localhost:8080/wp

docker-compose exec web wp option get home
# http://localhost:8080
```

#### Paso 6: Acceder al Sitio
```bash
# Abrir navegador
open http://localhost:8080

# Login admin
open http://localhost:8080/wp/wp-admin
# Usuario: (el del sitio original)
# Contraseña: (la del sitio original)
```

#### Resultado Final
✅ **Sitio migrado exitosamente**
- Base de datos importada y limpiada
- Prefijo cambiado: `hp2f_` → `wp_`
- URLs actualizadas: `https://detodo24.com` → `http://localhost:8080`
- Plugins y temas funcionando
- Acorn configurado
- Redis activo para caché
- Entorno Docker completo

#### Tiempo Estimado
- Exportar BD: 2-5 minutos
- Crear proyecto: 3-5 minutos
- Levantar Docker: 1-2 minutos
- Migrar BD: 5-15 minutos (depende del tamaño)
- **Total: 15-30 minutos**

---

### 12.2. Caso de Uso 2: Crear Proyecto Nuevo con Docker y Acorn

**Contexto**: Iniciar un proyecto WordPress desde cero con arquitectura moderna.

**Objetivo**: Proyecto Bedrock con Docker, Acorn (Laravel components), Redis, y configuración completa.

#### Paso 1: Crear Proyecto
```bash
# Crear proyecto completo
bedrock new mi-tienda --with-docker

# Resultado:
# ✓ Bedrock instalado
# ✓ Acorn instalado (Laravel components)
# ✓ Redis instalado (object cache)
# ✓ Docker configurado (4 servicios)
# ✓ Estructura extendida creada:
#   - database/migrations/
#   - database/seeders/
#   - database/snapshots/
#   - config/plugins/
#   - scripts/
# ✓ .env generado con salts aleatorios
# ✓ application.php con guards
# ✓ Git inicializado
```

#### Paso 2: Levantar Entorno
```bash
cd mi-tienda
docker-compose up -d

# Verificar servicios
docker-compose ps
```

#### Paso 3: Instalar WordPress
```bash
# Opción A: Comando interactivo
bedrock setup

# Opción B: Comando directo
docker-compose exec web wp core install \
  --url=http://localhost:8080 \
  --title="Mi Tienda" \
  --admin_user=admin \
  --admin_password=SecurePass123! \
  --admin_email=admin@mitienda.com

# Resultado:
# Success: WordPress installed successfully.
```

#### Paso 4: Configurar Acorn
```bash
# Opción A: Menú interactivo
bedrock acorn
# Seleccionar opción [4] Instalar completo

# Opción B: Comandos manuales
docker-compose exec web wp acorn acorn:init storage
docker-compose exec web wp acorn vendor:publish --tag=acorn

# Resultado:
# ✓ Storage inicializado en storage/
# ✓ Configs publicados en config/
```

#### Paso 5: Instalar Plugins Esenciales
```bash
# WooCommerce
docker-compose exec web wp plugin install woocommerce --activate

# Advanced Custom Fields
docker-compose exec web wp plugin install advanced-custom-fields --activate

# Verificar
docker-compose exec web wp plugin list
```

#### Paso 6: Instalar Tema
```bash
# Opción A: Tema del repositorio
docker-compose exec web wp theme install storefront --activate

# Opción B: Tema personalizado (copiar a web/app/themes/)
cp -r ~/mi-tema-custom web/app/themes/
docker-compose exec web wp theme activate mi-tema-custom
```

#### Paso 7: Crear Snapshot Inicial
```bash
# Crear snapshot de la configuración base
bedrock snapshot --create --name=base-install

# Resultado:
# ✓ Snapshot creado: base-install_20250130-1430.sql
# Ubicación: database/snapshots/
```

#### Paso 8: Verificar Acorn Funciona
```bash
# Ver comandos Acorn disponibles
docker-compose exec web wp acorn list

# Resultado:
# Available commands:
#   acorn:init
#   cache:clear
#   config:cache
#   make:command
#   make:controller
#   make:model
#   vendor:publish
```

#### Paso 9: Acceder al Sitio
```bash
# Frontend
open http://localhost:8080

# Admin
open http://localhost:8080/wp/wp-admin
# Usuario: admin
# Contraseña: SecurePass123!
```

#### Resultado Final
✅ **Proyecto moderno listo para desarrollo**
- WordPress instalado
- Acorn configurado (Laravel components disponibles)
- Redis activo (caché de objetos)
- Docker con 4 servicios (web, nginx, mysql, redis)
- Plugins esenciales instalados
- Tema activado
- Snapshot inicial creado
- Git inicializado con commit inicial

#### Ventajas de esta Configuración
- **Acorn**: Usa componentes Laravel (Blade, Eloquent, Artisan)
- **Redis**: Caché de objetos ultra-rápida
- **Docker**: Entorno reproducible
- **Bedrock**: Estructura moderna con Composer
- **Snapshots**: Backup/restore rápido
- **Git**: Control de versiones desde día 1

#### Tiempo Estimado
- Crear proyecto: 3-5 minutos
- Levantar Docker: 1-2 minutos
- Instalar WordPress: 1 minuto
- Configurar Acorn: 1-2 minutos
- Instalar plugins/tema: 2-3 minutos
- **Total: 10-15 minutos**

---

### 12.3. Caso de Uso 3: Gestionar Orden de Activación de Plugins con Dependencias

**Contexto**: Tienes plugins con dependencias (ej: plugin A requiere plugin B activo primero).

**Problema**: WordPress activa plugins en orden alfabético, causando errores si las dependencias no están cargadas.

**Objetivo**: Definir orden de activación personalizado y aplicarlo automáticamente.

#### Escenario Real
Tienes estos plugins:
- `acorn` (framework base)
- `dt24-metodos-envio` (requiere Acorn)
- `dt24-filtros-productos` (requiere WooCommerce)
- `woocommerce` (debe cargar antes que dt24-filtros)
- `redis-cache` (debe cargar primero para caché)

**Orden correcto**:
1. `redis-cache` (caché)
2. `acorn` (framework)
3. `woocommerce` (ecommerce base)
4. `dt24-metodos-envio` (depende de Acorn)
5. `dt24-filtros-productos` (depende de WooCommerce)

#### Paso 1: Listar Plugins Actuales
```bash
# Ver plugins instalados
bedrock plugins:list

# Resultado:
# Plugins en web/app/plugins/:
#   - acorn/
#   - dt24-filtros-productos/
#   - dt24-metodos-envio/
#   - redis-cache/
#   - woocommerce/
```

#### Paso 2: Crear Orden de Activación
```bash
# Opción A: Constructor interactivo
bedrock plugins:order-builder

# El comando mostrará:
# Plugins disponibles:
#   [1] acorn
#   [2] dt24-filtros-productos
#   [3] dt24-metodos-envio
#   [4] redis-cache
#   [5] woocommerce
#
# Selecciona plugins en orden de activación:
# (Presiona Enter cuando termines)

# Seleccionar en orden:
4  # redis-cache
1  # acorn
5  # woocommerce
3  # dt24-metodos-envio
2  # dt24-filtros-productos
[Enter]

# Nombre del orden: produccion

# Resultado:
# ✓ Orden 'produccion' guardado en config/plugins/produccion.json
```

#### Paso 3: Ver Orden Guardado
```bash
# Listar órdenes guardados
bedrock plugins:order list

# Resultado:
# Órdenes de activación guardados:
#   - produccion (5 plugins)
#   - desarrollo (3 plugins)
#   - testing (4 plugins)

# Ver detalles de un orden
cat config/plugins/produccion.json

# Contenido:
# {
#   "name": "produccion",
#   "plugins": [
#     "redis-cache/redis-cache.php",
#     "acorn/acorn.php",
#     "woocommerce/woocommerce.php",
#     "dt24-metodos-envio/dt24-metodos-envio.php",
#     "dt24-filtros-productos/dt24-filtros-productos.php"
#   ]
# }
```

#### Paso 4: Aplicar Orden de Activación
```bash
# Opción A: Menú interactivo
bedrock plugins:order-menu
# Seleccionar orden 'produccion'
# Confirmar aplicación

# Opción B: Comando directo
bedrock plugins:order activate produccion

# El comando ejecutará:
# 1. Desactivar todos los plugins
# 2. Activar plugins en orden definido:
#    ✓ Activando redis-cache...
#    ✓ Activando acorn...
#    ✓ Activando woocommerce...
#    ✓ Activando dt24-metodos-envio...
#    ✓ Activando dt24-filtros-productos...
#
# ✓ Orden de activación aplicado correctamente
```

#### Paso 5: Verificar Orden Aplicado
```bash
# Ver plugins activos
docker-compose exec web wp plugin list --status=active

# Resultado (en orden de activación):
# +--------------------------+----------+---------+
# | name                     | status   | version |
# +--------------------------+----------+---------+
# | redis-cache              | active   | 2.5.0   |
# | acorn                    | active   | 4.0.0   |
# | woocommerce              | active   | 8.5.2   |
# | dt24-metodos-envio       | active   | 1.0.0   |
# | dt24-filtros-productos   | active   | 1.0.0   |
# +--------------------------+----------+---------+

# Verificar que no hay errores
docker-compose exec web wp plugin status dt24-metodos-envio
# Plugin dt24-metodos-envio details:
#   Name: DT24 Métodos de Envío
#   Status: Active
#   Version: 1.0.0
#   No errors detected
```

#### Paso 6: Crear Órdenes para Diferentes Entornos
```bash
# Orden para desarrollo (solo plugins esenciales)
bedrock plugins:order-builder
# Seleccionar: redis-cache, acorn, woocommerce
# Nombre: desarrollo

# Orden para testing (incluir plugins de debug)
bedrock plugins:order-builder
# Seleccionar: redis-cache, acorn, woocommerce, query-monitor, debug-bar
# Nombre: testing

# Listar todos los órdenes
bedrock plugins:order list
# - produccion (5 plugins)
# - desarrollo (3 plugins)
# - testing (5 plugins)
```

#### Paso 7: Cambiar Entre Órdenes
```bash
# Cambiar a orden de desarrollo
bedrock plugins:order activate desarrollo

# Resultado:
# ✓ Desactivando plugins no incluidos...
# ✓ Activando en orden: redis-cache, acorn, woocommerce
# ✓ Orden 'desarrollo' aplicado

# Volver a producción
bedrock plugins:order activate produccion
```

#### Resultado Final
✅ **Gestión profesional de dependencias de plugins**
- Orden de activación definido y guardado
- Plugins se activan en secuencia correcta
- Sin errores de dependencias
- Múltiples órdenes para diferentes entornos
- Cambio rápido entre configuraciones
- Archivos JSON versionables en Git

#### Ventajas
- **Reproducible**: Mismo orden en todos los entornos
- **Versionable**: Archivos JSON en Git
- **Automatizable**: Aplicar en CI/CD
- **Documentado**: Orden explícito en archivo
- **Flexible**: Múltiples órdenes para diferentes casos

#### Casos de Uso Adicionales
- **Onboarding**: Nuevo desarrollador aplica orden con 1 comando
- **CI/CD**: Pipeline aplica orden antes de tests
- **Staging**: Orden diferente que producción
- **Troubleshooting**: Desactivar todo y activar uno por uno

#### Tiempo Estimado
- Crear orden: 2-3 minutos
- Aplicar orden: 30 segundos
- Verificar: 1 minuto
- **Total: 5 minutos (primera vez), 30 segundos (subsecuentes)**

---

## 13. DIAGRAMAS

### 13.1. Diagrama de Clases Completo
```
Application (Symfony Console)
  ├─ 38 Commands
  │   ├─ Core (3): New, Migrate, Setup
  │   ├─ Menús (2): MainMenu, Acorn
  │   ├─ Docker/DB (5): Docker, Database, DbClean, Backup, Snapshot
  │   ├─ Plugins (10): Plugins, List, Status, Activate, Deactivate, Compress, Order, OrderMenu, OrderBuilder
  │   ├─ Themes (5): Themes, List, Status, Activate, Compress
  │   ├─ Options (5): Options, List, Manage, Pull, Push
  │   └─ Utilidades (6): Doctor, Install, Reinstall, Seed, Update, ExportConfig, ImportCore
  └─ 6 Services
      ├─ DockerService
      ├─ WpCliService
      ├─ SecurityService
      ├─ ZipService
      ├─ UnzipService
      └─ ProgressService
```

### 13.2. Diagrama de Dependencias
```
Commands → Services → Herramientas Externas
  ↓          ↓              ↓
NewCmd → DockerService → docker-compose
MigrateCmd → WpCliService → WP-CLI (en contenedor)
PluginsCmd → ZipService → ZipArchive (PHP)
DatabaseCmd → SecurityService → Confirmación usuario
```

---

## 14. DEPENDENCIAS

### 14.1. Composer (composer.json)
```json
{
  "require": {
    "php": ">=8.0",
    "symfony/console": "^6.0|^7.0|^8.0",
    "symfony/process": "^6.0|^7.0|^8.0",
    "symfony/filesystem": "^6.0|^7.0|^8.0",
    "symfony/yaml": "^6.0|^7.0|^8.0"
  }
}
```

### 14.2. Herramientas Externas
- **Docker**: Contenedores (opcional pero recomendado)
- **WP-CLI**: Gestión de WordPress (dentro de contenedor)
- **Composer**: Gestor de dependencias PHP
- **Git**: Control de versiones
- **MySQL**: Base de datos (en contenedor)
- **Redis**: Caché de objetos (en contenedor, opcional)
- **Nginx**: Servidor web (en contenedor)

---

## 15. PROBLEMAS CONOCIDOS

### 15.1. MigrateCommand - Claves Duplicadas
**Archivo**: `PLAN_MEJORAS_MIGRATE.md`  
**Severidad**: Alta  
**Descripción**: Al importar SQL con claves duplicadas, el comando falla  
**Solución Propuesta**: Validar SQL antes de importar, detectar duplicados  
**Estado**: Pendiente

### 15.2. MigrateCommand - Tablas Pre-existentes
**Severidad**: Media  
**Descripción**: Si existen tablas wp_ antes de migrar, hay conflictos  
**Solución Propuesta**: Detectar estado y ofrecer drop automático  
**Estado**: Pendiente

### 15.3. BackupCommand - No Implementado
**Severidad**: Baja  
**Descripción**: Comando backup está pendiente de implementación  
**Solución**: Usar SnapshotCommand como alternativa temporal  
**Estado**: Pendiente

---

## 16. ROADMAP

### Completado ✅
- [x] Comando `new` con Docker, Acorn, Redis
- [x] Comando `migrate` con limpieza automática
- [x] Sistema de menús interactivos (MainMenu, Acorn)
- [x] Gestión completa de plugins (10 comandos)
- [x] Gestión de temas y opciones
- [x] Snapshots de BD
- [x] Orden de activación de plugins
- [x] Mecanismos de seguridad (confirmación con código)
- [x] Servicios reutilizables (Docker, WpCli, Security, Zip, Unzip, Progress)

### En Progreso 🚧
- [ ] WizardCommand (Fase 2 de DESIGN_MENU_SYSTEM.md)
- [ ] Sistema de menús inteligentes completo
- [ ] Mejoras a MigrateCommand (validaciones)

### Planeado 📋
- [ ] BackupCommand completo (backup de DB + uploads)
- [ ] Sistema de snapshots con versionado
- [ ] Testing automatizado (PHPUnit)
- [ ] Health check automático
- [ ] Exportar/importar configuración completa
- [ ] Integración con Git (commits automáticos)
- [ ] Perfiles de configuración (dev, staging, prod)
- [ ] Historial de acciones
- [ ] Modo experto vs principiante

---

## 17. ESTADÍSTICAS DEL PROYECTO

**Total de Archivos Analizados**: 67  
**Total de Líneas de Código**: ~8,000  
**Total de Comandos**: 38  
**Total de Servicios**: 6  
**Total de Stubs**: 14  
**Lenguaje**: PHP 8.0+  
**Framework**: Symfony Console 6.0+  
**Licencia**: MIT  

---

**FIN DEL DOCUMENTO**

**Fecha de Generación**: 2025-01-30  
**Versión**: 1.0.0  
**Generado por**: Protocolo PMAI (Mapeo Arquitectónico Incremental)

