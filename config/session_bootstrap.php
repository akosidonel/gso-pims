<?php

if (!function_exists('gso_secure_session_options')) {
    function gso_secure_session_options()
    {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
        );

        return [
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Strict',
            'path' => '/',
        ];
    }
}

if (!function_exists('gso_start_secure_session')) {
    function gso_start_secure_session()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params(gso_secure_session_options());
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}

