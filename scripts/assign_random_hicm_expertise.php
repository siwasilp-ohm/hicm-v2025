<?php
/**
 * Script to randomly assign HICM Expertise (H1, I2, C3, M4) to all auditors
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Random Assign HICM Expertise to Auditors ===\n\n";

try {
    $db = getDB();
    
    // Check if hicm_expertise column exists
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'hicm_expertise'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $db->prepare("ALTER TABLE users ADD COLUMN hicm_expertise TEXT AFTER expertise")->execute();
        echo "✓ Added hicm_expertise column to users table\n\n";
    }
    
    // Get all auditors
    $stmt = $db->prepare("SELECT id, name FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY id");
    $stmt->execute();
    $auditors = $stmt->fetchAll();
    
    echo "Found " . count($auditors) . " auditors\n\n";
    
    // HICM Pillars
    $pillars = ['H1', 'I2', 'C3', 'M4'];
    
    // Update each auditor with random pillars (1-3 pillars each)
    $updateStmt = $db->prepare("UPDATE users SET hicm_expertise = ? WHERE id = ?");
    
    $stats = ['H1' => 0, 'I2' => 0, 'C3' => 0, 'M4' => 0];
    
    foreach ($auditors as $auditor) {
        // Random number of pillars (1-3)
        $numPillars = rand(1, 3);
        
        // Shuffle and pick random pillars
        $shuffled = $pillars;
        shuffle($shuffled);
        $selectedPillars = array_slice($shuffled, 0, $numPillars);
        sort($selectedPillars); // Sort for consistency (H1, I2, C3, M4 order)
        
        // Update stats
        foreach ($selectedPillars as $p) {
            $stats[$p]++;
        }
        
        // Save to database
        $expertiseStr = implode('|', $selectedPillars);
        $updateStmt->execute([$expertiseStr, $auditor['id']]);
        
        echo sprintf("✓ %s => %s\n", $auditor['name'], implode(', ', $selectedPillars));
    }
    
    echo "\n=== Summary ===\n";
    echo "Total auditors: " . count($auditors) . "\n\n";
    echo "Expertise distribution:\n";
    foreach ($stats as $pillar => $count) {
        $pillarName = PILLARS[$pillar]['name_th'] ?? $pillar;
        echo sprintf("  %s (%s): %d auditors\n", $pillar, $pillarName, $count);
    }
    
    echo "\n✅ Done! All auditors have been assigned HICM expertise.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
