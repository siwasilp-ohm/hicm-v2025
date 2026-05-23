<?php
/**
 * HICM V2025 Assessment System - Index
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
} else {
    redirect(getBaseUrl() . '/pages/login.php');
}
?>
