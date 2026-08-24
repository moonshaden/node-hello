<?php
declare(strict_types=1);

/**
 * Bootstrap. Loaded by public_html/index.php, which is the only file the web
 * server ever executes directly — everything else lives above the web root.
 */

require __DIR__ . '/src/Schedule.php';
require __DIR__ . '/src/Store.php';
require __DIR__ . '/src/Markdown.php';
require __DIR__ . '/src/Content.php';
require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/Admin.php';
require __DIR__ . '/src/App.php';
require __DIR__ . '/src/helpers.php';

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : ['admin_password' => (string) getenv('LEO_ADMIN_PASSWORD'), 'session_secret' => ''];

return new Leo\App(
    dataFile: __DIR__ . '/data/content.json',
    viewsDir: __DIR__ . '/views',
    config: is_array($config) ? $config : []
);
