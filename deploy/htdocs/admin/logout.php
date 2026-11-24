<?php
/**
 * admin/logout.php — Log out (POST only, with CSRF token)
 * ------------------------------------------------------------------
 * POST is required so another website cannot log you out with a link
 * or an <img> tag. Clears the session and issues a new session ID.
 */
require_once __DIR__ . '/../config/database.php';

require_post('admin/index.php');
verify_csrf('admin/index.php');

logout_admin();
set_flash('success', 'You have been logged out.');
redirect('admin/login.php');
