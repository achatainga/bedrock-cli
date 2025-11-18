# Bedrock CLI Plugin

MU-Plugin for bedrock-cli operations including REST API, logging system, and admin interface.

## Features

- **REST API**: Fast plugin activation endpoints
- **Logging System**: Comprehensive logging with rotation
- **Admin Interface**: Log viewer with filters and search
- **Database Validation**: Safe execution only when WordPress is ready

## Installation

This plugin is automatically installed by `bedrock-cli` when running:

```bash
bedrock install:mu-plugin
```

## Structure

```
bedrock-cli-plugin/
├── bedrock-cli-plugin.php  # Entry point
├── composer.json           # PSR-4 autoload
├── src/
│   ├── Core/              # Core plugin class
│   ├── API/               # REST API controllers
│   ├── Admin/             # Admin menu and pages
│   └── Utils/             # Logger and utilities
├── assets/
│   ├── css/               # Admin styles
│   └── js/                # Admin scripts
└── logs/                  # Log files (protected)
```

## Requirements

- PHP >= 7.4
- WordPress with initialized database
- Bedrock project structure

## Security

- Validates WordPress is loaded before execution
- Checks database tables exist before queries
- Protects log directory with .htaccess
- Token-based authentication for REST API

## Version

1.0.0
