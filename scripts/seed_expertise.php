<?php
/**
 * Seeding script to randomly assign expertise to auditors
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

if (!defined('AUDITOR_EXPERTISE')) {
    echo "Error: AUDITOR_EXPERTISE constant not defined. Make sure config.php is updated.\n";
    exit(1);
}

$expertise_list = AUDITOR_EXPERTISE;

$stmt = $db->prepare("SELECT id, name FROM users WHERE role = 'auditor'");
$stmt->execute();
$auditors = $stmt->fetchAll();

echo "Seeding expertise for " . count($auditors) . " auditors...\n";

foreach ($auditors as $auditor) {
    // Randomly pick 1 to 4 expertise areas
    $count = rand(1, 4);
    $shuffled = $expertise_list;
    shuffle($shuffled);
    $selected = array_slice($shuffled, 0, $count);
    $expertise_str = implode(',', $selected);
    
    $updateStmt = $db->prepare("UPDATE users SET expertise = ? WHERE id = ?");
    $updateStmt->execute([$expertise_str, $auditor['id']]);
    
    echo "Updated [ID: {$auditor['id']}] {$auditor['name']}: {$expertise_str}\n";
}

echo "\nDone!\n";
