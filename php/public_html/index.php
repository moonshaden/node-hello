<?php
declare(strict_types=1);

/**
 * The only file the web server executes directly.
 *
 * leo-app normally sits beside public_html, above the web root, so none of the
 * application code or content can be fetched over HTTP. Extracting the archive
 * one level too deep is an easy mistake in a File Manager, though, so the
 * in-web-root location is accepted as a fallback — a working site with a
 * .htaccess denying access beats a fatal error and a blank page.
 */

$candidates = [
    __DIR__ . '/../leo-app/boot.php',   // preferred: above the web root
    __DIR__ . '/leo-app/boot.php',      // fallback: extracted inside public_html
];

$boot = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $boot = $candidate;
        break;
    }
}

if ($boot === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "The leo-app folder could not be found.\n\n";
    echo "It should sit next to public_html:\n";
    echo "  /home/<account>/leo-app/\n";
    echo "  /home/<account>/public_html/index.php\n\n";
    echo "Looked in:\n";
    foreach ($candidates as $candidate) {
        echo '  ' . $candidate . "\n";
    }
    exit;
}

$app = require $boot;
$app->run();
