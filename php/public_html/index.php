<?php
declare(strict_types=1);

/**
 * The only file the web server executes directly.
 *
 * Application code, templates and content all live in ../leo-app, above the web
 * root, so none of it can be fetched over HTTP even if PHP stops running.
 */

$app = require __DIR__ . '/../leo-app/boot.php';
$app->run();
