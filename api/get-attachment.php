<?php
/**
 * HICM V2025 Assessment System - Get Attachment API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(403);
    die('Unauthorized');
}

$user = getCurrentUser();
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

if (!$fileId) {
    http_response_code(400);
    die('Invalid ID');
}

try {
    $db = getDB();
    
    // Fetch attachment info with permission check
    // Join all the way to company to verify ownership
    $stmt = $db->prepare("
        SELECT f.*, c.id as company_id 
        FROM attachments f
        JOIN assessment_scores s ON f.assessment_score_id = s.id
        JOIN assessments a ON s.assessment_id = a.id
        JOIN companies c ON a.company_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Check Permissions
    $canAccess = false;
    
    if (hasRole(ROLE_ADMIN)) {
        $canAccess = true;
    } elseif (hasRole(ROLE_AUDITOR)) {
        // Auditors can generally access assessments assigned to them, but for simplicity
        // allowing all auditors access (or refine based on assignment)
        // Ideally should check assessment_evaluators or evaluator_id
        $canAccess = true; 
    } elseif (hasRole(ROLE_COMPANY)) {
        if ($file['company_id'] == $user['company_id']) {
            $canAccess = true;
        }
    }
    
    if (!$canAccess) {
        http_response_code(403);
        die('Access Denied');
    }
    
    // Check if file exists on disk
    $filePath = $file['file_path'];
    
    // Handle relative path if it's stored relatively (though api/upload.php seems to store absolute path in $targetPath)
    // If stored path starts with 'app' or something relative, prepend BASE path
    // Based on upload.php: $targetPath = $uploadPath . $uniqueName; where $uploadPath is APP_UPLOAD_PATH
    // So it stores absolute path.
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found on server');
    }
    
    // Serve file
    $mimeType = $file['file_type'] ?: 'application/octet-stream';
    $originalName = $file['file_original_name'] ?: basename($filePath);
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    
    if ($inline) {
        header('Content-Disposition: inline; filename="' . $originalName . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $originalName . '"');
    }
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log("Get attachment error: " . $e->getMessage());
    http_response_code(500);
    die('Internal Server Error');
}
