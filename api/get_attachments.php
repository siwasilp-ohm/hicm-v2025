<?php
/**
 * HICM V2025 Assessment System - Get Attachments API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$scoreId = intval($_GET['score_id'] ?? 0);

if (!$scoreId) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลคะแนน']);
    exit;
}

try {
    $attachments = getAttachmentsByScoreId($scoreId);
    
    echo json_encode([
        'success' => true,
        'attachments' => $attachments
    ]);
    
} catch (Exception $e) {
    error_log("Get attachments API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
}
