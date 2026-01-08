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
        add_filter('wp_mail_smtp_custom_options', [$this, 'configureHostname']);
    }

    /**
     * Configure SMTP hostname for WP Mail SMTP plugin
     */
    public function configureHostname($phpmailer)
    {
        // Only apply to SMTP mailer
        if ($phpmailer->Mailer === 'smtp') {
            // Set proper hostname for HELO command
            $phpmailer->Hostname = 'detodo24.com';
        }
        
        return $phpmailer;
    }
}