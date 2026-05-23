<?php
/**
 * Random assign industry types to companies
 * สุ่มประเภทอุตสาหกรรมให้บริษัททุกบริษัท
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // Get industry types from config
    $industryTypes = AUDITOR_EXPERTISE;
    
    echo "Available Industry Types:\n";
    foreach ($industryTypes as $i => $type) {
        echo ($i + 1) . ". {$type}\n";
    }
    echo "\n";
    
    // Get all companies
    $stmt = $pdo->query("SELECT id, company_name FROM companies ORDER BY id");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($companies) . " companies.\n\n";
    
    // Update each company with 1-3 random industry types
    $updateStmt = $pdo->prepare("UPDATE companies SET industry_type = ? WHERE id = ?");
    
    $count = 0;
    foreach ($companies as $company) {
        // Random 1-3 industry types
        $numTypes = rand(1, 3);
        $shuffled = $industryTypes;
        shuffle($shuffled);
        $selectedTypes = array_slice($shuffled, 0, $numTypes);
        $industryStr = implode(',', $selectedTypes);
        
        $updateStmt->execute([$industryStr, $company['id']]);
        $count++;
        
        // Show short version
        $shortTypes = array_map(function($t) {
            // Extract Thai part only
            return preg_replace('/\s*\([^)]+\)/', '', $t);
        }, $selectedTypes);
        
        echo "✓ {$company['company_name']} => " . implode(', ', $shortTypes) . "\n";
    }
    
    echo "\n✅ Successfully assigned industry types to {$count} companies.\n";
    
    // Show distribution
    echo "\n--- Industry Type Distribution ---\n";
    foreach ($industryTypes as $type) {
        $escapedType = addslashes($type);
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM companies WHERE industry_type LIKE '%{$escapedType}%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $shortType = preg_replace('/\s*\([^)]+\)/', '', $type);
        $bar = str_repeat('█', $row['cnt']);
        echo "{$shortType}: {$row['cnt']} {$bar}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
