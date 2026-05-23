<?php
/**
 * HICM V2025 Assessment System - File Upload API
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

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'อัปโหลดไฟล์ไม่สำเร็จ';
    if (isset($_FILES['file'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'ไฟล์มีขนาดใหญ่เกินไป';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'อัปโหลดไฟล์ไม่สมบูรณ์';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$file = $_FILES['file'];
$indicatorId = intval($_POST['indicator_id'] ?? 0);

// Validate file type
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
];

if (!isset($allowedTypes[$file['type']])) {
    echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่รองรับ']);
    exit;
}

// Validate file size (10MB max)
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 10MB)']);
    exit;
}

// Generate unique filename
$extension = $allowedTypes[$file['type']];
$uniqueName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$uploadPath = APP_UPLOAD_PATH . date('Y/m/');

// Create directory if not exists
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0755, true);
}

$targetPath = $uploadPath . $uniqueName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
    exit;
}

// Save to database
try {
    $db = getDB();
    
    // Get assessment_score_id from indicator_id
    $assessmentId = intval($_POST['assessment_id'] ?? 0);
    
    if ($assessmentId > 0) {
        // Precise lookup with assessment_id
        $stmt = $db->prepare("
            SELECT s.id FROM assessment_scores s
            JOIN assessments a ON s.assessment_id = a.id
            JOIN companies c ON a.company_id = c.id
            WHERE s.indicator_id = ? AND a.id = ?
            LIMIT 1
        ");
        $stmt->execute([$indicatorId, $assessmentId]);
    } else {
        // Fallback to current assessment for the user's company
        $stmt = $db->prepare("
            SELECT s.id FROM assessment_scores s
            JOIN assessments a ON s.assessment_id = a.id
            JOIN companies c ON a.company_id = c.id
            WHERE s.indicator_id = ? AND c.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$indicatorId, $user['id']]);
    }
    $scoreRecord = $stmt->fetch();
    
    if (!$scoreRecord) {
        unlink($targetPath);
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการประเมิน']);
        exit;
    }
    
    $stmt = $db->prepare("
        INSERT INTO attachments (assessment_score_id, file_name, file_original_name, file_path, file_type, file_size, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $scoreRecord['id'],
        $uniqueName,
        $file['name'],
        $targetPath,
        $file['type'],
        $file['size'],
        $user['id']
    ]);
    
    $fileId = $db->lastInsertId();
    
    // Update attachment count
    $stmt = $db->prepare("
        UPDATE assessment_scores 
        SET self_attachment_count = (SELECT COUNT(*) FROM attachments WHERE assessment_score_id = ?)
        WHERE id = ?
    ");
    $stmt->execute([$scoreRecord['id'], $scoreRecord['id']]);
    
    echo json_encode([
        'success' => true,
        'file_id' => $fileId,
        'file_name' => $uniqueName,
        'original_name' => $file['name'],
        'file_type' => $file['type']
    ]);
    
} catch (Exception $e) {
    unlink($targetPath);
    error_log("Upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
?>
