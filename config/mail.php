<?php

if (!function_exists('gso_mail_settings')) {
    function gso_mail_settings()
    {
        static $settings = null;
        if (is_array($settings)) {
            return $settings;
        }

        $settings = [
            'host' => trim((string)gso_secret('GSO_SMTP_HOST', 'smtp.gmail.com')),
            'port' => (int)gso_secret('GSO_SMTP_PORT', '465'),
            'username' => trim((string)gso_secret('GSO_SMTP_USERNAME', '')),
            'password' => trim((string)gso_secret('GSO_SMTP_PASSWORD', '')),
            'from_email' => trim((string)gso_secret('GSO_SMTP_FROM', '')),
            'from_name' => trim((string)gso_secret('GSO_SMTP_FROM_NAME', 'General Services Office')),
            'reply_to' => trim((string)gso_secret('GSO_SMTP_REPLY_TO', 'no-reply@localhost')),
            'encryption' => trim((string)gso_secret('GSO_SMTP_ENCRYPTION', 'smtps')),
        ];

        $localConfig = __DIR__ . '/mail.local.php';
        if (is_file($localConfig)) {
            $localSettings = require $localConfig;
            if (is_array($localSettings)) {
                $settings = array_merge($settings, array_filter($localSettings, 'is_scalar'));
            }
        }

        if ($settings['from_email'] === '') {
            $settings['from_email'] = $settings['username'];
        }
        if ($settings['port'] <= 0) {
            $settings['port'] = 465;
        }

        return $settings;
    }
}

