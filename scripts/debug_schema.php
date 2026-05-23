<?php
require_once __DIR__ . '/../includes/auth.php';
$db = getDB()->getConnection();

echo "Table: companies\n";
$stmt = $db->query("DESCRIBE companies");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\nTable: assessments\n";
$stmt = $db->query("DESCRIBE assessments");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
?>
