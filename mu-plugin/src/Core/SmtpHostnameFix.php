<?php

namespace BedrockCli\Plugin\Core;

/**
 * SMTP Hostname Fix
 * 
 * Configures proper hostname for SMTP HELO command to avoid
 * "Invalid HELO name" errors from mail servers.
 */
class SmtpHostnameFix
{
    public function __construct()
    {
        add_action('phpmailer_init', [$this, 'configureHostname'], 1);
    }

    public function configureHostname($phpmailer)
    {
        // Force hostname for HELO command
        $phpmailer->Hostname = 'detodo24.com';
        // Override the method that generates HELO
        $phpmailer->Helo = 'detodo24.com';
        // Set server hostname
        if (method_exists($phpmailer, 'serverHostname')) {
            $phpmailer->serverHostname('detodo24.com');
        }
    }
}