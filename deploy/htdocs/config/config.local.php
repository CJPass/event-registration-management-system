<?php
/**
 * config/config.local.php — LOCAL settings (XAMPP on your PC)
 * ------------------------------------------------------------------
 * Used automatically when the site is opened at http://localhost/...
 * (config/config.php checks the address and picks this file).
 *
 * Values come from XAMPP → phpMyAdmin (http://localhost/phpmyadmin).
 * This file only RETURNS an array — opening it in a browser prints nothing.
 */
return [
    'db_host'  => 'localhost',              // XAMPP's MySQL runs on this PC
    'db_name'  => 'event_registration_db',  // database created by database/event_registration.sql
    'db_user'  => 'root',                   // XAMPP's default MySQL user
    'db_pass'  => '',                       // XAMPP's root user has no password by default

    // URL path to the project folder: http://localhost/event-registration/
    'base_url' => '/event-registration/',

    // true = show error details while developing
    'debug'    => true,
];
