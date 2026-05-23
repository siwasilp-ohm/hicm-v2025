<?php
/**
 * HICM V2025 Assessment System - Logout
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logout();
redirect(getBaseUrl() . '/pages/login.php');
?>
