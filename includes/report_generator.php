<?php
/**
 * Report Generator - HICM V2025
 * สร้างรายงานสรุปการประเมินในรูปแบบต่าง ๆ (PDF, Excel, CSV)
 */

class HICMReportGenerator {
    private $assessment;
    private $scores;
    private $period;
    private $company;
    private $indicatorCountPerPillar = [];
    private $evaluators = [];        // All assigned evaluators from assessment_evaluators
    private $evaluatorScores = [];   // Per-evaluator scores from assessment_evaluator_scores
    
    public function __construct($assessmentId) {
        $this->loadAssessmentData($assessmentId);
        $this->calculateIndicatorCounts();
    }
    
    private function calculateIndicatorCounts() {
        $counts = [];
        foreach ($this->scores as $score) {
            $pillarId = $score['pillar_id'];
            $indicatorId = $score['indicator_id'];
            
            if (!isset($counts[$pillarId])) {
                $counts[$pillarId] = [];
            }
            
            if (!in_array($indicatorId, $counts[$pillarId])) {
                $counts[$pillarId][] = $indicatorId;
            }
        }
        
        // Convert to count
        foreach ($counts as $pillarId => $indicators) {
            $this->indicatorCountPerPillar[$pillarId] = count($indicators);
        }
    }
    
