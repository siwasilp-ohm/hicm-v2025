<?php
// scripts/debug_xlsx_data.php
$file = __DIR__ . '/../company_data.xlsx';

if (!file_exists($file)) die("File not found\n");

$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    
    // 1. Load Shared Strings
    $sharedStrings = [];
    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
        $xml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
        foreach ($xml->si as $si) {
            $sharedStrings[] = (string)$si->t;
        }
    }

    // 2. Parse Sheet 1
    if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        $rowCount = 0;
        
        foreach ($xml->sheetData->row as $row) {
            if ($rowCount >= 5) break; 

            $rowData = [];
            foreach ($row->c as $cell) {
                $val = (string)$cell->v;
                if ((string)$cell['t'] === 's') {
                    $val = $sharedStrings[intval($val)];
                }
                $rowData[] = $val;
            }
            $rows[] = $rowData;
            $rowCount++;
        }
        
        file_put_contents(__DIR__ . '/debug_rows.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "Data saved to debug_rows.json\n";
    }
    $zip->close();
} else {
    echo "Failed to open XLSX\n";
}
?>
