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

# Crear alias global
echo 'alias bedrock="php /c/code/bedrock-cli/bin/bedrock"' >> ~/.bashrc
source ~/.bashrc
```

## 📖 Uso

### Comandos con Menú Interactivo

```bash
# Gestión Docker (menú)
bedrock docker

# Gestión Base de Datos (menú)
bedrock db

# Gestión Plugins (menú)
bedrock plugins
```

### Comandos Directos

```bash
# Docker
bedrock docker --up
bedrock docker --down
bedrock docker --restart
bedrock docker --status

# Base de Datos
bedrock db --create
bedrock db --import=database.sql
bedrock db --export=backup.sql

# WordPress
bedrock install
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
bedrock list

# Probar comando
bedrock docker --status
```

## 📦 Como Paquete Composer

Una vez publicado, se instalará así:

```bash
composer require roots/bedrock-cli --dev

# Opción 1: Usar vendor/bin
vendor/bin/bedrock docker --up

# Opción 2: Crear alias
echo 'alias bedrock="php $(pwd)/vendor/roots/bedrock-cli/bin/bedrock"' >> ~/.bashrc
source ~/.bashrc
bedrock docker --up
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
