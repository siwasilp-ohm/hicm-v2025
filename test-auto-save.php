<?php
/**
 * Test script for Auditor Auto-Save and Submit
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/assessment.php';

// Find a valid auditor and assessment assignment
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT user_id, assessment_id FROM assessment_evaluators LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "No valid assignment found in assessment_evaluators table.\n";
    exit;
}

$auditorId = $row['user_id'];
$assessmentId = $row['assessment_id'];

// Mock session for testing
$_SESSION['user_id'] = $auditorId; 
$_SESSION['user_role'] = ROLE_AUDITOR;
$_SESSION['user_name'] = 'Test Auditor';
$_SESSION['user_username'] = 'auditor1';

echo "Testing for Auditor ID: $auditorId on Assessment ID: $assessmentId\n";

function testSave($assessmentId) {
    echo "Testing save-auditor-score.php...\n";
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT indicator_id FROM assessment_scores WHERE assessment_id = ? LIMIT 1");
    $stmt->execute([$assessmentId]);
    $indicatorId = $stmt->fetchColumn();
    
    // Test parameters
    $score = 0.75;
    $comment = "Test comment " . date('Y-m-d H:i:s');
    
    // Simulate POST
    $_POST['assessment_id'] = $assessmentId;
    $_POST['indicator_id'] = $indicatorId;
    $_POST['score'] = $score;
    $_POST['comment'] = $comment;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    include __DIR__ . '/api/save-auditor-score.php';
    $output = ob_get_clean();
    
    echo "Output: " . $output . "\n";
    $data = json_decode($output, true);
    if ($data && $data['success']) {
        echo "SUCCESS: Score saved.\n";
    } else {
        echo "FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
}

function testSubmit() {
    echo "\nTesting submit-auditor-evaluation.php...\n";
    
    // Find a valid assessment
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT assessment_id FROM assessment_evaluators LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo "No valid assessment found for testing.\n";
        return;
    }
    
    $assessmentId = $row['assessment_id'];
    
    // Simulate POST
    $_POST['assessment_id'] = $assessmentId;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    include __DIR__ . '/api/submit-auditor-evaluation.php';
    $output = ob_get_clean();
    
    echo "Output: " . $output . "\n";
    $data = json_decode($output, true);
    if ($data && $data['success']) {
        echo "SUCCESS: Evaluation submitted.\n";
    } else {
        echo "FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
}

testSave($assessmentId);
testSubmit($assessmentId);
