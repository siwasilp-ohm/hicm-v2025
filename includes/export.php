<?php
/**
 * Export Logic
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/assessment.php';
require_once __DIR__ . '/../includes/companies.php';

function exportAssessments($format, $filters) {
    $assessments = getAllAssessments($filters);
    
    if ($format === 'csv') {
        $filename = 'assessments_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, [
            'Assessment ID', 
            'Company Name', 
            'Industry', 
            'Period', 
            'Status', 
            'Submitted Date', 
            'Evaluated Date',
            'Evaluated By',
            'Final Score',
            'HICM Level'
        ]);
        
        // Rows
        foreach ($assessments as $a) {
            fputcsv($output, [
                $a['id'],
                $a['company_name'],
                $a['industry_type'],
                $a['period_name'],
                $a['status'],
                $a['submitted_at'],
                $a['evaluated_at'],
                $a['evaluator_name'],
                $a['final_score'],
                getHICMLevelName($a['hicm_level'])['name']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

function exportCompanies($format, $filters) {
    $companies = getAllCompanies($filters);
    
    if ($format === 'csv') {
        $filename = 'companies_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, [
            'Company ID',
            'Name (TH)',
            'Name (EN)',
            'Tax ID',
            'Industry',
            'Size',
            'Employees',
            'Contact Name',
            'Contact Email',
            'Contact Phone',
            'Status',
            'Created Date'
        ]);
        
        foreach ($companies as $c) {
            fputcsv($output, [
                $c['id'],
                $c['company_name'],
                $c['company_name_en'],
                $c['tax_id'],
                $c['industry_type'],
                $c['company_size'],
                $c['employee_count'],
                $c['contact_name'],
                $c['contact_email'],
                $c['contact_phone'],
                $c['is_active'] ? 'Active' : 'Inactive',
                $c['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

function exportUsers($format, $filters) {
    $db = getDB();
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($filters['role'])) {
        $sql .= " AND role = ?";
        $params[] = $filters['role'];
    }
    
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'active') {
            $sql .= " AND is_active = 1";
        } elseif ($filters['status'] === 'inactive') {
            $sql .= " AND is_active = 0";
        }
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    if ($format === 'csv') {
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Username', 'Name', 'Email', 'Role', 'Phone', 'Active', 'Last Login']);
        
        foreach ($users as $u) {
            fputcsv($output, [
                $u['id'],
                $u['username'],
                $u['name'],
                $u['email'],
                $u['role'],
                $u['phone'],
                $u['is_active'] ? 'Yes' : 'No',
                $u['last_login']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

function exportToPDF($type, $filters) {
    echo "PDF export functionality has been removed. Please use Excel export instead.";
    exit;
}

function exportToExcel($type, $filters) {
    echo "Excel export functionality has been moved to the client-side using JavaScript.";
    exit;
}

function exportToTXT($type, $filters) {
    $data = [];
    $headers = [];

    switch ($type) {
        case 'assessments':
            $data = getAllAssessments($filters);
            $headers = ['Assessment ID', 'Company Name', 'Industry', 'Period', 'Status', 'Submitted Date', 'Evaluated Date', 'Evaluated By', 'Final Score', 'HICM Level'];
            break;
        case 'companies':
            $data = getAllCompanies($filters);
            $headers = ['Company ID', 'Name (TH)', 'Name (EN)', 'Tax ID', 'Industry', 'Size', 'Employees', 'Contact Name', 'Contact Email', 'Contact Phone', 'Status', 'Created Date'];
            break;
        case 'users':
            $data = getAllUsers($filters);
            $headers = ['ID', 'Username', 'Name', 'Email', 'Role', 'Phone', 'Active', 'Last Login'];
            break;
        default:
            echo "Invalid export type.";
            exit;
    }

    if (empty($data)) {
        echo "No data available for export.";
        exit;
    }

    $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Generate TXT content
    $output = implode("\t", $headers) . "\n"; // Add headers
    foreach ($data as $row) {
        $output .= implode("\t", $row) . "\n"; // Tab-separated values
    }

    echo $output;
    exit;
}

function getExportStats() {
    $db = getDB();
    $stats = [];
    
    // Assessments total
    $stats['assessments']['total'] = $db->getConnection()->query("SELECT COUNT(*) FROM assessments")->fetchColumn();
    
    // Companies total
    $stats['companies']['total'] = $db->getConnection()->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    
    // Users total
    $stats['users']['total'] = $db->getConnection()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    return $stats;
}
?>
