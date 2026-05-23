<?php
/**
 * API to fetch data for export preview
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';
require_once __DIR__ . '/../includes/companies.php';
require_once __DIR__ . '/../includes/export_helpers.php'; // Added this line
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$filters = $_GET['filters'] ?? [];
$data = [];

try {
    switch ($type) {
        case 'assessments':
            $data = getAllAssessments($filters);
            break;
        case 'companies':
            $data = getAllCompanies($filters);
            break;
        case 'users':
            $data = getAllUsers($filters);
            break;
        case 'documents':
            $data = function_exists('getAllDocuments') ? getAllDocuments($filters) : [];
            break;
        case 'periods':
            $data = function_exists('getAllPeriods') ? getAllPeriods($filters) : [];
            break;
        case 'indicators':
            $data = function_exists('getAllIndicators') ? getAllIndicators($filters) : [];
            break;
        case 'user_assessments':
            $data = function_exists('getAllUserAssessments') ? getAllUserAssessments($filters) : [];
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid data type: ' . $type]);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
