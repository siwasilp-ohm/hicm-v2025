<?php
/**
 * scripts/migrate_auditor_assignment.php
 * - Adds evaluated_by, evaluator_id to assessments (required for assessment-view.php)
 * - Creates assessment_evaluators table
 */
require_once __DIR__ . '/../config/database.php';
$db = getDB();

try {
    $conn = $db->getConnection();

    // Add evaluator columns to assessments if missing (required for View button -> assessment-view.php)
    foreach (['evaluated_by', 'evaluator_id'] as $col) {
        $stmt = $conn->query("SHOW COLUMNS FROM assessments LIKE '$col'");
        if ($stmt->rowCount() === 0) {
            $after = $col === 'evaluated_by' ? 'notes' : 'evaluated_by';
            $conn->exec("ALTER TABLE assessments ADD COLUMN $col INT NULL AFTER $after");
            echo "Added column 'assessments.$col'.\n";
        }
    }
    try {
        $conn->exec("ALTER TABLE assessments ADD INDEX idx_evaluated_by (evaluated_by)");
        echo "Added index idx_evaluated_by.\n";
    } catch (Exception $e) { /* ignore if exists */ }
    try {
        $conn->exec("ALTER TABLE assessments ADD INDEX idx_evaluator_id (evaluator_id)");
        echo "Added index idx_evaluator_id.\n";
    } catch (Exception $e) { /* ignore if exists */ }
    try {
        $conn->exec("ALTER TABLE assessments ADD CONSTRAINT fk_assessments_evaluated_by FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "Added FK fk_assessments_evaluated_by.\n";
    } catch (Exception $e) { /* ignore if exists */ }
    try {
        $conn->exec("ALTER TABLE assessments ADD CONSTRAINT fk_assessments_evaluator_id FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "Added FK fk_assessments_evaluator_id.\n";
    } catch (Exception $e) { /* ignore if exists */ }

    // Add N/A columns to assessment_scores if missing (required for assessment-view.php)
    foreach (['is_na', 'auditor_is_na'] as $col) {
        $stmt = $conn->query("SHOW COLUMNS FROM assessment_scores LIKE '$col'");
        if ($stmt->rowCount() === 0) {
            $after = $col === 'is_na' ? 'self_evidence' : 'auditor_comment';
            $conn->exec("ALTER TABLE assessment_scores ADD COLUMN $col TINYINT(1) DEFAULT 0 AFTER $after");
            echo "Added column 'assessment_scores.$col'.\n";
        }
    }

    echo "Creating assessment_evaluators table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS assessment_evaluators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notified_at DATETIME DEFAULT NULL,
        INDEX (assessment_id),
        INDEX (user_id),
        UNIQUE KEY (assessment_id, user_id)
    )");
    
    echo "Creating email_templates table and seed notification template if missing...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(50) UNIQUE NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("INSERT IGNORE INTO email_templates (template_key, subject, body) VALUES (?, ?, ?)");
    $stmt->execute([
        'new_assignment',
        'แจ้งการมอบหมายแบบประเมินใหม่ - {app_name}',
        '<p>เรียน คุณ {auditor_name},</p>
        <p>คุณได้รับมอบหมายให้ตรวจสอบแบบประเมินของ <strong>{company_name}</strong> ในรอบปี <strong>{year}</strong> ({period_name})</p>
        <p>กรุณาคลิกที่ลิงก์ด้านล่างเพื่อเข้าสู่ระบบและตรวจสอบ:</p>
        <p><a href="{view_url}" style="display:inline-block; padding:10px 20px; background:#2563eb; color:white; border-radius:8px; text-decoration:none;">ไปที่แบบประเมิน</a></p>
        <p>ขอบคุณครับ,<br>{app_name}</p>'
    ]);

    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
