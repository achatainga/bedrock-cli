# Profiles Guide

## Overview

Profiles are reusable project templates that define:
- Public plugins (from wpackagist.org)
- Premium plugins (from private Git repositories)
- Custom plugins (local development)
- Theme configuration
- Composer repositories
- Blueprint configurations per environment

## Profile Structure

```json
{
  "name": "ecommerce",
  "description": "E-commerce profile with WooCommerce",
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/company/premium-plugins.git"
    },
    {
      "type": "path",
      "url": "../custom-plugins",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "wpackagist-plugin/woocommerce": "*",
    "wpackagist-plugin/woocommerce-gateway-stripe": "*"
  },
  "plugins": {
    "public": ["woocommerce", "woocommerce-gateway-stripe"],
    "premium": ["acf-pro", "gravityforms"],
    "custom": ["my-custom-plugin"]
  },
  "theme": {
    "name": "storefront",
    "type": "public",
    "license_env": null
  },
  "blueprints": {
    "production": {
      "snapshot": null,
      "seeders": ["CoreSeeder", "WooCommerceSeeder", "ThemeSeeder", "PluginsSeeder"],
      "users": { "admin": "administrator" }
    },
    "staging": {
      "snapshot": null,
      "seeders": ["CoreSeeder", "WooCommerceSeeder", "ThemeSeeder", "PluginsSeeder", "ProductsSeeder"],
      "faker_products": 50
    },
    "development": {
      "snapshot": null,
      "seeders": ["CoreSeeder", "WooCommerceSeeder", "ThemeSeeder"],
      "users": { "admin": "administrator", "dev": "administrator" }
    }
  }
}
```

## Creating Profiles

### Interactive Wizard

```bash
bedrock profile:create my-profile
```

The wizard will guide you through:

1. **Description**: Brief description of the profile
2. **Public Plugins**: 
   - Interactive search on WordPress.org
   - Multiple searches with multi-select
   - Or manual comma-separated list
3. **Premium Plugins**:
   - Git repository URL (optional)
4. **Custom Plugins**:
   - Local path to custom plugins
   - Automatic detection via WordPress headers
5. **Theme**:
   - Theme name
   - Type (public/premium)
   - License environment variable (if premium)

### Interactive Plugin Search

When creating a profile, you can search WordPress.org interactively:

```
🔌 PLUGINS PÚBLICOS (desde wpackagist.org)
¿Buscar plugins interactivamente? (Y/n): y

🔍 Buscar plugin (Enter para terminar): woocommerce

┌─────┬──────────────────────────────────┬──────────┬────────────┐
│ #   │ Nombre                           │ Slug     │ Descargas  │
├─────┼──────────────────────────────────┼──────────┼────────────┤
│ [1] │ WooCommerce                      │ woocomm… │ 150M       │
│ [2] │ WooCommerce Stripe Gateway       │ woocomm… │ 10M        │
│ [3] │ WooCommerce PayPal Payments      │ woocomm… │ 5M         │
└─────┴──────────────────────────────────┴──────────┴────────────┘

Seleccionar plugins (ej: 1,2,3 o Enter para todos): 1,2

✅ Agregados: woocommerce, woocommerce-gateway-stripe

🔍 Buscar plugin (Enter para terminar): [Enter]
```

### Automatic Custom Plugin Detection

When you provide a path to custom plugins, the system automatically scans for valid WordPress plugins:

```
🛠️  PLUGINS CUSTOM (en desarrollo)
¿Tienes plugins custom en otro proyecto? (Y/n): y
Path absoluto a los plugins custom: C:\projects\my-plugins

Plugins detectados:
  • My Custom Plugin (my-custom-plugin) - v1.0.0
  • Another Plugin (another-plugin) - v2.1.0

¿Agregar todos estos plugins? (Y/n): y
```

## Managing Profiles

### List Profiles

```bash
bedrock profile:list
```

Output:
```
┌──────────────┬─────────────────────────────────┬─────────┬─────────┐
│ Nombre       │ Descripción                     │ Plugins │ Tema    │
├──────────────┼─────────────────────────────────┼─────────┼─────────┤
│ default      │ Profile básico de Bedrock       │ 2       │ twenty… │
│ ecommerce    │ E-commerce con WooCommerce      │ 5       │ storefo…│
│ blog         │ Blog simple con SEO             │ 3       │ twenty… │
└──────────────┴─────────────────────────────────┴─────────┴─────────┘
```

