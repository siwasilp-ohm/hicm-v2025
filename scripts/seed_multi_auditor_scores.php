<?php
/**
 * Seed demo scores for multiple auditors to test the average calculation and UI.
 * Targets 10 random assessments that have at least 2 auditors assigned.
 * Run: php scripts/seed_multi_auditor_scores.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/assessment.php';

$db = getDB();
$conn = $db->getConnection();

echo "=== Seed Multi-Auditor Demo Scores ===\n\n";

// 1. Find 10 assessments with multiple auditors
$stmt = $conn->query("
    SELECT assessment_id, COUNT(user_id) as auditor_count 
    FROM assessment_evaluators 
    GROUP BY assessment_id 
    HAVING auditor_count >= 2 
    LIMIT 10
");
$assessmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assessmentRows)) {
    echo "No assessments with multiple auditors found. Run scripts/seed_assessment_evaluators.php first.\n";
    exit(1);
}

// 2. Get all indicators
$stmt = $conn->query("SELECT id FROM indicators WHERE is_active = 1");
$indicators = $stmt->fetchAll(PDO::FETCH_COLUMN);

$totalScoresCreated = 0;

foreach ($assessmentRows as $row) {
    $assessmentId = $row['assessment_id'];
    
    // Get assigned auditors for this assessment
    $stmt = $conn->prepare("SELECT user_id FROM assessment_evaluators WHERE assessment_id = ?");
    $stmt->execute([$assessmentId]);
    $auditorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Processing Assessment #{$assessmentId} with auditors: " . implode(', ', $auditorIds) . "\n";
    
    // For each indicator, let both auditors score it (randomly)
    foreach ($indicators as $indicatorId) {
        foreach ($auditorIds as $auditorId) {
            // Randomly choose score from [0, 0.25, 0.5, 0.75, 1.0]
            $scores = [0, 0.25, 0.5, 0.75, 1.0];
            $score = $scores[array_rand($scores)];
            $isNa = (rand(1, 20) === 1) ? 1 : 0; // 5% chance of N/A
            
            $comments = [
                "Good progress observed.",
                "Needs improvement in documentation.",
                "Excellent implementation of the standard.",
                "Partially met the criteria.",
                "Compliance is clear and verified.",
                "Supporting evidence is sufficient.",
                "Wait for further evidence next time.",
                "Generally satisfied with the results."
            ];
            $comment = $comments[array_rand($comments)];
            
            saveAuditorScore($assessmentId, $indicatorId, $score, $comment, $auditorId, $isNa);
            $totalScoresCreated++;
        }
    }
    
    // Mark assessment as 'under_review' or 'evaluated' to show up in lists
    $conn->prepare("UPDATE assessments SET status = 'evaluated', evaluated_at = NOW() WHERE id = ?")
         ->execute([$assessmentId]);
}

echo "\nDone! Created {$totalScoresCreated} individual auditor scores for " . count($assessmentRows) . " assessments.\n";
echo "You can now view these assessments in the Assessment View page to see the AVG badges.\n";
