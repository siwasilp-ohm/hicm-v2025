<?php
/**
 * Seed Organizations Data
 * ใส่ข้อมูลหน่วยงานภาคีเครือข่าย
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // Clear existing data
    $pdo->exec("DELETE FROM organizations");
    $pdo->exec("ALTER TABLE organizations AUTO_INCREMENT = 1");
    
    echo "Cleared existing organizations data.\n";
    
    // Organizations data
    $organizations = [
        ['name' => 'สำนักสนับสนุนสุขภาวะองค์กร (สำนัก 8)', 'short_name' => 'สำนัก 8'],
        ['name' => 'สำนักงานแรงงานจังหวัดนครราชสีมา-กระทรวงแรงงาน', 'short_name' => 'แรงงานจังหวัด'],
        ['name' => 'สำนักงานสาธารณสุขจังหวัดนครราชสีมา', 'short_name' => 'สสจ.นครราชสีมา'],
        ['name' => 'องค์การบริหารส่วนจังหวัดนครราชสีมา', 'short_name' => 'อบจ.นครราชสีมา'],
        ['name' => 'ศูนย์อนามัยที่ 9 นครราชสีมา', 'short_name' => 'ศูนย์อนามัย 9'],
        ['name' => 'สำนักงานประกันสังคมจังหวัดนครราชสีมา', 'short_name' => 'ประกันสังคม'],
        ['name' => 'ศูนย์ส่งเสริมอุตสาหกรรมภาคที่ 6', 'short_name' => 'ศูนย์อุตฯ ภาค 6'],
        ['name' => 'สำนักงานสวัสดิการและคุ้มครองแรงงานจังหวัดนครราชสีมา', 'short_name' => 'สวัสดิการแรงงาน'],
        ['name' => 'ศูนย์ความปลอดภัยในการทำงานเขต 3 (นครราชสีมา)', 'short_name' => 'ศูนย์ความปลอดภัย 3'],
        ['name' => 'สำนักงานอุตสาหกรรมจังหวัดนครราชสีมา', 'short_name' => 'อุตสาหกรรมจังหวัด'],
        ['name' => 'ศูนย์สุขภาพจิตที่ 9', 'short_name' => 'ศูนย์สุขภาพจิต 9'],
        ['name' => 'สำนักงานป้องกันควบคุมโรคที่ 9 นครราชสีมา', 'short_name' => 'สคร.9'],
        ['name' => 'สถาบันพัฒนาฝีมือแรงงาน 5 นครราชสีมา', 'short_name' => 'สพร.5'],
        ['name' => 'การนิคมอุตสาหกรรมแห่งประเทศไทย', 'short_name' => 'กนอ.'],
        ['name' => 'จังหวัดนครราชสีมา', 'short_name' => 'จ.นครราชสีมา'],
        ['name' => 'สภาอุตสาหกรรมแห่งประเทศไทย', 'short_name' => 'ส.อ.ท.'],
        ['name' => 'กระทรวงดิจิทัลเพื่อเศรษฐกิจและสังคม', 'short_name' => 'ก.ดิจิทัล'],
        ['name' => 'สภาอุตสาหกรรมจังหวัดนครราชสีมา', 'short_name' => 'ส.อ.ท.นครราชสีมา'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO organizations (name, short_name, display_order, is_active) VALUES (?, ?, ?, 1)");
    
    $count = 0;
    foreach ($organizations as $index => $org) {
        $stmt->execute([$org['name'], $org['short_name'], $index + 1]);
        $count++;
        echo "Inserted: {$org['short_name']}\n";
    }
    
    echo "\n✅ Successfully inserted {$count} organizations.\n";
    
    // Verify
    echo "\n--- Verification ---\n";
    $result = $pdo->query("SELECT id, name, short_name FROM organizations ORDER BY display_order");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['id']}. {$row['short_name']} - {$row['name']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
