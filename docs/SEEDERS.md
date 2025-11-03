# Seeders Guide

## Overview

Seeders are PHP classes that populate WordPress with initial data. The bedrock-cli seeder system is **hybrid**, supporting both:

1. **Laravel Acorn** (if installed): Native Laravel seeder execution
2. **WP-CLI fallback**: `wp eval-file` for projects without Acorn

This makes seeders portable across different Bedrock configurations.

## Seeder Structure

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // WordPress configuration
        update_option('blogname', 'My Site');
        update_option('blogdescription', 'Just another WordPress site');
        update_option('timezone_string', 'America/New_York');
        update_option('permalink_structure', '/%postname%/');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
```

## Available Seeders

### 1. DatabaseSeeder

**Purpose**: Master seeder that calls all other seeders

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            WooCommerceSeeder::class,
            ThemeSeeder::class,
            PluginsSeeder::class,
            ProductsSeeder::class,
        ]);
    }
}
```

### 2. CoreSeeder

**Purpose**: WordPress core configuration

**Configures**:
- Site title and tagline
- Timezone
- Permalink structure
- Date/time formats
- Default category and post format
- Discussion settings
- Media settings

**Example**:
```php
public function run(): void
{
    update_option('blogname', 'My Store');
    update_option('blogdescription', 'E-commerce site');
    update_option('timezone_string', 'America/New_York');
    update_option('permalink_structure', '/%postname%/');
    update_option('date_format', 'F j, Y');
    update_option('time_format', 'g:i a');
    
    flush_rewrite_rules();
}
```

### 3. WooCommerceSeeder

**Purpose**: WooCommerce configuration

**Configures**:
- Store address and currency
- Payment gateways
- Shipping zones and methods
- Tax settings
- Product settings
- Checkout options

**Example**:
```php
public function run(): void
{
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Store settings
    update_option('woocommerce_store_address', '123 Main St');
    update_option('woocommerce_store_city', 'New York');
    update_option('woocommerce_default_country', 'US:NY');
    update_option('woocommerce_currency', 'USD');
    
    // Enable COD
    update_option('woocommerce_cod_settings', [
        'enabled' => 'yes',
        'title' => 'Cash on delivery',
    ]);
    
    // Create shipping zone
    $zone = new \WC_Shipping_Zone();
    $zone->set_zone_name('US');
    $zone->add_location('US', 'country');
    $zone->save();
}
```

### 4. ThemeSeeder

**Purpose**: Theme activation and configuration

**Configures**:
- Theme activation
- Theme mods
- Custom logo
- Navigation menus
- Widget areas

**Example**:
```php
public function run(): void
{
    $theme = 'storefront';
    
    // Activate theme
    switch_theme($theme);
    
    // Set theme mods
    set_theme_mod('custom_logo', 0);
    set_theme_mod('header_textcolor', '000000');
    
    // Create menu
    $menu_id = wp_create_nav_menu('Main Menu');
    set_theme_mod('nav_menu_locations', [
        'primary' => $menu_id
    ]);
}
```

### 5. PluginsSeeder

**Purpose**: Plugin activation and license configuration

**Configures**:
- Plugin activation
- License keys from .env
- Plugin-specific settings

**Example**:
```php
public function run(): void
{
    $plugins = [
        'woocommerce/woocommerce.php',
        'wordpress-seo/wp-seo.php',
    ];
    
    foreach ($plugins as $plugin) {
        if (!is_plugin_active($plugin)) {
            activate_plugin($plugin);
        }
    }
    
    // Configure licenses
    if (defined('ACF_PRO_LICENSE')) {
        update_option('acf_pro_license', ACF_PRO_LICENSE);
    }
    
    if (defined('GF_LICENSE')) {
        update_option('rg_gforms_key', GF_LICENSE);
    }
}
```

### 6. ProductsSeeder

**Purpose**: Generate fake WooCommerce products

**Generates**:
- Simple products
- Variable products
- Product categories
- Product tags
- Product images
- Stock management

**Example**:
```php
public function run(): void
{
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    $count = 50; // From blueprint
    
    for ($i = 1; $i <= $count; $i++) {
        $product = new \WC_Product_Simple();
        $product->set_name("Product {$i}");
        $product->set_regular_price(rand(10, 100));
        $product->set_description("Description for product {$i}");
        $product->set_short_description("Short description {$i}");
        $product->set_stock_quantity(rand(0, 100));
        $product->set_manage_stock(true);
        $product->save();
    }
}
```

## Execution Methods

### Method 1: Acorn (Preferred)

If Roots Acorn is installed:

```bash
wp acorn db:seed
wp acorn db:seed --class=CoreSeeder
```

### Method 2: WP-CLI Fallback

If Acorn is not available:

```bash
wp eval-file database/seeders/CoreSeeder.php
```

