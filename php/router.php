<?php
/**
 * Development only, for `php -S localhost:8000 -t public_html router.php`.
 *
 * Returning false hands the request back to the built-in server, which serves
 * it from the document root — so the server must be started with
 * `-t public_html` for static assets to resolve. Apache on the real host uses
 * public_html/.htaccess instead and never loads this file.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && is_file(__DIR__ . '/public_html' . $path)) {
    return false;
}

require __DIR__ . '/public_html/index.php';
