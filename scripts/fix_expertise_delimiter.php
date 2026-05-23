<?php
/**
 * Script to fix expertise/industry_type data - change delimiter from comma to pipe (|)
 * แก้ไข delimiter จาก , เป็น | เพื่อไม่ให้ conflict กับ comma ในชื่อหมวดหมู่
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();

// New 8 industry categories
$newCategories = AUDITOR_EXPERTISE;

echo "=== แก้ไข delimiter ของ expertise/industry_type ===\n\n";

// Fix auditors
echo "=== แก้ไข auditors ===\n";
$stmt = $db->prepare("SELECT id, name, expertise FROM users WHERE role = 'auditor'");
$stmt->execute();
$auditors = $stmt->fetchAll();

foreach($auditors as $auditor) {
    if (empty($auditor['expertise'])) continue;
    
    // สุ่มใหม่เนื่องจากข้อมูลเก่าผิดรูปแบบ
    $numExpertise = rand(1, 3);
    $shuffled = $newCategories;
    shuffle($shuffled);
    $selectedExpertise = array_slice($shuffled, 0, $numExpertise);
    $expertiseStr = implode('|', $selectedExpertise); // Use | as delimiter
    
    $updateStmt = $db->prepare("UPDATE users SET expertise = ? WHERE id = ?");
    $updateStmt->execute([$expertiseStr, $auditor['id']]);
    
    echo "Updated ID {$auditor['id']} ({$auditor['name']})\n";
}

// Fix companies
echo "\n=== แก้ไข companies ===\n";
$stmt = $db->prepare("SELECT id, company_name, industry_type FROM companies");
$stmt->execute();
$companies = $stmt->fetchAll();

foreach($companies as $company) {
    // สุ่มใหม่เนื่องจากข้อมูลเก่าผิดรูปแบบ
    $numTypes = rand(1, 2);
    $shuffled = $newCategories;
    shuffle($shuffled);
    $selectedTypes = array_slice($shuffled, 0, $numTypes);
    $industryStr = implode('|', $selectedTypes); // Use | as delimiter
    
    $updateStmt = $db->prepare("UPDATE companies SET industry_type = ? WHERE id = ?");
    $updateStmt->execute([$industryStr, $company['id']]);
    
    echo "Updated ID {$company['id']} ({$company['company_name']})\n";
}

echo "\n=== ตรวจสอบผลลัพธ์ ===\n";
$stmt = $db->prepare("SELECT id, name, expertise FROM users WHERE role = 'auditor' LIMIT 3");
$stmt->execute();
$rows = $stmt->fetchAll();

foreach($rows as $r) {
    echo "ID: " . $r['id'] . " - " . $r['name'] . "\n";
    echo "Expertise: [" . $r['expertise'] . "]\n";
    $exps = explode('|', $r['expertise']);
    echo "Split count: " . count($exps) . "\n";
    echo "---\n";
}

echo "\nเสร็จสิ้น!\n";
