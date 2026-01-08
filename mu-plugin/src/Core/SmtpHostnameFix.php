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
        if (property_exists($phpmailer, 'Hostname')) {
            $phpmailer->Hostname = 'detodo24.com';
        }
        if (property_exists($phpmailer, 'Helo')) {
            $phpmailer->Helo = 'detodo24.com';
        }
        // Force override for WP Mail SMTP
        add_filter('wp_mail_smtp_phpmailer_init', function($mailer) {
            $mailer->Hostname = 'detodo24.com';
            return $mailer;
        }, 999);
    }
}