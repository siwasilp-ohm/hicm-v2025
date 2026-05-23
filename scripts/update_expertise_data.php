<?php
/**
 * Script to clear old expertise/industry_type data and update with new 8 categories
 * ล้างข้อมูลเก่าและสุ่มใส่ค่าใหม่ตาม 8 หมวดหมู่อุตสาหกรรม
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();

// New 8 industry categories
$newCategories = AUDITOR_EXPERTISE;

echo "=== ล้างและอัปเดตข้อมูล expertise/industry_type ===\n\n";
echo "หมวดหมู่ใหม่ 8 ประเภท:\n";
foreach($newCategories as $i => $cat) {
    echo ($i+1) . ". $cat\n";
}

// Clear and update auditors
echo "\n=== อัปเดต auditors ===\n";
$stmt = $db->prepare("SELECT id, name, expertise FROM users WHERE role = 'auditor'");
$stmt->execute();
$auditors = $stmt->fetchAll();

foreach($auditors as $auditor) {
    // Random 1-3 expertise from new categories
    $numExpertise = rand(1, 3);
    $shuffled = $newCategories;
    shuffle($shuffled);
    $selectedExpertise = array_slice($shuffled, 0, $numExpertise);
    $expertiseStr = implode(',', $selectedExpertise);
    
    $updateStmt = $db->prepare("UPDATE users SET expertise = ? WHERE id = ?");
    $updateStmt->execute([$expertiseStr, $auditor['id']]);
    
    echo "Updated ID {$auditor['id']} ({$auditor['name']}): {$numExpertise} expertise(s)\n";
}

// Clear and update companies
echo "\n=== อัปเดต companies ===\n";
$stmt = $db->prepare("SELECT id, company_name, industry_type FROM companies");
$stmt->execute();
$companies = $stmt->fetchAll();

foreach($companies as $company) {
    // Random 1-2 industry types from new categories
    $numTypes = rand(1, 2);
    $shuffled = $newCategories;
    shuffle($shuffled);
    $selectedTypes = array_slice($shuffled, 0, $numTypes);
    $industryStr = implode(',', $selectedTypes);
    
    $updateStmt = $db->prepare("UPDATE companies SET industry_type = ? WHERE id = ?");
    $updateStmt->execute([$industryStr, $company['id']]);
    
    echo "Updated ID {$company['id']} ({$company['company_name']}): {$numTypes} industry type(s)\n";
}

echo "\n=== สรุปผล ===\n";
echo "อัปเดต auditors: " . count($auditors) . " คน\n";
echo "อัปเดต companies: " . count($companies) . " บริษัท\n";
echo "\nเสร็จสิ้น!\n";
