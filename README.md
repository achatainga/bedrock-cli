# Bedrock CLI

Universal CLI tool for managing Roots Bedrock WordPress projects with **Profiles**, **Blueprints**, and **Seeders**.

## 🚀 Features

### v2.0 - Unified Management System
- **🎛️ Interactive Management**: Manage plugins, themes, and dependencies with intuitive menus
- **⚡ Granular Commands**: Direct commands for scripts and CI/CD automation
- **🎨 Profiles**: Reusable project templates with plugins, themes, and configurations
- **🔍 Interactive Search**: Search WordPress.org plugins/themes (Chocolatey-style)
- **📋 Blueprints**: Environment-specific configurations (production/staging/development)
- **🌱 Seeders**: Hybrid Laravel/WordPress data seeders
- **⚡ Quick Init**: One-command environment initialization
- **🐳 Docker Support**: Built-in Docker configuration
- **🔄 Database Migration**: Import and transform existing databases

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

# Creates: composer.json, blueprints/, seeders/, .bedrock/profile.json
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

## 📚 Documentation

- **[Profiles Guide](docs/PROFILES.md)** - Complete profile system documentation
- **[Blueprints Guide](docs/BLUEPRINTS.md)** - Environment-specific configurations
- **[Seeders Guide](docs/SEEDERS.md)** - Data seeding system

## Commands Reference

### 🎛️ Unified Management (v2.0)

```bash
# Interactive menus
bedrock manage                      # Main management menu
bedrock manage:plugins              # Plugin management menu
bedrock manage:themes               # Theme management menu
bedrock manage:dependencies         # Dependency management menu

# Granular commands (for scripts/CI/CD)
bedrock add:plugin <slug> [--version] [--activate]
bedrock add:theme <slug> [--version] [--activate]
bedrock add:dependency <vendor/package> [--version] [--dev]

bedrock remove:plugin <slug>
bedrock remove:theme <slug>
bedrock remove:dependency <vendor/package>
```

### Profile Management

```bash
bedrock profile:create <name>      # Create new profile (interactive wizard)
bedrock profile:list                # List all profiles
bedrock profile:show <name>         # Show profile details
bedrock profile:edit <name>         # Edit profile in system editor
bedrock profile:delete <name>       # Delete profile
bedrock profile:export <name>       # Export profile from current project
bedrock profile:apply <name>        # Apply profile to existing project
bedrock profile:menu                # Interactive profile menu

# Granular profile editing (v2.0)
bedrock profile:add-plugin <profile> <slug> [--version]
bedrock profile:remove-plugin <profile> <slug>
bedrock profile:set-theme <profile> <slug> [--version]
bedrock profile:add-repo <profile> --type=<type> --url=<url>
```

### WordPress.org Search

```bash
bedrock plugin:search <query> [--profile=NAME]   # Search plugins
bedrock plugin:info <slug>                       # Plugin details
bedrock theme:search <query> [--profile=NAME]    # Search themes
bedrock theme:info <slug>                        # Theme details
```

### Project Creation

```bash
bedrock new <name> [OPTIONS]

Options:
  --profile=NAME       Use existing profile
  --with-acorn         Install Roots Acorn
  --with-docker        Generate Docker files
  --db-name=NAME       Database name
  --db-user=USER       Database user
  --db-pass=PASS       Database password
  --force              Overwrite if exists
```

### Environment Initialization

```bash
bedrock init [OPTIONS]

Options:
  --env=ENV            Environment: production|staging|development
  --db-file=FILE       SQL file to import
  --skip-db            Skip database import
  --skip-seeders       Skip seeders execution
  --skip-plugins       Skip plugin activation
  --old-url=URL        Old site URL (for search-replace)
  --new-url=URL        New site URL (auto-detected from .env)
```

### Database Management

```bash
bedrock migrate                    # Migrate database from SQL dump
bedrock snapshot --create          # Create database snapshot
bedrock snapshot --restore         # Restore database snapshot
bedrock db:clean                   # Clean and rename table prefixes
bedrock import:core                # Import golden-image.sql
```

### Configuration

```bash
bedrock export:config [--all]      # Export WordPress options to JSON
bedrock options:pull [--all]       # Export options to config/options/
bedrock options:push               # Import options from config/options/
```

### Plugins

```bash
bedrock plugins:order              # Apply activation order
bedrock plugins:order:builder      # Interactive order builder
bedrock plugins:order:menu         # Manage saved orders
```

### System

```bash
bedrock docker                     # Docker management
bedrock doctor                     # Check system dependencies
```

## Project Structure

```
my-site/
├── .bedrock/
│   └── profile.json              # Project profile
├── blueprints/
│   ├── production.json           # Production blueprint
│   ├── staging.json              # Staging blueprint
│   └── development.json          # Development blueprint
├── config/
│   └── plugins/                  # Plugin configurations
├── database/
│   ├── migrations/               # Database migrations
│   ├── seeders/                  # Data seeders
│   │   ├── DatabaseSeeder.php
│   │   ├── CoreSeeder.php
│   │   ├── WooCommerceSeeder.php
│   │   ├── ThemeSeeder.php
│   │   ├── PluginsSeeder.php
│   │   └── ProductsSeeder.php
│   └── snapshots/                # SQL snapshots
├── scripts/                      # Utility scripts
├── web/                          # Bedrock web root
├── docker-compose.yml            # Docker config (if --with-docker)
├── Dockerfile.web                # PHP-FPM Dockerfile
├── composer.json                 # Generated from profile
└── .env                          # Environment variables
```

## Profile Storage

Profiles are stored globally in:
- **Linux/Mac**: `~/.bedrock-cli/profiles/`
- **Windows**: `%USERPROFILE%\.bedrock-cli\profiles\`

Each project also stores its profile in `.bedrock/profile.json` for portability.

## Examples

### Create E-commerce Profile

```bash
bedrock profile:create ecommerce
# Wizard will guide you through:
# - Plugin search (woocommerce, woocommerce-gateway-stripe, etc.)
# - Premium plugins repository
# - Custom plugins path
# - Theme selection
# - Blueprint configuration
```

### Create Project from Profile

```bash
bedrock new my-shop --profile=ecommerce --with-docker
cd my-shop
bedrock init --env=staging  # Creates staging environment with fake products
```

### Export Profile from Existing Project

```bash
cd existing-project
bedrock profile:export my-existing-profile
# Profile saved to ~/.bedrock-cli/profiles/my-existing-profile.json
```

### Apply Profile to Existing Project

```bash
cd another-project
bedrock profile:apply ecommerce
# Regenerates composer.json, updates .bedrock/profile.json
composer update
```

## Requirements

- PHP 8.0+
- Composer
- WP-CLI (for `init` command)
- Docker (optional, for --with-docker)
- Git

## License

MIT

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

Created by [Alejandro Chataing](https://github.com/achatainga)
