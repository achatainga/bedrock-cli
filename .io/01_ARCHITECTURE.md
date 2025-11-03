# ARCHITECTURE - bedrock-cli

## Estructura del Proyecto

```
bedrock-cli/
├── bin/
│   └── bedrock              # Entry point PHP
├── src/
│   ├── Application.php      # Symfony Console Application
│   ├── Commands/            # Comandos CLI
│   │   ├── MainMenuCommand.php
│   │   ├── SetupCommand.php
│   │   ├── DockerCommand.php
│   │   ├── DatabaseCommand.php
│   │   ├── InstallCommand.php
│   │   ├── PluginsCommand.php
│   │   ├── ThemesCommand.php
│   │   ├── UpdateCommand.php
│   │   └── BackupCommand.php
│   └── Services/            # Lógica de negocio
│       ├── DockerService.php
│       └── WpCliService.php
├── .io/                     # Memoria persistente (gitignored)
├── composer.json
├── bedrock.bat              # Windows wrapper
└── README.md
```

## Flujo de Ejecución

```
Usuario ejecuta: bedrock
    ↓
bedrock.bat (Windows) → bin/bedrock (PHP)
    ↓
Application.php carga comandos
    ↓
MainMenuCommand (default) muestra menú
    ↓
Usuario selecciona opción → Comando específico
    ↓
Comando usa Service para ejecutar lógica
    ↓
Service ejecuta docker-compose o wp-cli
    ↓
Output al usuario con colores
```

## Capas de Abstracción

### Capa 1: Entry Points
- `bin/bedrock`: Script PHP ejecutable
- `bedrock.bat`: Wrapper Windows

### Capa 2: Application
- `Application.php`: Registra comandos, configura Symfony Console

### Capa 3: Commands (UI Layer)
- Manejo de input/output
- Menús interactivos
- Validación de opciones
- Confirmaciones

### Capa 4: Services (Business Logic)
- Ejecución de docker-compose
- Ejecución de wp-cli
- Manejo de procesos
- Gestión de archivos

### Capa 5: External Tools
- Docker/docker-compose
- WP-CLI
- Sistema de archivos

## Dependencias Externas

### Requeridas en Runtime
- PHP 8.1+
- Composer
- Docker + docker-compose
- WP-CLI (instalado en contenedor o host)

### Dependencias Composer
- symfony/console: Interfaz CLI
- symfony/process: Ejecución de comandos
- symfony/filesystem: Operaciones de archivos
- symfony/yaml: Parsing de docker-compose.yml

## Patrones de Diseño

### Command Pattern
Cada comando es una clase que extiende `Command` de Symfony.

### Service Layer
Lógica de negocio separada en servicios reutilizables.

### Dependency Injection
Servicios inyectados en comandos cuando sea necesario.

### Template Method
Menús siguen estructura común: mostrar → input → validar → ejecutar → loop.

## Integración con Bedrock

```
Proyecto Bedrock/
├── web/
├── config/
├── vendor/
│   └── achatainga/
│       └── bedrock-cli/     # Este paquete
├── docker-compose.yml       # Modificado por setup
├── .env                     # Generado por setup
└── composer.json            # Requiere bedrock-cli
```

## Flujo de Datos

### Setup Command
1. Lee plantilla .env.example
2. Genera salts desde API WordPress
3. Solicita datos al usuario (DB, URL, puerto)
4. Escribe .env
5. Actualiza docker-compose.yml (puerto)
6. Actualiza nginx/default.conf (server_name)

### Docker Command
1. Usuario selecciona acción
2. DockerService construye comando docker-compose
3. Process ejecuta con/sin TTY según necesidad
4. Output se muestra en tiempo real (si TTY) o al finalizar

### Database Command
1. Usuario selecciona operación
2. WpCliService construye comando wp db
3. Process ejecuta dentro del contenedor (docker exec)
4. Output se muestra al usuario

## Consideraciones de Seguridad

- `.env` nunca en Git
- `.io/` nunca en Git (memoria persistente local)
- Validación de inputs antes de shell execution
- Confirmaciones para operaciones destructivas
- No exponer credenciales en logs
