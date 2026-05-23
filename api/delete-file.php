<?php
/**
 * HICM V2025 Assessment System - Delete File API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user = getCurrentUser();

// Get JSON data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);
$fileId = intval($data['file_id'] ?? 0);

if (!$fileId) {
    echo json_encode(['success' => false, 'message' => 'ID ไฟล์ไม่ถูกต้อง']);
    exit;
}

try {
    $db = getDB();
    
    // Check if user has permission to delete this file
    $stmt = $db->prepare("
        SELECT a.* FROM attachments a
        JOIN assessment_scores s ON a.assessment_score_id = s.id
        JOIN assessments asses ON s.assessment_id = asses.id
        JOIN companies c ON asses.company_id = c.id
        WHERE a.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$fileId, $user['id']]);
    $file = $stmt->fetch();
    
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์หรือคุณไม่มีสิทธิ์ลบไฟล์นี้']);
        exit;
    }
    
    // Delete physical file
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    // Delete from database
    $scoreId = $file['assessment_score_id'];
    $stmt = $db->prepare("DELETE FROM attachments WHERE id = ?");
    $stmt->execute([$fileId]);
    
    // Update attachment count
    $stmt = $db->prepare("
        UPDATE assessment_scores 
        SET self_attachment_count = (SELECT COUNT(*) FROM attachments WHERE assessment_score_id = ?)
        WHERE id = ?
    ");
    $stmt->execute([$scoreId, $scoreId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
