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
        add_action('phpmailer_init', [$this, 'configureHostname'], PHP_INT_MAX);
    }

    public function configureHostname($phpmailer)
    {
        // Force hostname for HELO command
        $phpmailer->Hostname = 'detodo24.com';
        // Explicitly set the Helo string to prevent PHPMailer from regenerating it
        $phpmailer->Helo = 'detodo24.com';
    }
}