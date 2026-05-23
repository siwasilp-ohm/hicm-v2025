<?php
require_once __DIR__ . '/../includes/auth.php';
$db = getDB()->getConnection();

try {
    $db->exec("ALTER TABLE companies ADD COLUMN default_evaluator_id INT NULL AFTER industry_type");
    echo "Column 'default_evaluator_id' added successfully to 'companies' table.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column 'default_evaluator_id' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
