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
        add_action('phpmailer_init', [$this, 'configureHostname'], 999);
    }

    /**
     * Configure SMTP hostname for WP Mail SMTP plugin
     */
    public function configureHostname($phpmailer)
    {
        if ($phpmailer->Mailer === 'smtp') {
            $phpmailer->Hostname = 'detodo24.com';
        }
    }
}