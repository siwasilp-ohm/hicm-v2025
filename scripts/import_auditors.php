<?php
/**
 * scripts/import_auditors.php
 * Import auditor data from aud_data.xlsx using native ZipArchive
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

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

$file = __DIR__ . '/../aud_data.xlsx';
if (!file_exists($file)) {
    die("Error: aud_data.xlsx not found.\n");
}

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
    foreach ($row->c as $cell) {
        $ref = (string)$cell['r'];
        $col = preg_replace('/[0-9]/', '', $ref);
        $colIdx = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $colIdx = $colIdx * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        $colIdx--; 

        $val = (string)$cell->v;
        if ((string)$cell['t'] === 's') {
            $val = $sharedStrings[intval($val)] ?? '';
        }
        $rowData[$colIdx] = trim($val);
    }

    // Mapping:
    // 1: FirstName, 2: LastName, 3: Username, 4: Password, 5: Email, 6: Position, 7: CV
    $firstName = $rowData[1] ?? '';
    $lastName = $rowData[2] ?? '';
    $username = $rowData[3] ?? '';
    $password = $rowData[4] ?? '';
    $email = $rowData[5] ?? '';
    $position = $rowData[6] ?? '';
    $cv = $rowData[7] ?? '';

    if (empty($username)) continue;

    $fullName = trim($firstName . ' ' . $lastName);
    
    $data = [
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'name' => $fullName,
        'role' => ROLE_AUDITOR,
        'phone' => '' // Not provided in Excel
    ];

    $result = createUser($data);
    if ($result['success']) {
        $rowsInserted++;
        echo "Successfully imported auditor: $fullName ($username)\n";
    } else if ($result['message'] === 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว') {
        // Update existing user
        $stmt = getDB()->prepare("UPDATE users SET name = ?, email = ? WHERE username = ? AND role = ?");
        $stmt->execute([$fullName, $email, $username, ROLE_AUDITOR]);
        $rowsInserted++;
        echo "Successfully updated auditor: $fullName ($username)\n";
    } else {
        $errors[] = "Error importing $username: " . $result['message'];
        echo "FAILED: $username - " . $result['message'] . "\n";
    }
}

$zip->close();

echo "\n--- Import Summary ---\n";
echo "Total Rows Processed: " . ($rowsInserted + count($errors)) . "\n";
echo "Successfully Inserted: $rowsInserted\n";
echo "Failures: " . count($errors) . "\n";
if (!empty($errors)) {
    echo "Detail errors (Note: some might be existing users):\n" . implode("\n", $errors) . "\n";
}
?>
