<?php
/**
 * scripts/import_companies.php
 * Import company data from company_data.xlsx using native ZipArchive
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/companies.php';

// Ensure we have a session for logActivity
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1; // Default to admin for import audit logs

// Fix for missing logActivity in CLI
if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $description = '') {
        echo "[LOG] User $userId: $action - $description\n";
    }
}

$file = __DIR__ . '/../company_data.xlsx';
if (!file_exists($file)) {
    die("Error: company_data.xlsx not found.\n");
}

$industryMap = [
    'ผลิตชิ้นส่วนเหล็ก' => 'steel_parts',
    'ยานยนต์' => 'automotive',
    'แป้ง' => 'flour',
    'อาหารและเครื่องดื่ม' => 'food_beverage',
    'ขนส่ง' => 'logistics',
    'ไฟฟ้า' => 'electrical',
    'เคมี' => 'chemical',
    'เหล็ก' => 'steel',
    'โลหะ' => 'metal',
    'สิ่งทอและพลาสติก' => 'textile_plastic',
    'ประกอบชิ้นส่วน' => 'assembly',
    'ชิ้นส่วนยานยนต์' => 'automotive_parts',
    'การผลิต' => 'manufacturing',
    'การบริการ' => 'service',
    'การค้า' => 'trading',
    'เทคโนโลยี' => 'technology',
    'การเกษตร' => 'agriculture'
];

$sizeMap = [
    'ไม่เกิน 50 คน' => 'size_0_50',
    '50-100 คน' => 'size_50_100',
    '101-200 คน' => 'size_101_200',
    '201-500 คน' => 'size_201_500',
    '501-1,000 คน' => 'size_501_1000',
    '1,000 คน ขึ้นไป' => 'size_1000_plus'
];

$zip = new ZipArchive;
if ($zip->open($file) === FALSE) {
    die("Error: Could not open XLSX file.\n");
}

// 1. Load Shared Strings
$sharedStrings = [];
if ($zip->locateName('xl/sharedStrings.xml') !== false) {
    $xml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
    foreach ($xml->si as $si) {
        $sharedStrings[] = (string)$si->t;
    }
}

// 2. Parse Sheet 1
if ($zip->locateName('xl/worksheets/sheet1.xml') === false) {
    die("Error: sheet1.xml not found.\n");
}

$xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
$rowsInserted = 0;
$errors = [];
$isHeader = true;

foreach ($xml->sheetData->row as $row) {
    if ($isHeader) {
        $isHeader = false;
        continue;
    }

    $rowData = [];
    // Ensure we handle sparse cells or shifted indices if any
    foreach ($row->c as $cell) {
        // Extract column index from 'r' attribute (e.g. A1, B1)
        $ref = (string)$cell['r'];
        $col = preg_replace('/[0-9]/', '', $ref);
        $colIdx = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $colIdx = $colIdx * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        $colIdx--; // 0-based

        $val = (string)$cell->v;
        if ((string)$cell['t'] === 's') {
            $val = $sharedStrings[intval($val)] ?? '';
        }
        $rowData[$colIdx] = trim($val);
    }

    // Mapping based on debug structure:
    // 1: Company Name, 2: Username, 3: Password, 4: Email, 5: Size, 6: Industry, 7: Contact Name
    $companyName = $rowData[1] ?? '';
    $username = $rowData[2] ?? '';
    $password = $rowData[3] ?? '123456';
    $email = $rowData[4] ?? '';
    $sizeText = $rowData[5] ?? '';
    $industryText = $rowData[6] ?? '';
    $contactName = $rowData[7] ?? '';

    if (empty($companyName) || empty($username)) continue;

    // Handle generic/duplicate emails by making them unique per username
    if (empty($email) || $email === 'com@com.com') {
        $email = $username . '@example.com';
    }

    $data = [
        'username' => $username,
        'password' => $password,
        'contact_email' => $email,
        'contact_name' => $contactName,
        'contact_phone' => '',
        'company_name' => $companyName,
        'company_name_en' => '',
        'tax_id' => '',
        'address' => '',
        'province' => '',
        'district' => '',
        'postal_code' => '',
        'phone' => '',
        'fax' => '',
        'website' => '',
        'industry_type' => $industryMap[$industryText] ?? 'other',
        'company_size' => $sizeMap[$sizeText] ?? 'size_0_50',
        'employee_count' => 0,
        'established_year' => '',
        'contact_position' => '',
        'description' => 'Imported from Excel'
    ];

    $result = createCompany($data);
    if ($result['success']) {
        $rowsInserted++;
        echo "Successfully imported: $companyName\n";
    } else {
        $errors[] = "Error importing $companyName: " . $result['message'];
        echo "FAILED: $companyName - " . $result['message'] . "\n";
    }
}

$zip->close();

echo "\n--- Import Summary ---\n";
echo "Total Rows Processed: " . ($rowsInserted + count($errors)) . "\n";
echo "Successfully Inserted: $rowsInserted\n";
echo "Failures: " . count($errors) . "\n";
if (!empty($errors)) {
    echo "Detail errors:\n" . implode("\n", $errors) . "\n";
}
?>
