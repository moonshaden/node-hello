<?php
/**
 * Copy this file to config.php (same folder) and edit it.
 *
 * config.php sits outside public_html, so these values are never served even
 * if PHP is misconfigured. Nothing else needs changing to run the site.
 */
return [
    // Required. The password for /admin. Leave empty to switch the admin off.
    'admin_password' => 'change-this-to-something-long',

    // Optional. Change it to sign every current admin out without changing the
    // password. Any long random string works.
    'session_secret' => '',
];
