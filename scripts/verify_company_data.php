<?php
/**
 * Verify that all companies have complete data
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("
        SELECT 
            id,
            company_name,
            tax_id,
            employee_count,
            established_year,
            contact_name,
            contact_email,
            phone,
            address,
            website
        FROM companies
        ORDER BY id
    ");
    
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total companies: " . count($companies) . "\n\n";
    
    $completeCount = 0;
    $incompleteCount = 0;
    
    foreach ($companies as $company) {
        $isComplete = true;
        $missingFields = [];
        
        if (empty($company['tax_id'])) {
            $isComplete = false;
            $missingFields[] = 'tax_id';
        }
        if (empty($company['employee_count'])) {
            $isComplete = false;
            $missingFields[] = 'employee_count';
        }
        if (empty($company['established_year'])) {
            $isComplete = false;
            $missingFields[] = 'established_year';
        }
        if (empty($company['contact_name'])) {
            $isComplete = false;
            $missingFields[] = 'contact_name';
        }
        if (empty($company['contact_email'])) {
            $isComplete = false;
            $missingFields[] = 'contact_email';
        }
        if (empty($company['phone'])) {
            $isComplete = false;
            $missingFields[] = 'phone';
        }
        if (empty($company['address'])) {
            $isComplete = false;
            $missingFields[] = 'address';
        }
        if (empty($company['website'])) {
            $isComplete = false;
            $missingFields[] = 'website';
        }
        
        if ($isComplete) {
            $completeCount++;
            echo "✓ " . $company['company_name'] . " - COMPLETE\n";
        } else {
            $incompleteCount++;
            echo "✗ " . $company['company_name'] . " - MISSING: " . implode(', ', $missingFields) . "\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Complete: $completeCount / " . count($companies) . "\n";
    echo "Incomplete: $incompleteCount\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
