<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$conn = $db->getConnection();

// Add results_announced_at column
$conn->exec("ALTER TABLE assessment_periods ADD COLUMN results_announced_at DATETIME DEFAULT NULL AFTER results_announced");
echo "Column results_announced_at added.\n";

// Backfill: if results_announced = 1 already, set results_announced_at = NOW
$conn->exec("UPDATE assessment_periods SET results_announced_at = NOW() WHERE results_announced = 1 AND results_announced_at IS NULL");
echo "Backfilled existing announced periods.\n";