    private function loadAssessmentData($assessmentId) {
        try {
            $db = getDB()->getConnection();
            
            // Get assessment
            $stmt = $db->prepare("
                SELECT a.*, c.company_name, c.company_name_en, c.address, c.province,
                       p.name as period_name, p.year
                FROM assessments a
                LEFT JOIN companies c ON a.company_id = c.id
                LEFT JOIN assessment_periods p ON a.period_id = p.id
                WHERE a.id = ?
            ");
            $stmt->execute([$assessmentId]);
            $this->assessment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->assessment) {
                throw new Exception('Assessment not found');
            }
            
            // Get all scores with indicators (aggregate level)
            $stmt = $db->prepare("
                SELECT s.*, i.name_th, i.name_en, i.code,
                       pl.id as pillar_id, pl.name_th as pillar_name, pl.icon, pl.weight as pillar_weight
                FROM assessment_scores s
                LEFT JOIN indicators i ON s.indicator_id = i.id
                LEFT JOIN pillars pl ON i.pillar_id = pl.id
                WHERE s.assessment_id = ?
                ORDER BY pl.id, i.display_order
            ");
            $stmt->execute([$assessmentId]);
            $this->scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all assigned evaluators with per-pillar weighted scores
            $stmtEval = $db->prepare("
                SELECT ae.assessment_id, ae.user_id, u.name as auditor_name, u.email as auditor_email,
                       ae.submitted_at,
                       p.id as pillar_id, p.code as pillar_code, p.weight as pillar_weight,
                       COUNT(CASE WHEN es.is_na = 0 THEN es.id END) as scored_count,
                       COUNT(CASE WHEN es.is_na = 1 THEN es.id END) as na_count,
                       ROUND(SUM(CASE WHEN es.is_na = 0 THEN es.score ELSE 0 END), 2) as pillar_raw_sum
                FROM assessment_evaluators ae
                JOIN users u ON ae.user_id = u.id
                LEFT JOIN assessment_evaluator_scores es ON es.assessment_id = ae.assessment_id AND es.user_id = ae.user_id
                LEFT JOIN indicators i ON es.indicator_id = i.id
                LEFT JOIN pillars p ON i.pillar_id = p.id
                WHERE ae.assessment_id = ?
                GROUP BY ae.assessment_id, ae.user_id, u.name, u.email, ae.submitted_at, p.id, p.code, p.weight
                ORDER BY ae.assigned_at, p.id
            ");
            $stmtEval->execute([$assessmentId]);
            $rawEvaluators = $stmtEval->fetchAll(PDO::FETCH_ASSOC);
            
            // Aggregate per-evaluator weighted totals
            $evalTemp = [];
            foreach ($rawEvaluators as $row) {
                $key = $row['user_id'];
                if (!isset($evalTemp[$key])) {
                    $evalTemp[$key] = [
                        'user_id' => $row['user_id'],
                        'name' => $row['auditor_name'],
                        'email' => $row['auditor_email'],
                        'submitted_at' => $row['submitted_at'],
                        'total_scored' => 0,
                        'total_na' => 0,
                        'weighted_total' => 0,
                    ];
                }
                if ($row['pillar_code'] && $row['scored_count'] > 0) {
                    $weighted = ($row['pillar_raw_sum'] / $row['scored_count']) * $row['pillar_weight'];
                    $evalTemp[$key]['weighted_total'] += $weighted;
                    $evalTemp[$key]['total_scored'] += $row['scored_count'];
                }
                $evalTemp[$key]['total_na'] += $row['na_count'];
            }
            foreach ($evalTemp as &$ev) {
                $ev['weighted_total'] = round($ev['weighted_total'], 2);
            }
            $this->evaluators = array_values($evalTemp);
            
            // Get all individual evaluator scores with comments
            $stmtES = $db->prepare("
                SELECT es.assessment_id, es.indicator_id, es.user_id, es.score, es.comment, es.is_na,
                       u.name as auditor_name, i.code as indicator_code, i.name_th as indicator_name
                FROM assessment_evaluator_scores es
                JOIN users u ON es.user_id = u.id
                JOIN indicators i ON es.indicator_id = i.id
                WHERE es.assessment_id = ?
                ORDER BY i.pillar_id, i.display_order, u.name
            ");
            $stmtES->execute([$assessmentId]);
            $this->evaluatorScores = $stmtES->fetchAll(PDO::FETCH_ASSOC);
            
            $this->period = $this->assessment;
            $this->company = $this->assessment;
        } catch (Exception $e) {
            throw new Exception('Failed to load assessment data: ' . $e->getMessage());
        }
    }
    
    /**
     * Export to CSV format
     */
    public function exportCSV() {
        header('Content-Type: text/csv; charset=utf-8-sig');
        header('Content-Disposition: attachment; filename="' . $this->getReportFilename('csv') . '"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        
        $this->writeCSVContent($output);
        fclose($output);
    }
    
    /**
     * Export to HTML/Preview
     */
    public function getHTMLPreview() {
        return $this->generateHTMLReport();
    }
    
    /**
     * Generate Excel file using CSV-like format (for Excel import)
     */
    public function exportExcel() {
        $filename = $this->getReportFilename('xlsx');
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Generate Excel-friendly CSV
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        
        $this->writeExcelContent($output);
        fclose($output);
    }
    
    /**
     * Export to PDF (using dompdf or simple HTML render)
     */
    public function exportPDF() {
        $filename = $this->getReportFilename('pdf');
        $html = $this->generateHTMLReport();
        
        // Try using dompdf if available, otherwise use simple method
        if ($this->useDompdf($html, $filename)) {
            return; // dompdf handles output and exit
        }
        
        // Fallback: create simple PDF-like output
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Convert HTML to simple text PDF
        $pdf_content = $this->htmlToPdf($html);
        echo $pdf_content;
        exit;
    }
    
    /**
     * Try to use dompdf library if available
     */
    private function useDompdf($html, $filename) {
        $dompdf_path = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($dompdf_path)) {
            try {
                require_once $dompdf_path;
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $dompdf->output();
                exit;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }
    
    /**
     * Simple HTML to PDF conversion (basic implementation)
     * Creates a PDF-like format by rendering HTML directly
     */
    private function htmlToPdf($html) {
        // Remove HTML tags but preserve content
        $text = strip_tags($html);
        
        // Create simple PDF header
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n";
        $pdf .= "2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n";
        $pdf .= "3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources <</Font <</F1 5 0 R>>>>>>\nendobj\n";
        
        // Encode text content
        $encoded_text = $this->encodePdfText($text);
        $content = "BT /F1 12 Tf 50 750 Td (" . $encoded_text . ") Tj ET";
        $content_len = strlen($content);
        
        $pdf .= "4 0 obj\n<</Length $content_len>>\nstream\n$content\nendstream\nendobj\n";
        $pdf .= "5 0 obj\n<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>\nendobj\n";
        $pdf .= "xref\n0 6\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000244 00000 n\n";
        $pdf .= sprintf("%010d", strlen($pdf)) . " 00000 n\ntrailer\n<</Size 6 /Root 1 0 R>>\nstartxref\n";
        $pdf .= strlen($pdf) . "\n%%EOF";
        
        return $pdf;
    }
    
    /**
     * Encode text for PDF
     */
    private function encodePdfText($text) {
        // Remove newlines and extra spaces
        $text = str_replace(["\r", "\n", "\t"], " ", $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Escape special characters for PDF
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        
        // Limit to first 500 chars
        return substr($text, 0, 500);
    }
    
    /**
     * Generate HTML Report
     */
    private function generateHTMLReport() {
        $assessment = $this->assessment;
        $scores = $this->scores;
        
        // Group scores by pillar with weight information and count unique indicators
        $pillars = [];
        foreach ($scores as $score) {
            $pillarName = $score['pillar_name'] ?? 'Unknown';
            if (!isset($pillars[$pillarName])) {
                $pillars[$pillarName] = [
                    'pillar_id' => $score['pillar_id'] ?? null,
                    'weight' => $score['pillar_weight'] ?? 0,
                    'indicator_ids' => [],
                    'scores' => []
                ];
            }
            // Track unique indicators
            if (!in_array($score['indicator_id'], $pillars[$pillarName]['indicator_ids'])) {
                $pillars[$pillarName]['indicator_ids'][] = $score['indicator_id'];
            }
            $pillars[$pillarName]['scores'][] = $score;
        }
        
        $html = '<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานการประเมิน HICM V2025</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Prompt", sans-serif; color: #333; line-height: 1.6; }
        .page { max-width: 210mm; margin: 0 auto; padding: 20mm; background: white; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 3px solid #1e293b; padding-bottom: 1rem; }
        .header h1 { font-size: 1.8rem; color: #1e293b; margin-bottom: 0.5rem; }
        .header p { color: #64748b; font-size: 0.95rem; }
        .company-info { background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border-left: 4px solid #3b82f6; }
        .info-row { display: flex; margin-bottom: 0.75rem; }
        .info-label { font-weight: 600; width: 150px; color: #475569; }
        .info-value { flex: 1; color: #1e293b; }
        .scores-section { margin-bottom: 2rem; }
        .pillar-title { font-size: 1.3rem; font-weight: 700; color: #fff; background: #3b82f6; padding: 0.75rem 1rem; margin-bottom: 0; border-radius: 0.5rem 0.5rem 0 0; }
        .pillar-summary { display: flex; padding: 1rem; background: #dbeafe; border-bottom: 2px solid #60a5fa; align-items: center; gap: 2rem; }
        .pillar-summary-label { font-weight: 700; color: #1e40af; min-width: 200px; font-size: 0.9rem; }
        .pillar-score-cell { flex: 1; padding: 0.5rem 1rem; background: white; border-radius: 0.5rem; border-left: 3px solid #3b82f6; font-weight: 600; color: #1e40af; font-size: 0.9rem; }
        .indicator-header { display: flex; padding: 0.75rem 1rem; background: #60a5fa; color: white; font-weight: 700; font-size: 0.9rem; border-bottom: 2px solid #3b82f6; }
        .indicator-header-code { width: 60px; }
        .indicator-header-name { flex: 1; padding: 0 1rem; }
        .indicator-header-self { width: 100px; text-align: center; }
        .indicator-header-auditor { width: 100px; text-align: center; }
        .indicator-row { display: flex; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; align-items: center; background: white; }
        .indicator-row:nth-child(odd) { background: #f8fafc; }
        .indicator-code { font-weight: 600; color: #3b82f6; width: 60px; }
        .indicator-name { flex: 1; padding: 0 1rem; }
        .score-cell { width: 100px; text-align: center; font-weight: 600; }
        .score-na { color: #94a3b8; }
        .score-value { color: #10b981; }
        .summary-section { background: #f0fdf4; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border-left: 4px solid #10b981; }
        .summary-row { display: flex; margin-bottom: 0.75rem; }
        .summary-label { font-weight: 600; width: 200px; }
        .summary-value { font-weight: 700; font-size: 1.1rem; color: #3b82f6; }
        .auditor-section { background: #eff6ff; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border-left: 4px solid #06b6d4; }
        .auditor-title { font-weight: 700; font-size: 1.1rem; color: #0369a1; margin-bottom: 1rem; }
        .auditor-header { display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 1fr; gap: 1rem; background: #0ea5e9; color: white; padding: 0.75rem 1rem; border-radius: 0.5rem 0.5rem 0 0; font-weight: 700; font-size: 0.9rem; }
        .auditor-header-cell { text-align: left; }
        .auditor-list { display: flex; flex-direction: column; border: 1px solid #bae6fd; border-top: none; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden; }
        .auditor-row { display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 1fr; gap: 1rem; padding: 0.75rem 1rem; border-bottom: 1px solid #bae6fd; align-items: center; background: white; }
        .auditor-row:nth-child(even) { background: #f0f9ff; }
        .auditor-row:last-child { border-bottom: none; }
        .auditor-name-cell { font-weight: 600; color: #1e293b; }
        .auditor-email-cell { color: #64748b; font-size: 0.9rem; }
        .auditor-score-cell { text-align: center; font-weight: 600; color: #0369a1; }
        .auditor-count-cell { text-align: center; color: #475569; }
        .comments-section { margin-bottom: 2rem; }
        .comments-title { font-size: 1.2rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; }
        .comment-block { margin-bottom: 1.5rem; }
        .comment-indicator { background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6; margin-bottom: 0.75rem; }
        .comment-indicator-title { font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .comment-indicator-code { font-size: 0.85rem; color: #64748b; }
        .company-comment { background: #fffbeb; padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 0.5rem; border-left: 3px solid #f59e0b; }
        .company-comment-label { font-weight: 600; color: #92400e; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .company-comment-text { color: #451a03; font-size: 0.9rem; line-height: 1.5; }
        .auditor-comment { background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #0ea5e9; }
        .auditor-comment-label { font-weight: 600; color: #0369a1; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .auditor-comment-text { color: #0c2d6b; font-size: 0.9rem; line-height: 1.5; }
        .no-comments { color: #94a3b8; font-size: 0.9rem; font-style: italic; padding: 0.5rem; }
        .signature-section { margin-top: 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .signature-box { border-top: 2px solid #1e293b; padding-top: 3rem; text-align: center; }
        .signature-title { font-weight: 600; margin-top: 1rem; font-size: 0.9rem; }
        .signature-date { color: #64748b; font-size: 0.85rem; margin-top: 0.5rem; }
        .level-badge { display: inline-block; padding: 0.5rem 1.5rem; border-radius: 99px; font-weight: 700; font-size: 0.9rem; }
        .level-1 { background: #fecaca; color: #991b1b; }
        .level-2 { background: #fde047; color: #854d0e; }
        .level-3 { background: #bbf7d0; color: #065f46; }
        .level-4 { background: #bfdbfe; color: #1e40af; }
        .level-5 { background: #c084fc; color: #581c87; }
        @media print { body { margin: 0; padding: 0; } .page { margin: 0; padding: 15mm; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>📋 รายงานการประเมิน HICM V2025</h1>
            <p>Health, Safety & Community Management System Assessment Report</p>
        </div>
        
        <div class="company-info">
            <div class="info-row">
                <span class="info-label">สถานประกอบการ:</span>
                <span class="info-value">' . htmlspecialchars($assessment['company_name']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">ที่อยู่:</span>
                <span class="info-value">' . htmlspecialchars($assessment['address'] ?? '-') . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">จังหวัด:</span>
                <span class="info-value">' . htmlspecialchars($assessment['province'] ?? '-') . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">รอบการประเมิน:</span>
                <span class="info-value">' . htmlspecialchars($assessment['period_name']) . ' (' . $assessment['year'] . ')</span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานะ:</span>
                <span class="info-value">' . $this->getStatusBadge($assessment['status']) . '</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-row">
                <span class="summary-label">คะแนนรวม:</span>
                <span class="summary-value">' . number_format($assessment['final_score'] ?? 0, 2) . ' / 1000</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">ระดับ HICM:</span>
                <span class="summary-value">
                    <span class="level-badge level-' . ($assessment['hicm_level'] ?? 1) . '">
                        Level ' . ($assessment['hicm_level'] ?? 1) . '
                    </span>
                </span>
            </div>
        </div>
        
        ' . $this->generateAuditorInfoHTML() . '
        
        ' . $this->generateScoresHTML($pillars) . '
        
        ' . $this->generateCommentsHTML() . '
        
        <div class="signature-section">
            <div class="signature-box">
                <div>ลายเซ็นผู้ประเมิน</div>
                <div class="signature-title">Auditor Signature</div>
                <div class="signature-date">วันที่: _______________</div>
            </div>
            <div class="signature-box">
                <div>ลายเซ็นผู้อนุมัติ</div>
                <div class="signature-title">Approval Signature</div>
                <div class="signature-date">วันที่: _______________</div>
            </div>
        </div>
        
        <div style="margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 0.8rem;">
            <p>รายงานนี้สร้างโดยระบบ HICM V2025 เมื่อ ' . date('d/m/Y H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate auditor information HTML
     */
    private function generateAuditorInfoHTML() {
        if (empty($this->evaluators)) {
            return '';
        }
        
        // Build HTML with table structure
        $html = '<div class="auditor-section">';
        $html .= '<div class="auditor-title">👥 ข้อมูลกรรมการประเมิน (' . count($this->evaluators) . ' คน)</div>';
        
        // Header row
        $html .= '<div class="auditor-header">';
        $html .= '<div class="auditor-header-cell">ชื่อกรรมการประเมิน</div>';
        $html .= '<div class="auditor-header-cell">อีเมล</div>';
        $html .= '<div class="auditor-header-cell" style="text-align: center;">คะแนนถ่วงน้ำหนัก</div>';
        $html .= '<div class="auditor-header-cell" style="text-align: center;">สถานะ</div>';
        $html .= '</div>';
        
        // Data rows
        $html .= '<div class="auditor-list">';
        
        foreach ($this->evaluators as $evaluator) {
            $statusLabel = '';
            if ($evaluator['total_scored'] > 0) {
                $statusLabel = '✅ ประเมินแล้ว ' . $evaluator['total_scored'] . ' ข้อ';
                if ($evaluator['total_na'] > 0) {
                    $statusLabel .= ' / N/A ' . $evaluator['total_na'] . ' ข้อ';
                }
            } else {
                $statusLabel = '⏳ ยังไม่ประเมิน';
            }
            
            $html .= '<div class="auditor-row">';
            $html .= '<div class="auditor-name-cell">👨‍💼 ' . htmlspecialchars($evaluator['name']) . '</div>';
            $html .= '<div class="auditor-email-cell">' . htmlspecialchars($evaluator['email']) . '</div>';
            $html .= '<div class="auditor-score-cell">' . ($evaluator['total_scored'] > 0 ? number_format($evaluator['weighted_total'], 2) . ' / 1,000' : '-') . '</div>';
            $html .= '<div class="auditor-count-cell">' . $statusLabel . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate comments HTML - showing company self-evidence and all evaluator comments
     */
    private function generateCommentsHTML() {
        // Build self-evidence map from assessment_scores
        $selfComments = [];
        foreach ($this->scores as $score) {
            if (!empty($score['self_evidence'])) {
                $selfComments[$score['indicator_id']] = [
                    'code' => $score['code'] ?? '',
                    'name' => $score['name_th'] ?? '',
                    'evidence' => $score['self_evidence'],
                ];
            }
        }
        
        // Build evaluator comments map from assessment_evaluator_scores
        $auditorComments = []; // indicator_id => [ { auditor_name, comment } ]
        foreach ($this->evaluatorScores as $es) {
            if (!empty($es['comment'])) {
                $indicatorId = $es['indicator_id'];
                if (!isset($auditorComments[$indicatorId])) {
                    $auditorComments[$indicatorId] = [
                        'code' => $es['indicator_code'] ?? '',
                        'name' => $es['indicator_name'] ?? '',
                        'comments' => [],
                    ];
                }
                $auditorComments[$indicatorId]['comments'][] = [
                    'auditor_name' => $es['auditor_name'],
                    'comment' => $es['comment'],
                ];
            }
        }
        
        // Also pick up auditor_comment from assessment_scores if evaluator_scores is empty
        if (empty($auditorComments)) {
            foreach ($this->scores as $score) {
                if (!empty($score['auditor_comment'])) {
                    $indicatorId = $score['indicator_id'];
                    if (!isset($auditorComments[$indicatorId])) {
                        $auditorComments[$indicatorId] = [
                            'code' => $score['code'] ?? '',
                            'name' => $score['name_th'] ?? '',
                            'comments' => [],
                        ];
                    }
                    $auditorComments[$indicatorId]['comments'][] = [
                        'auditor_name' => 'กรรมการ',
                        'comment' => $score['auditor_comment'],
                    ];
                }
            }
        }
        
        // Merge all indicator ids that have any comment
        $allIndicatorIds = array_unique(array_merge(array_keys($selfComments), array_keys($auditorComments)));
        sort($allIndicatorIds);
        
        if (empty($allIndicatorIds)) {
            return '';
        }
        
        // Build indicator info lookup from scores
        $indicatorInfo = [];
        foreach ($this->scores as $score) {
            $indicatorInfo[$score['indicator_id']] = [
                'code' => $score['code'] ?? '',
                'name' => $score['name_th'] ?? '',
            ];
        }
        
        $html = '<div class="comments-section">';
        $html .= '<div class="comments-title">💬 ข้อเสนอแนะและความเห็น</div>';
        
        foreach ($allIndicatorIds as $indicatorId) {
            $info = $indicatorInfo[$indicatorId] ?? $selfComments[$indicatorId] ?? $auditorComments[$indicatorId] ?? ['code' => '', 'name' => ''];
            
            $html .= '<div class="comment-block">';
            $html .= '<div class="comment-indicator">';
            $html .= '<div class="comment-indicator-title">' . htmlspecialchars($info['code']) . ': ' . htmlspecialchars($info['name']) . '</div>';
            $html .= '</div>';
            
            // Self-assessment evidence
            if (isset($selfComments[$indicatorId])) {
                $html .= '<div class="company-comment">';
                $html .= '<div class="company-comment-label">💼 ข้อเสนอแนะของบริษัท (Self-Assessment):</div>';
                $html .= '<div class="company-comment-text">' . nl2br(htmlspecialchars($selfComments[$indicatorId]['evidence'])) . '</div>';
                $html .= '</div>';
            }
            
            // Individual evaluator comments
            if (isset($auditorComments[$indicatorId])) {
                foreach ($auditorComments[$indicatorId]['comments'] as $ac) {
                    $html .= '<div class="auditor-comment">';
                    $html .= '<div class="auditor-comment-label">👨‍💼 ความเห็นกรรมการ — ' . htmlspecialchars($ac['auditor_name']) . ':</div>';
                    $html .= '<div class="auditor-comment-text">' . nl2br(htmlspecialchars($ac['comment'])) . '</div>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate scores HTML - Show pillar summaries with calculated scores and per-evaluator breakdown
     */
    private function generateScoresHTML($pillars) {
        $html = '';
        
        // Build per-indicator evaluator score lookup: indicator_id => [ user_id => { name, score, is_na } ]
        $evalScoreMap = [];
        foreach ($this->evaluatorScores as $es) {
            $evalScoreMap[$es['indicator_id']][$es['user_id']] = [
                'name' => $es['auditor_name'],
                'score' => $es['score'],
                'is_na' => $es['is_na'],
            ];
        }
        
        // Ordered evaluator list for consistent column display
        $evaluatorOrder = [];
        foreach ($this->evaluators as $ev) {
            $evaluatorOrder[] = $ev['user_id'];
        }
        $hasEvaluators = !empty($evaluatorOrder);
        
        // Pillar color scheme
        $pillarColors = [
            'การส่งเสริมสุขภาพ' => ['color' => '#10B981', 'bg' => '#D1FAE5', 'darkBg' => '#a7f3d0'],
            'ความปลอดภัยและสิ่งแวดล้อม' => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'darkBg' => '#bfdbfe'],
            'การมีส่วนร่วมกับชุมชน' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'darkBg' => '#fde68a'],
            'การบริหารจัดการและความยั่งยืน' => ['color' => '#8B5CF6', 'bg' => '#EDE9FE', 'darkBg' => '#ddd6fe']
        ];
        
        foreach ($pillars as $pillarName => $pillarData) {
            $pillarScores = $pillarData['scores'];
            $pillarWeight = $pillarData['weight'] ?? 0;
            
            // Filter out N/A indicators (where both self and auditor scores are null/not set)
            $filteredScores = [];
            foreach ($pillarScores as $score) {
                if ($score['self_score'] !== null || $score['auditor_score'] !== null) {
                    $filteredScores[] = $score;
                }
            }
            
            $indicatorCount = count($filteredScores);
            $unitWeight = $indicatorCount > 0 ? ($pillarWeight / $indicatorCount) : 0;
            
            // Get colors for this pillar
            $colors = $pillarColors[$pillarName] ?? ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'darkBg' => '#bfdbfe'];
            
            // Calculate pillar totals (handle NULL values properly)
            $selfTotal = 0;
            $selfCount = 0;
            $auditorTotal = 0;
            $auditorCount = 0;
            
            foreach ($filteredScores as $score) {
                $selfScore = $score['self_score'];
                $auditorScore = $score['auditor_score'];
                
                if ($selfScore !== null) {
                    $selfTotal += floatval($selfScore);
                    $selfCount++;
                }
                
                if ($auditorScore !== null) {
                    $auditorTotal += floatval($auditorScore);
                    $auditorCount++;
                }
            }
            
            $selfPillarScore = ($selfCount > 0) ? (($selfTotal / $selfCount) * $pillarWeight) : 0;
            $auditorPillarScore = ($auditorCount > 0) ? (($auditorTotal / $auditorCount) * $pillarWeight) : 0;
            
            $html .= '<div class="scores-section">';
            $html .= '<div class="pillar-title" style="background-color: ' . $colors['color'] . '; color: white;">📊 ' . htmlspecialchars($pillarName) . ' (น้ำหนัก: ' . $pillarWeight . ')</div>';
            
            // Pillar summary row
            $html .= '<div class="pillar-summary" style="background-color: ' . $colors['bg'] . '; border-bottom-color: ' . $colors['color'] . ';">';
            $html .= '<div class="pillar-summary-label" style="color: ' . $colors['color'] . ';">คะแนนรวม ' . htmlspecialchars($pillarName) . ':</div>';
            $html .= '<div class="pillar-score-cell" style="border-left-color: ' . $colors['color'] . '; color: ' . $colors['color'] . ';">บริษัท: ' . number_format($selfPillarScore, 2) . '</div>';
            $html .= '<div class="pillar-score-cell" style="border-left-color: ' . $colors['color'] . '; color: ' . $colors['color'] . ';">กรรมการ (เฉลี่ย): ' . number_format($auditorPillarScore, 2) . '</div>';
            $html .= '</div>';
            
            // Divider
            $html .= '<div style="border-top: 2px solid ' . $colors['color'] . '; margin: 0.75rem 0;"></div>';
            
            // Header row - use a table for precise column alignment
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">';
            $html .= '<thead><tr style="background-color: ' . $colors['color'] . '; color: white; font-weight: 700;">';
            $html .= '<th style="padding: 0.6rem 0.5rem; text-align: left; width: 55px;">รหัส</th>';
            $html .= '<th style="padding: 0.6rem 0.5rem; text-align: left;">ตัวชี้วัด</th>';
            $html .= '<th style="padding: 0.6rem 0.5rem; text-align: center; width: 80px;">ตนเอง</th>';
            if ($hasEvaluators) {
                foreach ($evaluatorOrder as $idx => $uid) {
                    $shortName = '';
                    foreach ($this->evaluators as $ev) {
                        if ($ev['user_id'] == $uid) {
                            // Use first name only for column header
                            $parts = explode(' ', $ev['name']);
                            $shortName = $parts[0];
                            break;
                        }
                    }
                    $html .= '<th style="padding: 0.6rem 0.3rem; text-align: center; width: 70px; font-size: 0.78rem;" title="' . htmlspecialchars($shortName) . '">กก.' . ($idx + 1) . '</th>';
                }
            }
            $html .= '<th style="padding: 0.6rem 0.5rem; text-align: center; width: 80px;">เฉลี่ยกก.</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            
            foreach ($filteredScores as $score) {
                $selfScore = floatval($score['self_score'] ?? 0) * $unitWeight;
                $auditorScore = $score['auditor_score'] !== null ? floatval($score['auditor_score']) * $unitWeight : null;
                $indicatorId = $score['indicator_id'];
                
                $html .= '<tr style="border-bottom: 1px solid #e2e8f0;">';
                $html .= '<td style="padding: 0.5rem; font-weight: 600; color: ' . $colors['color'] . ';">' . htmlspecialchars($score['code'] ?? '') . '</td>';
                $html .= '<td style="padding: 0.5rem;">' . htmlspecialchars($score['name_th']) . '</td>';
                $html .= '<td style="padding: 0.5rem; text-align: center; font-weight: 700; color: ' . $colors['color'] . ';">' . number_format($selfScore, 2) . '</td>';
                
                // Per-evaluator columns
                if ($hasEvaluators) {
                    foreach ($evaluatorOrder as $uid) {
                        $evData = $evalScoreMap[$indicatorId][$uid] ?? null;
                        if ($evData) {
                            if ($evData['is_na']) {
                                $html .= '<td style="padding: 0.5rem; text-align: center; color: #94a3b8; font-size: 0.8rem;">N/A</td>';
                            } else {
                                $evWeighted = floatval($evData['score']) * $unitWeight;
                                $html .= '<td style="padding: 0.5rem; text-align: center; font-weight: 600; color: #475569;">' . number_format($evWeighted, 2) . '</td>';
                            }
                        } else {
                            $html .= '<td style="padding: 0.5rem; text-align: center; color: #cbd5e1;">-</td>';
                        }
                    }
                }
                
                // Average auditor column
                $html .= '<td style="padding: 0.5rem; text-align: center; font-weight: 700;">';
                if ($auditorScore !== null) {
                    $html .= '<span style="color: ' . $colors['color'] . ';">' . number_format($auditorScore, 2) . '</span>';
                } else {
                    $html .= '<span style="color: #94a3b8;">-</span>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            $html .= '</div>';
        }
        
        // Evaluator legend
        if ($hasEvaluators) {
            $html .= '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem;">';
            $html .= '<div style="font-weight: 700; color: #475569; margin-bottom: 0.5rem;">📌 หมายเหตุคอลัมน์กรรมการ:</div>';
            foreach ($evaluatorOrder as $idx => $uid) {
                foreach ($this->evaluators as $ev) {
                    if ($ev['user_id'] == $uid) {
                        $html .= '<div style="margin-left: 1rem; color: #64748b;">กก.' . ($idx + 1) . ' = ' . htmlspecialchars($ev['name']) . '</div>';
                        break;
                    }
                }
            }
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Write CSV content
     */
    private function writeCSVContent($output) {
        fputcsv($output, ['รายงานการประเมิน HICM V2025']);
        fputcsv($output, []);
        
        fputcsv($output, ['สถานประกอบการ', htmlspecialchars($this->assessment['company_name'])]);
        fputcsv($output, ['ที่อยู่', htmlspecialchars($this->assessment['address'] ?? '-')]);
        fputcsv($output, ['จังหวัด', htmlspecialchars($this->assessment['province'] ?? '-')]);
        fputcsv($output, ['รอบการประเมิน', htmlspecialchars($this->assessment['period_name']) . ' (' . $this->assessment['year'] . ')']);
        fputcsv($output, ['สถานะ', $this->assessment['status']]);
        fputcsv($output, ['คะแนนรวม', number_format($this->assessment['final_score'] ?? 0, 2)]);
        fputcsv($output, ['ระดับ HICM', $this->assessment['hicm_level'] ?? 1]);
        fputcsv($output, ['วันที่สร้างรายงาน', date('d/m/Y H:i:s')]);
        fputcsv($output, []);
        
        // Auditor information from evaluators
        if (!empty($this->evaluators)) {
            fputcsv($output, ['ข้อมูลกรรมการประเมิน (' . count($this->evaluators) . ' คน)']);
            foreach ($this->evaluators as $evaluator) {
                fputcsv($output, [
                    'ชื่อกรรมการ',
                    htmlspecialchars($evaluator['name']),
                    'อีเมล',
                    htmlspecialchars($evaluator['email']),
                    'คะแนนถ่วงน้ำหนัก',
                    $evaluator['total_scored'] > 0 ? number_format($evaluator['weighted_total'], 2) . ' / 1,000' : '-',
                    'ประเมินแล้ว',
                    $evaluator['total_scored'] . ' ข้อ',
                    'N/A',
                    $evaluator['total_na'] . ' ข้อ'
                ]);
            }
            fputcsv($output, []);
        }
        
        fputcsv($output, ['หมวด', 'รหัส', 'ตัวชี้วัด', 'คะแนนตนเอง', 'คะแนนผู้ประเมิน']);
        
        $currentPillar = '';
        $currentPillarWeight = 0;
        $currentPillarId = null;
        foreach ($this->scores as $score) {
            if ($currentPillar !== $score['pillar_name']) {
                $currentPillar = $score['pillar_name'];
                $currentPillarWeight = $score['pillar_weight'] ?? 0;
                $currentPillarId = $score['pillar_id'];
                fputcsv($output, ['', '', str_repeat('=', 50), '', '']);
                fputcsv($output, [$currentPillar . ' (น้ำหนัก: ' . $currentPillarWeight . ')', '', '', '', '']);
                fputcsv($output, ['', '', str_repeat('=', 50), '', '']);
            }
            
            $indicatorCount = $this->indicatorCountPerPillar[$currentPillarId] ?? 0;
            $unitWeight = $indicatorCount > 0 ? ($currentPillarWeight / $indicatorCount) : 0;
            
            $selfScoreValue = (floatval($score['self_score'] ?? 0) * $unitWeight);
            $auditorScoreValue = ($score['auditor_score'] !== null) ? (floatval($score['auditor_score']) * $unitWeight) : null;
            
            fputcsv($output, [
                '',
                $score['code'] ?? '',
                htmlspecialchars($score['name_th']),
                number_format($selfScoreValue, 2),
                $auditorScoreValue !== null ? number_format($auditorScoreValue, 2) : '-'
            ]);
        }
        
        fputcsv($output, []);
        fputcsv($output, ['ข้อเสนอแนะและความเห็น']);
        fputcsv($output, []);
        
        // Comments section - self evidence
        $selfComments = [];
        foreach ($this->scores as $score) {
            if (!empty($score['self_evidence'])) {
                $selfComments[$score['indicator_id']] = [
                    'code' => $score['code'] ?? '',
                    'name' => $score['name_th'] ?? '',
                    'evidence' => $score['self_evidence'],
                ];
            }
        }
        
        // Evaluator comments
        $auditorComments = [];
        foreach ($this->evaluatorScores as $es) {
            if (!empty($es['comment'])) {
                $auditorComments[$es['indicator_id']][] = [
                    'code' => $es['indicator_code'] ?? '',
                    'name' => $es['indicator_name'] ?? '',
                    'auditor_name' => $es['auditor_name'],
                    'comment' => $es['comment'],
                ];
            }
        }
        
        $allIds = array_unique(array_merge(array_keys($selfComments), array_keys($auditorComments)));
        sort($allIds);
        
        foreach ($allIds as $indicatorId) {
            $info = $selfComments[$indicatorId] ?? ['code' => '', 'name' => ''];
            if (empty($info['code']) && isset($auditorComments[$indicatorId][0])) {
                $info = $auditorComments[$indicatorId][0];
            }
            fputcsv($output, ['ตัวชี้วัด', htmlspecialchars($info['code']), htmlspecialchars($info['name'])]);
            
            if (isset($selfComments[$indicatorId])) {
                fputcsv($output, ['ข้อเสนอแนะของบริษัท', htmlspecialchars($selfComments[$indicatorId]['evidence'])]);
            }
            
            if (isset($auditorComments[$indicatorId])) {
                foreach ($auditorComments[$indicatorId] as $ac) {
                    fputcsv($output, ['ความเห็นกรรมการ (' . htmlspecialchars($ac['auditor_name']) . ')', htmlspecialchars($ac['comment'])]);
                }
            }
            
            fputcsv($output, []);
        }
    }
    
    /**
     * Write Excel content
     */
    private function writeExcelContent($output) {
        // Excel-friendly format with better formatting
        $this->writeCSVContent($output);
    }
    
    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status) {
        $badges = [
            'draft' => '<span style="background: #f3f4f6; color: #6b7280; padding: 0.25rem 0.75rem; border-radius: 0.25rem;">📝 Draft</span>',
            'submitted' => '<span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 0.25rem;">📤 Submitted</span>',
            'under_review' => '<span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 0.25rem;">🔍 Under Review</span>',
            'evaluated' => '<span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 0.25rem;">✓ Evaluated</span>',
            'completed' => '<span style="background: #ddd6fe; color: #5b21b6; padding: 0.25rem 0.75rem; border-radius: 0.25rem;">✓✓ Completed</span>',
        ];
        
        return $badges[$status] ?? htmlspecialchars($status);
    }
    
    /**
     * Get filename based on format
     */
    private function getReportFilename($format) {
        $company = preg_replace('/[^a-z0-9\-_]/i', '_', $this->assessment['company_name']);
        $period = preg_replace('/[^a-z0-9\-_]/i', '_', $this->assessment['period_name']);
        
        $ext = [
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            'html' => 'html'
        ][$format] ?? 'txt';
        
        return 'HICM_' . $company . '_' . $period . '_' . date('Ymd') . '.' . $ext;
    }
}
?>
