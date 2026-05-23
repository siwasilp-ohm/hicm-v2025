<?php
/**
 * HICM V2025 Assessment System - Save News API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/news.php';

// Set JSON content type
header('Content-Type: application/json');

// Check authentication and admin role
if (!isLoggedIn() || !hasRole(ROLE_ADMIN)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
$user = getCurrentUser();

// Validate input
if (empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุหัวข้อและเนื้อหา']);
    exit;
}

try {
    if ($id > 0) {
        // Update
        if (updateAnnouncement($id, $title, $content, $status)) {
            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
        } else {
            throw new Exception("Failed to update announcement");
        }
    } else {
        // Create
        if (createAnnouncement($title, $content, $status, $user['id'])) {
            echo json_encode(['success' => true, 'message' => 'สร้างประกาศใหม่สำเร็จ']);
        } else {
            throw new Exception("Failed to create announcement");
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
