<?php
/**
 * API: Save/Update/Delete User Manual Items
 * Admin only
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Auth check
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDB()->getConnection();

try {
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $role = $_POST['role'] ?? '';
            $category = $_POST['category'] ?? '';
            $displayOrder = (int)($_POST['display_order'] ?? 0);

            if (!$title || !$content) {
                echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่อหัวข้อและเนื้อหา']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO user_manual (title, content, role, category, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$title, $content, $role, $category, $displayOrder]);
            
            echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'สร้างหัวข้อใหม่สำเร็จ']);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $role = $_POST['role'] ?? '';
            $category = $_POST['category'] ?? '';
            $displayOrder = (int)($_POST['display_order'] ?? 0);

            if (!$id || !$title || !$content) {
                echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
                exit;
            }

            $stmt = $db->prepare("UPDATE user_manual SET title = ?, content = ?, role = ?, category = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$title, $content, $role, $category, $displayOrder, $id]);
            
            echo json_encode(['success' => true, 'message' => 'อัปเดตสำเร็จ']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสหัวข้อ']);
                exit;
            }

            // Soft delete
            $stmt = $db->prepare("UPDATE user_manual SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'ลบสำเร็จ']);
            break;

        case 'get':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสหัวข้อ']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM user_manual WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                echo json_encode(['success' => true, 'item' => $item]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่พบหัวข้อ']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
