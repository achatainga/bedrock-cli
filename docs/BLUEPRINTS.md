# Blueprints Guide

## Overview

Blueprints are environment-specific configurations that define how to initialize a WordPress installation. Each environment (production, staging, development) can have its own blueprint with different:

- Database snapshots
- Seeders to execute
- Users to create
- Fake data generation
- Plugin activation order
- WordPress options

## Blueprint Structure

```json
{
  "environment": "production",
  "snapshot": "production-2024-01-15.sql",
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "ThemeSeeder",
    "PluginsSeeder"
  ],
  "users": {
    "admin": "administrator"
  },
  "plugins": {
    "activate": ["woocommerce", "wordpress-seo"],
    "licenses": {
      "acf_pro_license": "ACF_PRO_LICENSE",
      "gravityforms_license": "GF_LICENSE"
    }
  },
  "theme": {
    "activate": "storefront",
    "license_env": null
  },
  "options": {
    "blogname": "My Store",
    "blogdescription": "E-commerce site",
    "permalink_structure": "/%postname%/",
    "woocommerce_store_address": "123 Main St",
    "woocommerce_currency": "USD"
  },
  "search_replace": {
    "enabled": true,
    "old_url": null,
    "new_url": null
  }
}
```

## Default Blueprints

### Production Blueprint

**Purpose**: Minimal, secure production environment

```json
{
  "environment": "production",
  "snapshot": null,
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "ThemeSeeder",
    "PluginsSeeder"
  ],
  "users": {
    "admin": "administrator"
  },
  "faker_products": 0
}
```

**Characteristics**:
- No fake data
- Single admin user
- Essential seeders only
- Production-ready configuration

### Staging Blueprint

**Purpose**: Testing environment with realistic data

```json
{
  "environment": "staging",
  "snapshot": null,
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "ThemeSeeder",
    "PluginsSeeder",
    "ProductsSeeder"
  ],
  "faker_products": 50,
  "users": {
    "admin": "administrator",
    "tester": "editor"
  }
}
```

**Characteristics**:
- 50 fake products (if WooCommerce)
- Multiple test users
- Full seeder suite
- Realistic test data

### Development Blueprint

**Purpose**: Local development with debugging tools

```json
{
  "environment": "development",
  "snapshot": null,
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "ThemeSeeder"
  ],
  "users": {
    "admin": "administrator",
    "dev": "administrator"
  },
  "faker_products": 10
}
```

**Characteristics**:
- Minimal fake data
- Multiple admin users
- Fast initialization
- Development-friendly

## Using Blueprints

### Initialize Environment

```bash
# Use blueprint from blueprints/production.json
bedrock init --env=production

# Use blueprint from blueprints/staging.json
bedrock init --env=staging

# Use blueprint from blueprints/development.json
bedrock init --env=development
```

### With Database Import

```bash
bedrock init --env=production --db-file=backup.sql
```

### Skip Specific Steps

```bash
# Skip database import
bedrock init --env=staging --skip-db

# Skip seeders
bedrock init --env=production --skip-seeders

# Skip plugin activation
bedrock init --env=development --skip-plugins
```

### Custom URLs for Search-Replace

```bash
bedrock init --env=production \
  --db-file=backup.sql \
  --old-url=https://old-site.com \
  --new-url=https://new-site.com
```

## Blueprint Variables

Blueprints support variable replacement from the profile:

```json
{
  "theme": {
    "activate": "{{THEME_NAME}}",
    "license_env": "{{THEME_LICENSE_ENV}}"
  },
  "plugins": {
    "activate": "{{PUBLIC_PLUGINS}}",
    "licenses": "{{PREMIUM_LICENSES}}"
  }
}
```

Variables are replaced when creating a project from a profile.

## Seeders in Blueprints

### Available Seeders

1. **CoreSeeder**: WordPress core configuration
   - Site title, tagline, timezone
   - Permalink structure
   - Default category/format
   - User roles

2. **WooCommerceSeeder**: WooCommerce configuration
   - Store address, currency
   - Payment gateways
   - Shipping zones
   - Tax settings

3. **ThemeSeeder**: Theme configuration
   - Theme activation
   - Theme mods
   - Custom logo
   - Menus

