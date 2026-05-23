<?php
/**
 * HICM V2025 - API จัดการไฟล์เอกสารคู่มือ (List + Upload)
 * Admin only - สำหรับ manual-edit.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole(ROLE_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

$manualRefsDir = dirname(__DIR__) . '/assets/uploads/manual_refs/';
$projectRoot = dirname(__DIR__) . '/';

// สร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($manualRefsDir)) {
    mkdir($manualRefsDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ========== LIST: รายการไฟล์ที่ใช้ได้ ==========
if ($action === 'list') {
    $files = [];
    // ไฟล์ใน manual_refs
    if (is_dir($manualRefsDir)) {
        foreach (scandir($manualRefsDir) as $f) {
            if ($f !== '.' && $f !== '..' && is_file($manualRefsDir . $f)) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ['xlsx', 'xls', 'pdf', 'doc', 'docx'])) {
                    $files[] = ['name' => $f, 'source' => 'manual_refs'];
                }
            }
        }
    }
    // ไฟล์ใน root (Excel คู่มือ)
    $rootFiles = ['A_ส่วนที่ 1 บทนำและหลักการ.xlsx', 'B_ส่วนที่ 2 โครงสร้างและระบบคะแนน.xlsx'];
    foreach ($rootFiles as $f) {
        if (file_exists($projectRoot . $f)) {
            $files[] = ['name' => $f, 'source' => 'root'];
        }
    }
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// ========== UPLOAD ==========
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'อัปโหลดไม่สำเร็จ']);
        exit;
    }
    $file = $_FILES['file'];
    $allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/vnd.ms-excel' => 'xls', 'application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
    if (!isset($allowed[$file['type']])) {
        echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะ xlsx, xls, pdf, doc, docx']);
        exit;
    }
    $ext = $allowed[$file['type']];
    $origName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\p{L}\s]/u', '_', $origName);
    $filename = $safeName . '.' . $ext;
    $target = $manualRefsDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'บันทึกไฟล์ไม่สำเร็จ']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'คำสั่งไม่รู้จัก']);
