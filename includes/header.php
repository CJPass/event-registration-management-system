<?php
/**
 * includes/header.php
 * ------------------------------------------------------------------
 * Top part of every page: <head>, Bootstrap CSS, our own CSS,
 * the navigation bar, and the opening <main> tag.
 *
 * Usage (set $pageTitle BEFORE including this file):
 *     $pageTitle = 'Home';
 *     require_once __DIR__ . '/includes/header.php';
 */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Makes the layout responsive on phones and tablets -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>

    <!-- Inter font (falls back to the system font if offline) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS (from CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Our own styles (loaded last so they can override Bootstrap) -->
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<?php require __DIR__ . '/navbar.php'; ?>

<!-- flex-grow-1 pushes the footer to the bottom on short pages -->
<main class="flex-grow-1 py-4">
    <div class="container">
        <?php show_flash(); ?>
