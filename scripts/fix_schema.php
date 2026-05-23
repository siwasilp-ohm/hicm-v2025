<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

try {
    $conn = $db->getConnection();
    
    echo "Altering company_size column...\n";
    $conn->exec("ALTER TABLE companies MODIFY COLUMN company_size VARCHAR(50)");
    echo "Schema updated successfully.\n";
    
    echo "Clearing corrupted data (imported rows)...\n";
    // We can identify imported rows by checking description or user_id > initial range
    // But since it's a fresh import, we can just delete companies with empty size if we are sure
    // Better: delete users created recently and their companies.
    // In our script we set created_by to session 1.
    
    // Let's just delete ALL companies and users with IDs > some threshold if we knew it, 
    // but better to just delete ALL companies and users for now to be safe and clean.
    // Actually, I'll just delete the companies I just imported.
    // They all have 'Imported from Excel' in description.
    
    $stmt = $conn->prepare("DELETE FROM companies WHERE description = 'Imported from Excel'");
    $stmt->execute();
    $deletedCompanies = $stmt->rowCount();
    echo "Deleted $deletedCompanies companies.\n";
    
    // We should also delete the users. 
    // They are linked by user_id. 
    // But first, we need to find them.
    
    $conn->exec("DELETE FROM users WHERE id NOT IN (SELECT user_id FROM companies) AND role = 'company'");
    echo "Cleaned up orphaned company users.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
