# Bedrock CLI - Universal CLI for Roots Bedrock

Herramienta de gestión para proyectos Roots Bedrock WordPress.

## 🚀 Instalación

### En proyecto Bedrock existente

```bash
cd tu-proyecto-bedrock
composer require roots/bedrock-cli --dev
```

### Desarrollo local

```bash
cd bedrock-cli
composer install
chmod +x bin/bedrock
```

## 📖 Uso

### Comandos con Menú Interactivo

```bash
# Gestión Docker (menú)
./bin/bedrock docker

# Gestión Base de Datos (menú)
./bin/bedrock db

# Gestión Plugins (menú)
./bin/bedrock plugins
```

### Comandos Directos

```bash
# Docker
./bin/bedrock docker --up
./bin/bedrock docker --down
./bin/bedrock docker --restart
./bin/bedrock docker --status

# Base de Datos
./bin/bedrock db --create
./bin/bedrock db --import=database.sql
./bin/bedrock db --export=backup.sql

# WordPress
./bin/bedrock install
```

## 🏗️ Arquitectura

```
bedrock-cli/
├── bin/bedrock                 # Ejecutable
├── src/
│   ├── Application.php         # App principal
│   ├── Commands/               # Comandos Symfony
│   │   ├── DockerCommand.php
│   │   ├── DatabaseCommand.php
│   │   ├── InstallCommand.php
│   │   ├── PluginsCommand.php
│   │   ├── ThemesCommand.php
│   │   ├── BackupCommand.php
│   │   └── UpdateCommand.php
│   └── Services/               # Servicios
│       ├── DockerService.php
│       └── WpCliService.php
├── config/                     # Configuración
└── composer.json
```

## 🎯 Características

- ✅ **Menús interactivos** para operaciones complejas
- ✅ **Comandos directos** para automatización
- ✅ **Autocontenido** - No depende de scripts externos
- ✅ **Symfony Console** - Framework robusto
- ✅ **Paquete Composer** - Reutilizable en cualquier Bedrock

## 🔧 Desarrollo

```bash
# Instalar dependencias
composer install

# Ejecutar
./bin/bedrock list

# Probar comando
./bin/bedrock docker --status
```

## 📦 Como Paquete Composer

Una vez publicado, se instalará así:

```bash
composer require roots/bedrock-cli --dev
vendor/bin/bedrock docker --up
```

## 🚧 Estado

- ✅ Docker (completo)
- ✅ Database (completo)
- ✅ Install (completo)
- ⏳ Plugins (esqueleto)
- ⏳ Themes (esqueleto)
- ⏳ Backup (esqueleto)
- ⏳ Update (esqueleto)

## 📝 Licencia

MIT
