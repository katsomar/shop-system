<?php
/**
 * Application Entry Router
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: /shop-system/dashboard/index.php");
} else {
    header("Location: /shop-system/authentication/login.php");
}
exit();
