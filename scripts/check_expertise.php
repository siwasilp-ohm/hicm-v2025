<?php
/**
 * Check current expertise/industry_type data
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

echo "=== ข้อมูล expertise เดิมของ auditors ===\n";
$stmt = $db->prepare("SELECT id, username, name, expertise FROM users WHERE role = 'auditor' AND expertise IS NOT NULL AND expertise != '' LIMIT 5");
$stmt->execute();
$users = $stmt->fetchAll();
foreach($users as $u) {
    echo $u['id'] . ' - ' . $u['name'] . ': ' . $u['expertise'] . "\n";
}

echo "\n=== ข้อมูล industry_type เดิมของ companies ===\n";
$stmt = $db->prepare("SELECT id, company_name, industry_type FROM companies WHERE industry_type IS NOT NULL AND industry_type != '' LIMIT 5");
$stmt->execute();
$companies = $stmt->fetchAll();
foreach($companies as $c) {
    echo $c['id'] . ' - ' . $c['company_name'] . ': ' . $c['industry_type'] . "\n";
}

// Count
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'auditor'");
$stmt->execute();
echo "\nจำนวน auditors ทั้งหมด: " . $stmt->fetch()['cnt'] . "\n";

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM companies");
$stmt->execute();
echo "จำนวน companies ทั้งหมด: " . $stmt->fetch()['cnt'] . "\n";
