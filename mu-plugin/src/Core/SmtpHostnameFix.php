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
        $phpmailer->Hostname = 'detodo24.com';
        $phpmailer->Helo = 'detodo24.com';
    }
}