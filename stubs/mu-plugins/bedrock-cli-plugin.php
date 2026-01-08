<?php
/**
 * Bedrock CLI Plugin Loader
 * 
 * This file loads the Bedrock CLI Plugin from the bedrock-cli-plugin subdirectory.
 * WordPress MU plugins must be in the root of mu-plugins/ or have a loader like this.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the actual plugin from subdirectory
$plugin_file = __DIR__ . '/bedrock-cli-plugin/bedrock-cli-plugin.php';

if (file_exists($plugin_file)) {
    require_once $plugin_file;
}