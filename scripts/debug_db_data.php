<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$out = "";
$out .= "--- Distinct Values in company_size ---\n";
$stmt = $db->getConnection()->query("SELECT DISTINCT company_size FROM companies");
while ($row = $stmt->fetch()) {
    $out .= "'" . $row['company_size'] . "'\n";
}

$out .= "\n--- Count of Empty/Null sizes ---\n";
$count = $db->getConnection()->query("SELECT COUNT(*) FROM companies WHERE company_size IS NULL OR company_size = ''")->fetchColumn();
$out .= "Empty sizes: $count\n";

$out .= "\n--- Sample Data (5 rows) ---\n";
$stmt = $db->getConnection()->query("SELECT id, company_name, company_size FROM companies LIMIT 5");
while ($row = $stmt->fetch()) {
    $out .= $row['id'] . " | " . $row['company_name'] . " | '" . $row['company_size'] . "'\n";
}
file_put_contents(__DIR__ . '/debug_db.txt', $out);
echo "Data saved to debug_db.txt\n";
?>
