<?php
// scripts/debug_xlsx.php
$file = __DIR__ . '/../company_data.xlsx';

if (!file_exists($file)) {
    die("File not found\n");
}

$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    echo "XLSX Opened Successfully\n";
    for($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        // echo "File: $name\n"; // Too much output
    }
    
    // Check key files
    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
        echo "Found: sharedStrings.xml\n";
    } else {
        echo "Missing: sharedStrings.xml (might be empty xlsx or inline strings)\n";
    }
    
    if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
        echo "Found: sheet1.xml\n";
        
        // Peek at sheet1 structure
        $content = $zip->getFromName('xl/worksheets/sheet1.xml');
        echo "Sheet Content Preview:\n" . substr($content, 0, 500) . "...\n";
    }
    
    $zip->close();
} else {
    echo "Failed to open XLSX as Zip\n";
}
?>
