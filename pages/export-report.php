<?php
/**
 * HICM V2025 Assessment System - Single Assessment Export
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();

$assessmentId = $_GET['id'] ?? null;
$format = $_GET['format'] ?? 'excel';

if (!$assessmentId) {
    die('Invalid ID');
}

$assessment = getAssessmentWithScores($assessmentId);
if (!$assessment) {
    die('Assessment not found');
}

if ($format === 'excel') {
    $filename = 'HICM_Report_' . str_replace(' ', '_', $assessment['company_name']) . '_' . $assessment['year'] . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
    
    // Header Info
    fputcsv($output, ['รายงานผลการประเมิน HICM V2025']);
    fputcsv($output, ['สถานประกอบการ:', $assessment['company_name']]);
    fputcsv($output, ['รอบการประเมิน:', $assessment['year'] . ' (' . $assessment['period_name'] . ')']);
    fputcsv($output, ['คะแนนรวม:', number_format($assessment['final_score'], 2)]);
    fputcsv($output, ['ระดับการรับรอง:', getHICMLevelName($assessment['hicm_level'])['name']]);
    fputcsv($output, []); // Empty line
    
    // Pillars Data
    foreach ($assessment['pillars'] as $pillar) {
        fputcsv($output, ['หมวด:', $pillar['code'] . ' ' . $pillar['name']]);
        fputcsv($output, ['รหัส', 'ตัวชี้วัด', 'คะแนนตนเอง', 'คะแนนผู้ตรวจสอบ', 'หลักฐาน/ความเห็น']);
        
        foreach ($pillar['indicators'] as $ind) {
            fputcsv($output, [
                $ind['indicator_code'],
                $ind['indicator_name'],
                $ind['is_na'] ? 'N/A' : number_format($ind['self_score'], 2),
                $ind['auditor_is_na'] ? 'N/A' : ($ind['auditor_score'] !== null ? number_format($ind['auditor_score'], 2) : '-'),
                $ind['self_evidence'] ?: $ind['auditor_comment']
            ]);
        }
        fputcsv($output, []); // Empty line
    }
    
    fclose($output);
    exit;
} else if ($format === 'pdf') {
    // Redirect to assessment-view page with pdf=1 parameter to trigger client-side PDF download
    header('Location: assessment-view.php?id=' . $assessmentId . '&pdf=1');
    exit;
}