4. **PluginsSeeder**: Plugin activation
   - Activate plugins from profile
   - Configure licenses
   - Plugin-specific settings

5. **ProductsSeeder**: Fake products (WooCommerce)
   - Generate N fake products
   - Categories, tags
   - Images, variations
   - Stock management

### Seeder Execution Order

Seeders execute in the order defined in the blueprint:

```json
{
  "seeders": [
    "CoreSeeder",        // 1. Core WordPress
    "WooCommerceSeeder", // 2. WooCommerce setup
    "ThemeSeeder",       // 3. Theme activation
    "PluginsSeeder",     // 4. Plugin activation
    "ProductsSeeder"     // 5. Fake data
  ]
}
```

## Advanced Blueprints

### Multi-site Blueprint

```json
{
  "environment": "production",
  "multisite": true,
  "network": {
    "subdomain_install": false,
    "sites": [
      {
        "domain": "site1.example.com",
        "path": "/",
        "title": "Site 1"
      },
      {
        "domain": "site2.example.com",
        "path": "/",
        "title": "Site 2"
      }
    ]
  },
  "seeders": ["CoreSeeder", "NetworkSeeder", "SitesSeeder"]
}
```

### Headless WordPress Blueprint

```json
{
  "environment": "production",
  "headless": true,
  "seeders": ["CoreSeeder", "GraphQLSeeder"],
  "plugins": {
    "activate": ["wp-graphql", "wp-graphql-acf", "jwt-authentication-for-wp-rest-api"]
  },
  "options": {
    "show_on_front": "posts",
    "graphql_general_settings": {
      "public_introspection_enabled": "on"
    }
  }
}
```

### E-commerce with Subscriptions

```json
{
  "environment": "staging",
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "SubscriptionsSeeder",
    "ThemeSeeder",
    "PluginsSeeder",
    "ProductsSeeder"
  ],
  "faker_products": 20,
  "faker_subscriptions": 10,
  "plugins": {
    "activate": [
      "woocommerce",
      "woocommerce-subscriptions",
      "woocommerce-gateway-stripe"
    ]
  }
}
```

## Blueprint Customization

### Edit Blueprint

```bash
# Edit blueprint directly
nano blueprints/production.json

# Or use your preferred editor
code blueprints/staging.json
```

### Validate Blueprint

```bash
# Initialize with validation
bedrock init --env=production --validate
```

### Blueprint Templates

Create custom blueprint templates in `stubs/blueprints/`:

```bash
cp stubs/blueprints/production.json.stub stubs/blueprints/custom.json.stub
```

## Environment Variables

Blueprints can reference environment variables:

```json
{
  "plugins": {
    "licenses": {
      "acf_pro_license": "ACF_PRO_LICENSE",
      "gravityforms_license": "GF_LICENSE"
    }
  }
}
```

In `.env`:
```env
ACF_PRO_LICENSE=your-license-key
GF_LICENSE=your-gf-license
```

## Best Practices

1. **Separate Concerns**: Different blueprints for different environments
2. **Minimal Production**: Keep production blueprints minimal and secure
3. **Rich Staging**: Use staging for realistic testing with fake data
4. **Fast Development**: Keep development blueprints fast to initialize
4. **Version Control**: Commit blueprints to Git for team consistency
5. **Documentation**: Comment complex configurations
6. **Validation**: Test blueprints before deploying
7. **Snapshots**: Use snapshots for known-good states

## Troubleshooting

### Seeder Not Found

Verify seeder exists in `database/seeders/`:
```bash
ls database/seeders/
```

### Plugin Activation Failed

Check plugin slug:
```bash
wp plugin list
```

### License Configuration Failed

Verify environment variable:
```bash
echo $ACF_PRO_LICENSE
```

### Database Import Failed

Check SQL file path and permissions:
```bash
ls -la backup.sql
```

## Integration with Profiles

Blueprints are automatically generated from profiles:

```bash
bedrock new my-site --profile=ecommerce
```

Creates:
- `blueprints/production.json`
- `blueprints/staging.json`
- `blueprints/development.json`

All customized based on the profile's `blueprints` section.

## See Also

- [Profiles Guide](PROFILES.md)
- [Seeders Guide](SEEDERS.md)
- [README](../README.md)
