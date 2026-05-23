<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$count = $db->getConnection()->query("SELECT COUNT(*) FROM companies")->fetchColumn();
echo "Total companies in DB: $count\n";
?>
