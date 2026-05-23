<?php
/**
 * scripts/debug_aud_data.php
 */

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

foreach ($xml->sheetData->row as $row) {
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
    echo json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
}

$zip->close();
?>
