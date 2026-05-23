<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB()->getConnection();
$rows = $db->query("SELECT id, role, title FROM user_manual WHERE is_active=1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['role'] . ' | ' . $r['title'] . PHP_EOL;
}
