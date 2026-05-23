<?php
// Helper functions for export.php

function getAllDocuments($opts = []) {
    try {
        $db = getDB();
        // ดึงข้อมูลการตั้งค่าระบบจากตาราง settings แทน documents
        $sql = "SELECT * FROM settings ORDER BY id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllPeriods($opts = []) {
    try {
        $db = getDB();
        $sql = "SELECT * FROM assessment_periods ORDER BY year DESC, start_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllIndicators($opts = []) {
    try {
        $db = getDB();
        $sql = "SELECT * FROM indicators ORDER BY id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllUserAssessments($opts = []) {
    try {
        $db = getDB();
        $sql = "
            SELECT 
                u.id as user_id, u.name as user_name, u.email,
                c.company_name,
                ap.id as period_id, ap.name as period_name,
                a.id as assessment_id, a.final_score, a.status,
                s.id as score_id, s.indicator_id, s.self_score, s.auditor_score, s.is_na, s.self_evidence, s.auditor_comment,
                ind.name_th as indicator_name, p.name_th as pillar_name
            FROM users u
            JOIN companies c ON c.user_id = u.id
            JOIN assessments a ON a.company_id = c.id
            JOIN assessment_periods ap ON a.period_id = ap.id
            JOIN assessment_scores s ON s.assessment_id = a.id
            JOIN indicators ind ON s.indicator_id = ind.id
            JOIN pillars p ON ind.pillar_id = p.id
            ORDER BY u.id, a.id, s.id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// REMOVE getAllUsers here to avoid redeclare and always use the one from auth.php
if (function_exists('getAllUsers')) {
    // do nothing, use the one from auth.php
}

// ========== NEW OUTPUT MANAGEMENT EXPORT FUNCTIONS ==========

/**
 * Export Period Summary Report (สรุปผลการประเมินทั้งรอบ)
 */
function exportPeriodSummary($periodId) {
    try {
        $db = getDB()->getConnection();
        
        // Get period info
        $stmt = $db->prepare("SELECT * FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) {
            throw new Exception('Period not found');
        }

        // Get all completed assessments for this period
        $stmt = $db->prepare("
            SELECT a.*, c.company_name, c.company_name_en, c.industry_type,
                   COUNT(DISTINCT att.id) as file_count
            FROM assessments a
            LEFT JOIN companies c ON a.company_id = c.id
            LEFT JOIN assessment_scores s ON a.id = s.assessment_id
            LEFT JOIN attachments att ON s.id = att.assessment_score_id
            WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed')
            GROUP BY a.id
            ORDER BY a.final_score DESC
        ");
        $stmt->execute([$periodId]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create Excel file
        $filename = 'HICM_' . $period['year'] . '_' . str_replace(' ', '_', $period['name']) . '_Summary.csv';
        
        header('Content-Type: text/csv; charset=utf-8-sig');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        
        // Header
        fputcsv($output, ['รายงานสรุปผลการประเมิน HICM V2025']);
        fputcsv($output, ['รอบการประเมิน: ' . htmlspecialchars($period['name']) . ' (' . $period['year'] . ')']);
        fputcsv($output, ['วันสร้างรายงาน: ' . date('d/m/Y H:i:s')]);
        fputcsv($output, []);
        
        // Summary stats
        $totalScore = array_sum(array_column($assessments, 'final_score'));
        $avgScore = count($assessments) > 0 ? $totalScore / count($assessments) : 0;
        
        fputcsv($output, ['สถิติทั่วไป']);
        fputcsv($output, ['จำนวนสถานประกอบการทั้งหมด', count($assessments)]);
        fputcsv($output, ['คะแนนรวม', number_format($totalScore, 2)]);
        fputcsv($output, ['คะแนนเฉลี่ย', number_format($avgScore, 2)]);
        fputcsv($output, []);
        
        // Assessments table
        fputcsv($output, [
            'ลำดับที่',
            'สถานประกอบการ',
            'ประเภทอุตสาหกรรม',
            'คะแนน',
            'ระดับ HICM',
            'สถานะ',
            'ไฟล์แนบ',
            'วันที่อัปเดต'
        ]);
        
        $count = 1;
        foreach ($assessments as $assessment) {
            fputcsv($output, [
                $count++,
                htmlspecialchars($assessment['company_name']),
                htmlspecialchars($assessment['industry_type'] ?? '-'),
                number_format($assessment['final_score'] ?? 0, 2),
                htmlspecialchars($assessment['hicm_level'] ?? '-'),
                $assessment['status'],
                $assessment['file_count'],
                date('d/m/Y', strtotime($assessment['updated_at']))
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        setFlashMessage('Export failed: ' . $e->getMessage(), 'error');
        redirect(getBaseUrl() . '/pages/output-management.php');
    }
}

/**
 * Export Individual Assessment Detail Report
 */
function exportAssessmentDetail($assessmentId) {
    try {
        $db = getDB()->getConnection();
        
        // Get assessment with all details
        $stmt = $db->prepare("
            SELECT a.*, c.company_name, c.company_name_en, 
                   p.name as period_name, p.year
            FROM assessments a
            LEFT JOIN companies c ON a.company_id = c.id
            LEFT JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) {
            throw new Exception('Assessment not found');
        }

        // Get all scores with indicator details
        $stmt = $db->prepare("
            SELECT s.*, i.name_th, i.name_en, i.criteria_na,
                   pl.name_th as pillar_name, pl.icon
            FROM assessment_scores s
            LEFT JOIN indicators i ON s.indicator_id = i.id
            LEFT JOIN pillars pl ON i.pillar_id = pl.id
            WHERE s.assessment_id = ?
            ORDER BY pl.id, i.display_order
        ");
        $stmt->execute([$assessmentId]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create CSV
        $filename = 'HICM_' . $assessment['company_name'] . '_' . date('Ymd') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8-sig');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        
        // Header
        fputcsv($output, ['รายงานการประเมิน HICM V2025 - รายละเอียด']);
        fputcsv($output, []);
        fputcsv($output, ['สถานประกอบการ', htmlspecialchars($assessment['company_name'])]);
        fputcsv($output, ['รอบการประเมิน', htmlspecialchars($assessment['period_name']) . ' (' . $assessment['year'] . ')']);
        fputcsv($output, ['คะแนนรวม', number_format($assessment['final_score'] ?? 0, 2)]);
        fputcsv($output, ['ระดับ HICM', htmlspecialchars($assessment['hicm_level'] ?? '-')]);
        fputcsv($output, ['สถานะ', $assessment['status']]);
        fputcsv($output, ['วันที่สร้างรายงาน', date('d/m/Y H:i:s', strtotime($assessment['created_at']))]);
        fputcsv($output, []);
        
        // Scores detail
        $currentPillar = '';
        fputcsv($output, ['หมวด', 'ข้อที่', 'ตัวชี้วัด', 'คะแนนตนเอง', 'คะแนนผู้ประเมิน', 'หมายเหตุ', 'ความคิดเห็น']);
        
        foreach ($scores as $score) {
            if ($currentPillar !== $score['pillar_name']) {
                $currentPillar = $score['pillar_name'];
                fputcsv($output, [str_repeat('=', 60)]);
                fputcsv($output, [$score['pillar_name']]);
                fputcsv($output, [str_repeat('=', 60)]);
            }
            
            $selfScore = $score['is_na'] ? 'N/A' : $score['self_score'];
            $auditorScore = $score['is_na'] ? 'N/A' : $score['auditor_score'];
            
            fputcsv($output, [
                '',
                $score['indicator_id'],
                htmlspecialchars($score['name_th']),
                $selfScore,
                $auditorScore,
                htmlspecialchars($score['self_evidence'] ?? '-'),
                htmlspecialchars($score['auditor_comment'] ?? '-')
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        setFlashMessage('Export failed: ' . $e->getMessage(), 'error');
        redirect(getBaseUrl() . '/pages/output-management.php');
    }
}

/**
 * Export All Period Reports as ZIP (สำหรับ batch download ทั้งรอบ)
 */
function exportPeriodAllReports($periodId) {
    try {
        $db = getDB()->getConnection();
        
        // Get period info
        $stmt = $db->prepare("SELECT * FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) {
            throw new Exception('Period not found');
        }

        // Get all completed assessments
        $stmt = $db->prepare("
            SELECT a.id, c.company_name
            FROM assessments a
            LEFT JOIN companies c ON a.company_id = c.id
            WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed')
            ORDER BY c.company_name
        ");
        $stmt->execute([$periodId]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/hicm_export_' . time();
        mkdir($tempDir);

        // Create individual reports
        foreach ($assessments as $assessment) {
            // Export each assessment and save to temp dir
            ob_start();
            
            // Run export logic without headers (we'll save to files)
            $stmt = $db->prepare("
                SELECT a.*, c.company_name, c.company_name_en, 
                       p.name as period_name, p.year
                FROM assessments a
                LEFT JOIN companies c ON a.company_id = c.id
                LEFT JOIN assessment_periods p ON a.period_id = p.id
                WHERE a.id = ?
            ");
            $stmt->execute([$assessment['id']]);
            $assData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get scores
            $stmt = $db->prepare("
                SELECT s.*, i.name_th, i.name_en,
                       pl.name_th as pillar_name
                FROM assessment_scores s
                LEFT JOIN indicators i ON s.indicator_id = i.id
                LEFT JOIN pillars pl ON i.pillar_id = pl.id
                WHERE s.assessment_id = ?
                ORDER BY pl.id, i.display_order
            ");
            $stmt->execute([$assessment['id']]);
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build CSV content
            $csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM
            $csvLines = [];
            
            $csvLines[] = '"รายงานการประเมิน HICM V2025"';
            $csvLines[] = '""';
            $csvLines[] = '"สถานประกอบการ","' . str_replace('"', '""', $assData['company_name']) . '"';
            $csvLines[] = '"รอบการประเมิน","' . str_replace('"', '""', $assData['period_name']) . ' (' . $assData['year'] . ')"';
            $csvLines[] = '"คะแนนรวม","' . number_format($assData['final_score'] ?? 0, 2) . '"';
            $csvLines[] = '""';
            
            $csvContent .= implode("\r\n", $csvLines) . "\r\n";
            
            // Save file
            $filename = $tempDir . '/' . preg_replace('/[^a-z0-9\-_]/i', '_', $assData['company_name']) . '.csv';
            file_put_contents($filename, $csvContent);
        }

        // Create ZIP
        $zipFile = sys_get_temp_dir() . '/HICM_' . $period['year'] . '_' . time() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            throw new Exception('Cannot create ZIP file');
        }

        // Add files to ZIP
        foreach (glob($tempDir . '/*.csv') as $file) {
            $zip->addFile($file, basename($file));
        }
        
        $zip->close();

        // Send ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="HICM_' . $period['year'] . '_' . $period['name'] . '.zip"');
        readfile($zipFile);

        // Cleanup
        array_map('unlink', glob($tempDir . '/*.csv'));
        rmdir($tempDir);
        unlink($zipFile);
        
        exit;
    } catch (Exception $e) {
        setFlashMessage('Export failed: ' . $e->getMessage(), 'error');
        redirect(getBaseUrl() . '/pages/output-management.php');
    }
}

// --- Auto Test ---
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "\n[Auto Test: export_helpers.php]\n";
    $types = ['documents','periods','indicators','user_assessments','users'];
    foreach ($types as $type) {
        $fn = 'getAll' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));
        if (function_exists($fn)) {
            $result = $fn();
            echo "Test $fn: ";
            if (is_array($result)) {
                echo "OK (" . count($result) . " rows)\n";
            } else {
                echo "FAIL\n";
            }
        } else {
            echo "Function $fn does not exist\n";
        }
    }
}
