<?php
/**
 * config/config.production.example.php — TEMPLATE for the live server
 * ------------------------------------------------------------------
 * 1. Make a copy named  config.production.php  (same folder) ON THE SERVER ONLY.
 * 2. Replace every value in CAPITALS with the details shown in your
 *    InfinityFree client area → your hosting account → "MySQL Databases"
 *    and "MySQL Details" / account password.
 * 3. Never put the real password in this example file.
 *
 * Optional: if your host lets you upload files OUTSIDE htdocs, you may save
 * the copy as  ers-config.php  one level above the project folder instead
 * (next to htdocs). config/config.php looks there first.
 *
 * This file only RETURNS an array — opening it in a browser prints nothing.
 */
return [
    // "MySQL Host Name", e.g. sqlNNN.infinityfree.com — NOT "localhost" on InfinityFree
    'db_host'  => 'MYSQL_HOST_NAME',
    // Full database name including the prefix, e.g. if0_NNNNNNNN_events
    'db_name'  => 'MYSQL_DATABASE_NAME',
    // "MySQL User Name", e.g. if0_NNNNNNNN
    'db_user'  => 'MYSQL_USER_NAME',
    // Your hosting account (vPanel) password — shown in the client area, not your InfinityFree login password
    'db_pass'  => 'MYSQL_PASSWORD',

    // '/' when the project's files are directly inside htdocs.
    // '/folder-name/' if you uploaded them into htdocs/folder-name instead.
    'base_url' => '/',

    // Keep false on the live site: visitors never see error details.
    'debug'    => false,
];
