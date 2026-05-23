<?php
/**
 * HICM V2025 - Download Manual Reference Files (Dynamic)
 * อ่านรายการจาก manual_content.json อนุญาต Admin ปรับเปลี่ยนได้
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header("HTTP/1.1 401 Unauthorized");
    exit('กรุณาเข้าสู่ระบบ');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : -1;
if ($id < 0) {
    header("HTTP/1.1 404 Not Found");
    exit('ไม่พบไฟล์');
}

$manualFile = dirname(__DIR__) . '/manual_content.json';
$manualData = [];
if (file_exists($manualFile)) {
    $manualData = json_decode(file_get_contents($manualFile), true) ?: [];
}

$downloadFiles = $manualData['download_files'] ?? [];
if (!is_array($downloadFiles) || !isset($downloadFiles[$id])) {
    header("HTTP/1.1 404 Not Found");
    exit('ไม่พบไฟล์');
}

$item = $downloadFiles[$id];
$filename = $item['filename'] ?? '';
if (empty($filename)) {
    header("HTTP/1.1 404 Not Found");
    exit('ไม่พบไฟล์');
}

$basePath = dirname(__DIR__);
$manualRefsDir = $basePath . '/assets/uploads/manual_refs/';

// ค้นหาไฟล์: 1) ใน manual_refs 2) ใน root
$filePath = $manualRefsDir . $filename;
if (!file_exists($filePath)) {
    $filePath = $basePath . '/' . $filename;
}

if (!file_exists($filePath) || !is_readable($filePath)) {
    header("HTTP/1.1 404 Not Found");
    exit('ไม่พบไฟล์หรือไม่สามารถอ่านได้');
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimes = ['xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xls' => 'application/vnd.ms-excel', 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$contentType = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
exit;
