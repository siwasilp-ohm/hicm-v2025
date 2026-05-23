<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->getConnection()->query("SELECT username, name FROM users WHERE role = 'auditor'");
$users = $stmt->fetchAll();
file_put_contents(__DIR__ . '/auditor_list.txt', print_r($users, true));
echo "Auditors found: " . count($users) . "\n";
?>
