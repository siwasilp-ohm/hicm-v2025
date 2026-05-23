<?php
/**
 * HICM V2025 Assessment System - File Download/View API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header("HTTP/1.1 401 Unauthorized");
    exit('Unauthorized');
}

$fileId = intval($_GET['id'] ?? 0);
$isView = intval($_GET['view'] ?? 0);

if (!$fileId) {
    header("HTTP/1.1 404 Not Found");
    exit('File ID required');
}

try {
    $db = getDB();
    
    // Check if user has permission to view this file
    // For now, any logged in user can view if they have the ID, 
    // but ideally check company_id for ROLE_COMPANY
    $user = getCurrentUser();
    
    if ($user['role'] === ROLE_COMPANY) {
        $stmt = $db->prepare("
            SELECT a.* FROM attachments a
            JOIN assessment_scores s ON a.assessment_score_id = s.id
            JOIN assessments asses ON s.assessment_id = asses.id
            WHERE a.id = ? AND asses.company_id = ?
        ");
        $stmt->execute([$fileId, $user['company_id']]);
    } else {
        // Auditors and Admins can view any file
        $stmt = $db->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$fileId]);
    }
    
    $file = $stmt->fetch();
    
    if (!$file) {
        header("HTTP/1.1 404 Not Found");
        exit('File not found or access denied');
    }
    
    $filePath = $file['file_path'];
    
    if (!file_exists($filePath)) {
        header("HTTP/1.1 404 Not Found");
        exit('Physical file not found');
    }
    
    // Set headers
    header('Content-Type: ' . $file['file_type']);
    header('Content-Length: ' . filesize($filePath));
    
    if ($isView) {
        header('Content-Disposition: inline; filename="' . $file['file_original_name'] . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $file['file_original_name'] . '"');
    }
    
    // Read and output file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    exit('Error: ' . $e->getMessage());
}
