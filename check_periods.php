<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();
$stmt = $db->getConnection()->query("
    SELECT p.id, p.name, p.year, COUNT(a.id) as assessment_count 
    FROM assessment_periods p 
    LEFT JOIN assessments a ON p.id = a.period_id 
    GROUP BY p.id
");

echo "รายการ Period และจำนวน Assessments:\n";
echo "================================\n";
foreach ($stmt->fetchAll() as $row) {
    echo "ID: {$row['id']} | ปี: {$row['year']} | ชื่อ: {$row['name']} | จำนวน assessments: {$row['assessment_count']}\n";
}
