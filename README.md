# Bedrock CLI

Universal CLI tool for managing Roots Bedrock WordPress projects.

## Installation

### Global Installation (Recommended)

```bash
composer global require achatainga/bedrock-cli:dev-develop
```

Make sure `~/.composer/vendor/bin` (or `%APPDATA%\Composer\vendor\bin` on Windows) is in your PATH.

### Local Installation (Per Project)

```bash
composer require --dev achatainga/bedrock-cli
```

## Usage

### Create New Project

```bash
# Basic project
bedrock new my-site

# With Docker
bedrock new my-site --with-docker

# With Acorn (Laravel components)
bedrock new my-site --with-acorn --with-docker

# Custom database config
bedrock new my-site --with-docker --db-name=mydb --db-user=admin --db-pass=secret
```

### Migrate Existing Database

```bash
cd my-site

# Migrate database from SQL dump
bedrock migrate \
  --sql-file=dump.sql \
  --old-prefix=hp2f_ \
  --old-url=https://old-site.com \
  --default-theme

# Migrate without changing prefix
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://old-site.com \
  --new-url=http://localhost:8080

# Migrate without Acorn setup
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://old-site.com \
  --skip-acorn
```

### Manage Existing Project

```bash
cd my-site

# Database snapshots
bedrock snapshot --create --name="backup"
bedrock snapshot --restore --name="backup"

# Export/Import configuration
bedrock export:config --all
bedrock import:core

# Plugin management
bedrock plugins:order
bedrock plugins:order:builder

# Options management
bedrock options:pull --all
bedrock options:push
```

## Commands

### Project Creation
- `new <name>` - Create new Bedrock project

### Database Management
- `migrate` - Migrate database from SQL dump (import + clean + search-replace)
- `snapshot --create --name="backup"` - Create database snapshot
- `snapshot --restore --name="backup"` - Restore database snapshot
- `db:clean --old-prefix=old_ --new-prefix=wp_` - Clean and rename table prefixes
- `import:core` - Import golden-image.sql + configs

### Configuration
- `export:config [--all]` - Export WordPress options to JSON
- `options:pull [--all]` - Export options to config/options/
- `options:push` - Import options from config/options/

### Plugins
- `plugins:order` - Apply activation order
- `plugins:order:builder` - Interactive order builder
- `plugins:order:menu` - Manage saved orders

### System
- `docker` - Docker management
- `doctor` - Check system dependencies

## Options for `new` Command

- `--with-acorn` - Install Roots Acorn
- `--with-docker` - Generate Docker files
- `--db-name=NAME` - Database name (default: project_name)
- `--db-user=USER` - Database user (default: root)
- `--db-pass=PASS` - Database password (default: mysql)
- `--force` - Overwrite if exists

## Options for `migrate` Command

- `--sql-file=FILE` - Path to SQL dump file (required)
- `--old-prefix=PREFIX` - Old table prefix (default: wp_)
- `--new-prefix=PREFIX` - New table prefix (default: wp_)
- `--old-url=URL` - Old site URL (required)
- `--new-url=URL` - New site URL (auto-detected from .env)
- `--skip-acorn` - Skip Acorn configuration
- `--default-theme` - Activate twentytwentyfive theme

## Project Structure

```
my-site/
├── config/
│   └── plugins/          # Plugin configurations
├── database/
│   ├── migrations/       # Database migrations
│   ├── seeders/          # Data seeders
│   └── snapshots/        # SQL snapshots
├── scripts/              # Utility scripts
├── web/                  # Bedrock web root
├── docker-compose.yml    # Docker config (if --with-docker)
├── Dockerfile.web        # PHP-FPM Dockerfile
└── .env                  # Environment variables
```

## Requirements

- PHP 8.0+
- Composer
- Docker (optional, for --with-docker)
- Git

## License

MIT
