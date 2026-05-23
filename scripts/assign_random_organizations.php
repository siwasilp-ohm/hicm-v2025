<?php
/**
 * Random assign organizations to auditors
 * สุ่มหน่วยงานให้กรรมการทุกคน
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // Get all organizations
    $orgStmt = $pdo->query("SELECT id, short_name FROM organizations WHERE is_active = 1 ORDER BY id");
    $organizations = $orgStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($organizations)) {
        echo "No organizations found!\n";
        exit(1);
    }
    
    echo "Found " . count($organizations) . " organizations.\n\n";
    
    // Get all auditors
    $auditorStmt = $pdo->query("SELECT id, name FROM users WHERE role = 'auditor' ORDER BY id");
    $auditors = $auditorStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($auditors)) {
        echo "No auditors found!\n";
        exit(1);
    }
    
    echo "Found " . count($auditors) . " auditors.\n\n";
    
    // Update each auditor with random organization
    $updateStmt = $pdo->prepare("UPDATE users SET organization_id = ? WHERE id = ?");
    
    $count = 0;
    foreach ($auditors as $auditor) {
        // Random select organization
        $randomOrg = $organizations[array_rand($organizations)];
        
        $updateStmt->execute([$randomOrg['id'], $auditor['id']]);
        $count++;
        
        echo "✓ {$auditor['name']} => {$randomOrg['short_name']}\n";
    }
    
    echo "\n✅ Successfully assigned organizations to {$count} auditors.\n";
    
    // Verification - show distribution
    echo "\n--- Organization Distribution ---\n";
    $distStmt = $pdo->query("
        SELECT o.short_name, COUNT(u.id) as auditor_count 
        FROM organizations o 
        LEFT JOIN users u ON u.organization_id = o.id AND u.role = 'auditor'
        WHERE o.is_active = 1
        GROUP BY o.id, o.short_name
        ORDER BY auditor_count DESC, o.display_order
    ");
    
    while ($row = $distStmt->fetch(PDO::FETCH_ASSOC)) {
        $bar = str_repeat('█', $row['auditor_count']);
        echo "{$row['short_name']}: {$row['auditor_count']} {$bar}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
