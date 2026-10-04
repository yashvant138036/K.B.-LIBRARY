<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin_dashboard.php');
        exit;
    }
    header('Location: user_dashboard.php');
    exit;
}

header('Location: login.php');
exit;
