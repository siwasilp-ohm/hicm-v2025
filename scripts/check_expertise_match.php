<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();

echo "=== ตรวจสอบ expertise ในฐานข้อมูล ===\n\n";

$stmt = $db->prepare("SELECT id, name, expertise FROM users WHERE role = 'auditor' LIMIT 3");
$stmt->execute();
$rows = $stmt->fetchAll();

foreach($rows as $r) {
    echo "ID: " . $r['id'] . " - " . $r['name'] . "\n";
    echo "Expertise: [" . $r['expertise'] . "]\n";
    $exps = explode('|', $r['expertise']);
    echo "Split count: " . count($exps) . "\n";
    foreach($exps as $i => $e) {
        echo "  [$i]: [$e]\n";
    }
    echo "---\n";
}

echo "\n=== AUDITOR_EXPERTISE constant ===\n";
foreach(AUDITOR_EXPERTISE as $i => $exp) {
    echo "[$i]: [$exp]\n";
}