The `init` command automatically detects which method to use.

## Using Seeders

### Via Init Command

```bash
# Execute all seeders from blueprint
bedrock init --env=production

# Skip seeders
bedrock init --env=staging --skip-seeders
```

### Manually

```bash
# With Acorn
wp acorn db:seed

# Specific seeder
wp acorn db:seed --class=CoreSeeder

# Without Acorn
wp eval-file database/seeders/DatabaseSeeder.php
```

## Creating Custom Seeders

### 1. Create Seeder File

```bash
touch database/seeders/CustomSeeder.php
```

### 2. Implement Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CustomSeeder extends Seeder
{
    public function run(): void
    {
        // Your custom logic
        $this->createPages();
        $this->createPosts();
        $this->createUsers();
    }
    
    private function createPages(): void
    {
        $pages = [
            'About' => 'About us content',
            'Contact' => 'Contact form',
        ];
        
        foreach ($pages as $title => $content) {
            wp_insert_post([
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
        }
    }
    
    private function createPosts(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            wp_insert_post([
                'post_title' => "Post {$i}",
                'post_content' => "Content for post {$i}",
                'post_status' => 'publish',
                'post_type' => 'post',
            ]);
        }
    }
    
    private function createUsers(): void
    {
        $users = [
            ['username' => 'editor', 'role' => 'editor'],
            ['username' => 'author', 'role' => 'author'],
        ];
        
        foreach ($users as $user) {
            if (!username_exists($user['username'])) {
                wp_create_user(
                    $user['username'],
                    wp_generate_password(),
                    $user['username'] . '@example.com'
                );
                
                $user_id = username_exists($user['username']);
                $wp_user = new \WP_User($user_id);
                $wp_user->set_role($user['role']);
            }
        }
    }
}
```

### 3. Add to Blueprint

```json
{
  "seeders": [
    "CoreSeeder",
    "CustomSeeder",
    "ThemeSeeder"
  ]
}
```

### 4. Execute

```bash
bedrock init --env=development
```

## Advanced Patterns

### Conditional Seeding

```php
public function run(): void
{
    // Only in development
    if (wp_get_environment_type() === 'development') {
        $this->createFakeData();
    }
    
    // Only if WooCommerce exists
    if (class_exists('WooCommerce')) {
        $this->configureWooCommerce();
    }
    
    // Only if Acorn exists
    if (function_exists('app')) {
        $this->useAcornFeatures();
    }
}
```

### Using Faker

```php
use Faker\Factory as Faker;

public function run(): void
{
    $faker = Faker::create();
    
    for ($i = 0; $i < 50; $i++) {
        wp_insert_post([
            'post_title' => $faker->sentence,
            'post_content' => $faker->paragraphs(3, true),
            'post_status' => 'publish',
        ]);
    }
}
```

### Database Transactions

```php
public function run(): void
{
    global $wpdb;
    
    $wpdb->query('START TRANSACTION');
    
    try {
        $this->createData();
        $wpdb->query('COMMIT');
    } catch (\Exception $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}
```

### Idempotent Seeders

```php
public function run(): void
{
    // Check if already seeded
    if (get_option('custom_seeder_run')) {
        return;
    }
    
    $this->createData();
    
    // Mark as seeded
    update_option('custom_seeder_run', true);
}
```

## Best Practices

1. **Idempotency**: Make seeders safe to run multiple times
2. **Conditional Logic**: Check for plugin/theme existence
3. **Error Handling**: Use try-catch for critical operations
4. **Logging**: Output progress for long-running seeders
5. **Environment Awareness**: Different data for different environments
6. **Performance**: Batch operations when possible
7. **Cleanup**: Provide a way to undo seeding if needed

## Troubleshooting

### Seeder Not Found

```bash
# Verify file exists
ls database/seeders/CoreSeeder.php

# Check namespace
grep "namespace" database/seeders/CoreSeeder.php
```

### Acorn Not Detected

```bash
# Check Acorn installation
wp acorn --version

# Use fallback
wp eval-file database/seeders/CoreSeeder.php
```

### Permission Errors

```bash
# Fix permissions
chmod +x database/seeders/*.php
```

### Memory Limit

For large seeders, increase PHP memory:

```php
ini_set('memory_limit', '512M');
```

Or in `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
```

## Integration with Blueprints

Seeders are defined in blueprints:

```json
{
  "seeders": [
    "CoreSeeder",
    "WooCommerceSeeder",
    "ThemeSeeder",
    "PluginsSeeder",
    "ProductsSeeder"
  ],
  "faker_products": 50
}
```

The `init` command reads the blueprint and executes seeders in order.

## See Also

- [Profiles Guide](PROFILES.md)
- [Blueprints Guide](BLUEPRINTS.md)
- [README](../README.md)
