<?php
/**
 * HICM V2025 Assessment System - Save Assessment API
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

$user = getCurrentUser();

// Get JSON data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

// Get or create assessment for this company
$assessmentResult = getOrCreateAssessment($user['company_id']);
if (!$assessmentResult['success']) {
    echo json_encode(['success' => false, 'message' => $assessmentResult['message']]);
    exit;
}

$assessmentId = $assessmentResult['assessment']['id'];

try {
    // Collect indicators to update
    $updates = [];
    foreach ($data as $key => $value) {
        if (strpos($key, 'score_') === 0) {
            $id = intval(substr($key, 6));
            if (!isset($updates[$id])) $updates[$id] = ['score' => 0, 'evidence' => '', 'is_na' => 0];
            
            if ($value === 'na') {
                $updates[$id]['is_na'] = 1;
                $updates[$id]['score'] = 0;
            } else {
                $updates[$id]['is_na'] = 0;
                $updates[$id]['score'] = floatval($value);
            }
        } elseif (strpos($key, 'evidence_') === 0) {
            $id = intval(substr($key, 9));
            if (!isset($updates[$id])) $updates[$id] = ['score' => 0, 'evidence' => '', 'is_na' => 0];
            $updates[$id]['evidence'] = sanitizeInput($value);
        }
    }
    
    // Save each update
    foreach ($updates as $indicatorId => $update) {
        saveSelfScore($assessmentId, $indicatorId, $update['score'], $update['evidence'], $update['is_na']);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
