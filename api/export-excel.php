<?php
/**
 * HICM V2025 Assessment System - Export Excel API Placeholder
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// This is a placeholder. Real export usually returns a file stream.
// For now, redirect to the working export-report.php if needed, 
// or implement a basic response.

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'ฟีเจอร์นี้กำลังอยู่ระหว่างการพัฒนา กรุณาใช้ปุ่มพิมพ์หรือดาวน์โหลด PDF ในหน้ารายงาน']);
