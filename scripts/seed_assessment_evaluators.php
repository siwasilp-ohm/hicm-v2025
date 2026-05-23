<?php
/**
 * Seed demo data into assessment_evaluators for testing the assignment flow.
 * - Assigns 1–2 auditors per assessment (round-robin from auditor list)
 * - Updates assessments.evaluated_by and evaluator_id for display
 * Run: php scripts/seed_assessment_evaluators.php
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$conn = $db->getConnection();

echo "=== Seed Assessment Evaluators (Demo) ===\n\n";

// Ensure table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS assessment_evaluators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notified_at DATETIME DEFAULT NULL,
        INDEX (assessment_id),
        INDEX (user_id),
        UNIQUE KEY (assessment_id, user_id),
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Table may already exist
}

// 1. Get all auditor user IDs
$stmt = $conn->query("SELECT id, name, username FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY id");
$auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($auditors)) {
    echo "No auditors found. Add users with role 'auditor' (e.g. run database/sample_users.sql).\n";
    exit(1);
}

echo "Found " . count($auditors) . " auditor(s): " . implode(', ', array_column($auditors, 'username')) . "\n";

// 2. Get all assessments
$stmt = $conn->query("
    SELECT a.id, a.company_id, a.period_id, a.status, c.company_name
    FROM assessments a
    JOIN companies c ON c.id = a.company_id
    ORDER BY a.id
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assessments)) {
    echo "No assessments found. Create assessments first (e.g. run scripts/generate_demo_assessments.php).\n";
    exit(1);
}

echo "Found " . count($assessments) . " assessment(s).\n\n";

$insertStmt = $conn->prepare("
    INSERT IGNORE INTO assessment_evaluators (assessment_id, user_id) VALUES (?, ?)
");
// Check if assessments has evaluator columns (from migrate_auditor_assignment.php)
$hasEvaluatedBy = false;
$hasEvaluatorId = false;
try {
    $stmt = $conn->query("SHOW COLUMNS FROM assessments LIKE 'evaluated_by'");
    $hasEvaluatedBy = $stmt->rowCount() > 0;
    $stmt = $conn->query("SHOW COLUMNS FROM assessments LIKE 'evaluator_id'");
    $hasEvaluatorId = $stmt->rowCount() > 0;
} catch (Exception $e) { /* ignore */ }

$updateAssessmentStmt = null;
if ($hasEvaluatedBy && $hasEvaluatorId) {
    $updateAssessmentStmt = $conn->prepare("
        UPDATE assessments SET evaluated_by = ?, evaluator_id = ?,
        status = CASE WHEN status IN ('submitted') THEN 'under_review' ELSE status END,
        updated_at = NOW()
        WHERE id = ?
    ");
} elseif ($hasEvaluatedBy) {
    $updateAssessmentStmt = $conn->prepare("
        UPDATE assessments SET evaluated_by = ?,
        status = CASE WHEN status IN ('submitted') THEN 'under_review' ELSE status END,
        updated_at = NOW()
        WHERE id = ?
    ");
}

$auditorIds = array_column($auditors, 'id');
$nAuditors = count($auditorIds);
$assigned = 0;
$updated = 0;

foreach ($assessments as $i => $a) {
    $assessmentId = (int) $a['id'];
    // Assign 1 or 2 auditors per assessment (round-robin)
    $primaryIndex = $i % $nAuditors;
    $primaryId = $auditorIds[$primaryIndex];
    $secondaryIndex = ($i + 1) % $nAuditors;
    $secondaryId = $nAuditors > 1 ? $auditorIds[$secondaryIndex] : null;

    try {
        $insertStmt->execute([$assessmentId, $primaryId]);
        if ($insertStmt->rowCount() > 0) {
            $assigned++;
            echo "  Assessment #{$assessmentId} ({$a['company_name']}) -> auditor id {$primaryId}\n";
        }
        if ($secondaryId !== null && $secondaryId !== $primaryId) {
            $insertStmt->execute([$assessmentId, $secondaryId]);
            if ($insertStmt->rowCount() > 0) {
                $assigned++;
                echo "  Assessment #{$assessmentId} ({$a['company_name']}) -> auditor id {$secondaryId}\n";
            }
        }

        if ($updateAssessmentStmt) {
            if ($hasEvaluatorId) {
                $updateAssessmentStmt->execute([$primaryId, $primaryId, $assessmentId]);
            } else {
                $updateAssessmentStmt->execute([$primaryId, $assessmentId]);
            }
            if ($updateAssessmentStmt->rowCount() > 0) {
                $updated++;
            }
        }
    } catch (Exception $e) {
        echo "  Error for assessment #{$assessmentId}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Inserted/kept {$assigned} assignment(s)";
if ($updateAssessmentStmt) {
    echo ", updated {$updated} assessment(s) with primary evaluator";
}
echo ".\n";
if (!$hasEvaluatedBy) {
    echo "Tip: Run scripts/migrate_auditor_assignment.php to add evaluated_by/evaluator_id columns so the View page shows the primary auditor.\n";
}
echo "You can now test: Login as admin/auditor -> Assessments -> View -> see evaluators list.\n";
