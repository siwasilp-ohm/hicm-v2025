<?php
/**
 * Script to populate all company fields with realistic demo data
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $conn = $db->getConnection();
    
    // Get all companies
    $stmt = $conn->query("SELECT id, company_name, industry_type FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($companies) . " companies to update.\n\n";
    
    // Thai contact names
    $thaiNames = [
        'สมชาย วงศ์สุวรรณ', 'สุภาพร ศรีสวัสดิ์', 'ประเสริฐ มั่นคง', 'วิไลวรรณ จันทร์เพ็ญ',
        'อนุชา พงษ์พานิช', 'ชนิดา ทองดี', 'ธนพล รัตนพันธ์', 'กนกวรรณ สุขสวัสดิ์',
        'วีระพงษ์ ชัยวัฒน์', 'นันทนา บุญมี', 'สมศักดิ์ เจริญสุข', 'พิมพ์ชนก ศิริมงคล'
    ];
    
    // Positions
    $positions = [
        'ผู้จัดการทั่วไป', 'ผู้อำนวยการ', 'หัวหน้าฝ่ายปฏิบัติการ', 'ผู้จัดการฝ่ายทรัพยากรบุคคล',
        'ผู้จัดการฝ่ายการตลาด', 'ผู้จัดการฝ่ายผลิต', 'หัวหน้าฝ่ายบัญชี', 'ผู้จัดการฝ่ายขาย'
    ];
    
    // Thai provinces
    $provinces = [
        'กรุงเทพมหานคร', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'ชลบุรี', 
        'ระยอง', 'เชียงใหม่', 'ขอนแก่น', 'นครราชสีมา', 'สงขลา'
    ];
    
    // Districts
    $districts = [
        'เมือง', 'บางกะปิ', 'ห้วยขวาง', 'ดินแดง', 'ราชเทวี', 'ปากเกร็ด',
        'บางใหญ่', 'ศรีราชา', 'บางพลี', 'สันกำแพง'
    ];
    
    // Street names
    $streets = [
        'ถนนสุขุมวิท', 'ถนนพระราม 4', 'ถนนรัชดาภิเษก', 'ถนนงามวงศ์วาน', 'ถนนบางนา-ตราด',
        'ถนนเพชรบุรี', 'ถนนศรีนครินทร์', 'ถนนรามอินทรา', 'ถนนวิภาวดีรังสิต', 'ถนนลาดพร้าว'
    ];
    
    $updateCount = 0;
    
    foreach ($companies as $index => $company) {
        $companyId = $company['id'];
        $companyName = $company['company_name'];
        
        // Generate realistic data
        $taxId = '0' . rand(100, 999) . rand(10000, 99999) . '000' . rand(1, 9);
        $employeeCount = rand(50, 2000);
        $establishedYear = rand(1990, 2020);
        
        $contactName = $thaiNames[array_rand($thaiNames)];
        $position = $positions[array_rand($positions)];
        $email = 'contact' . ($index + 1) . '@' . strtolower(str_replace(' ', '', preg_replace('/[^a-zA-Z0-9\s]/', '', $companyName))) . '.co.th';
        $contactPhone = '0' . rand(80, 99) . '-' . rand(100, 999) . '-' . rand(1000, 9999);
        $companyPhone = '0' . rand(2, 9) . '-' . rand(100, 999) . '-' . rand(1000, 9999);
        
        $buildingNo = rand(1, 999);
        $street = $streets[array_rand($streets)];
        $district = $districts[array_rand($districts)];
        $province = $provinces[array_rand($provinces)];
        $postalCode = rand(10000, 99999);
        
        $address = "$buildingNo $street แขวง/ตำบล$district";
        
        $website = 'https://www.' . strtolower(str_replace(' ', '', preg_replace('/[^a-zA-Z0-9\s]/', '', $companyName))) . '.co.th';
        $fax = '0' . rand(2, 9) . '-' . rand(100, 999) . '-' . rand(1000, 9999);
        
        // Generate description based on industry
        $descriptions = [
            'ไฟฟ้า' => 'ผู้ผลิตและจำหน่ายอุปกรณ์ไฟฟ้าและระบบพลังงานคุณภาพสูง มุ่งเน้นนวัตกรรมและความยั่งยืน',
            'เหล็ก' => 'ผู้ผลิตและจัดจำหน่ายเหล็กและผลิตภัณฑ์โลหะคุณภาพสูง รองรับอุตสาหกรรมก่อสร้างและการผลิต',
            'เคมี' => 'ผู้ผลิตและจัดจำหน่ายสารเคมีอุตสาหกรรม มุ่งเน้นความปลอดภัยและมาตรฐานสากล',
            'ประกอบชิ้นส่วน' => 'ผู้ผลิตชิ้นส่วนอุตสาหกรรมคุณภาพสูง รองรับอุตสาหกรรมยานยนต์และอิเล็กทรอนิกส์',
            'อาหาร' => 'ผู้ผลิตและจำหน่ายผลิตภัณฑ์อาหารคุณภาพ ได้มาตรฐาน GMP และ HACCP'
        ];
        
        $industryType = $company['industry_type'] ?? 'อื่นๆ';
        $description = $descriptions[$industryType] ?? 'บริษัทชั้นนำในอุตสาหกรรม มุ่งมั่นพัฒนาคุณภาพและบริการที่ดีเยี่ยม';
        
        // Update company
        $updateStmt = $conn->prepare("
            UPDATE companies SET
                tax_id = ?,
                employee_count = ?,
                established_year = ?,
                contact_name = ?,
                contact_position = ?,
                contact_email = ?,
                contact_phone = ?,
                phone = ?,
                address = ?,
                province = ?,
                district = ?,
                postal_code = ?,
                website = ?,
                fax = ?,
                description = ?
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $taxId,
            $employeeCount,
            $establishedYear,
            $contactName,
            $position,
            $email,
            $contactPhone,
            $companyPhone,
            $address,
            $province,
            $district,
            $postalCode,
            $website,
            $fax,
            $description,
            $companyId
        ]);
        
        $updateCount++;
        echo "✓ Updated: $companyName (ID: $companyId)\n";
        echo "  Contact: $contactName ($position)\n";
        echo "  Email: $email\n";
        echo "  Address: $address, $district, $province $postalCode\n\n";
    }
    
    echo "\n========================================\n";
    echo "Successfully updated $updateCount companies!\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