### Show Profile Details

```bash
bedrock profile:show ecommerce
```

Output: Formatted JSON with syntax highlighting

### Edit Profile

```bash
bedrock profile:edit ecommerce
```

Opens profile in system editor (respects `EDITOR` environment variable, falls back to notepad/nano).
Validates JSON after editing.

### Delete Profile

```bash
bedrock profile:delete ecommerce
```

Prompts for confirmation before deletion.

### Export Profile from Project

```bash
cd my-existing-project
bedrock profile:export my-exported-profile
```

Reads `.bedrock/profile.json` from current project and saves to `~/.bedrock-cli/profiles/`.

### Apply Profile to Existing Project

```bash
cd another-project
bedrock profile:apply ecommerce
```

- Regenerates `composer.json` from profile
- Updates `.bedrock/profile.json`
- Suggests running `composer update`

## Using Profiles

### Create New Project

```bash
bedrock new my-site --profile=ecommerce
```

This will:
1. Create Bedrock project structure
2. Generate `composer.json` from profile
3. Copy blueprints to `blueprints/`
4. Copy seeders to `database/seeders/`
5. Save profile to `.bedrock/profile.json`

### Initialize Environment

```bash
cd my-site
bedrock init --env=production
```

Reads blueprint from `blueprints/production.json` and:
1. Imports database (if specified)
2. Executes seeders
3. Activates plugins
4. Configures licenses
5. Activates theme
6. Configures WordPress options

## Profile Storage

### Global Profiles

Stored in:
- **Linux/Mac**: `~/.bedrock-cli/profiles/`
- **Windows**: `%USERPROFILE%\.bedrock-cli\profiles\`

### Project Profile

Each project stores its profile in `.bedrock/profile.json` for portability.

## Best Practices

1. **Version Control**: Add `.bedrock/profile.json` to Git for team consistency
2. **Naming**: Use descriptive names (e.g., `ecommerce-b2b`, `blog-multilang`)
3. **Documentation**: Add detailed descriptions to profiles
4. **Blueprints**: Customize blueprints per environment needs
5. **Premium Plugins**: Use environment variables for licenses
6. **Custom Plugins**: Use symlinks for active development

## Advanced Examples

### Multi-site Profile

```json
{
  "name": "multisite",
  "description": "WordPress Multisite configuration",
  "plugins": {
    "public": ["wordpress-seo", "wp-mail-smtp"],
    "premium": ["acf-pro"],
    "custom": []
  },
  "blueprints": {
    "production": {
      "seeders": ["CoreSeeder", "NetworkSeeder", "SitesSeeder"]
    }
  }
}
```

### Headless WordPress Profile

```json
{
  "name": "headless",
  "description": "Headless WordPress with WPGraphQL",
  "plugins": {
    "public": ["wp-graphql", "wp-graphql-acf", "jwt-authentication-for-wp-rest-api"],
    "premium": ["acf-pro"],
    "custom": []
  },
  "theme": {
    "name": "twentytwentyfour",
    "type": "public"
  }
}
```

### Agency Profile with Premium Theme

```json
{
  "name": "agency-premium",
  "description": "Agency site with Motta theme",
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/agency/premium-themes.git"
    }
  ],
  "plugins": {
    "public": ["contact-form-7", "wordpress-seo"],
    "premium": ["acf-pro", "gravityforms"],
    "custom": []
  },
  "theme": {
    "name": "motta",
    "type": "premium",
    "license_env": "MOTTA_LICENSE"
  }
}
```

## Troubleshooting

### Profile Not Found

```bash
bedrock profile:list
```

Verify profile exists in `~/.bedrock-cli/profiles/`

### Invalid JSON

```bash
bedrock profile:edit my-profile
```

Edit and save. CLI validates JSON automatically.

### Plugin Not Found

Verify plugin slug on WordPress.org:
```bash
bedrock plugin:info <slug>
```

### Custom Plugins Not Detected

Ensure plugins have valid WordPress headers:
```php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Description: Plugin description
 * Author: Author Name
 */
```

## See Also

- [Blueprints Guide](BLUEPRINTS.md)
- [Seeders Guide](SEEDERS.md)
- [README](../README.md)
