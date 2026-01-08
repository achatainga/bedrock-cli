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

// Debug log
error_log("[Loader] Bedrock CLI Plugin Loader executing", 3, "/home/qqgi77wff00i/apps/bedrock-docker/debug-file-mod.log");

// Load the actual plugin from subdirectory
$plugin_file = __DIR__ . '/bedrock-cli-plugin/bedrock-cli-plugin.php';

if (file_exists($plugin_file)) {
    error_log("[Loader] Loading plugin from: $plugin_file", 3, "/home/qqgi77wff00i/apps/bedrock-docker/debug-file-mod.log");
    require_once $plugin_file;
} else {
    error_log("[Loader] Plugin file not found: $plugin_file", 3, "/home/qqgi77wff00i/apps/bedrock-docker/debug-file-mod.log");
}