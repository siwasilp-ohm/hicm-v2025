<?php
/**
 * HICM V2025 - Factory Assessment Summary API
 *
 * GET /api/factory-assessment-summary.php?factory_id=1
 * GET /api/factory-assessment-summary.php?factory_id=1&scope=all
 * GET /api/factory-assessment-summary.php?factory_id=1&period_id=3
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/assessment.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function apiRespond($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function apiError($message, $statusCode = 400, $details = []) {
    apiRespond([
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $message,
            'details' => $details
        ]
    ], $statusCode);
}

function dbTableExists($tableName) {
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ");
    $stmt->execute([DB_NAME, $tableName]);
    $cache[$tableName] = ((int) $stmt->fetchColumn()) > 0;
    return $cache[$tableName];
}

function dbColumnExists($tableName, $columnName) {
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([DB_NAME, $tableName, $columnName]);
    $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function normalizeIndustryTypes($value) {
    if ($value === null || $value === '') {
        return [];
    }

    $parts = strpos($value, '|') !== false ? explode('|', $value) : [$value];
    return array_values(array_filter(array_map('trim', $parts), function ($item) {
        return $item !== '';
    }));
}

function cleanText($value) {
    if ($value === null) {
        return null;
    }
    return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatDateValue($value) {
    if (!$value) {
        return null;
    }
    return date('Y-m-d', strtotime($value));
}

function formatDateTimeValue($value) {
    if (!$value) {
        return null;
    }
    return date('c', strtotime($value));
}

function scoreBlock($score) {
    $score = round((float) $score, 2);
    $level = calculateHICMLevel($score);
    $levelName = function_exists('getHICMLevelName') ? getHICMLevelName($level) : ['name' => '', 'name_en' => ''];

    return [
        'score' => $score,
        'max_score' => 1000,
        'percentage' => round(($score / 1000) * 100, 2),
        'hicm_level' => [
            'level' => $level,
            'name' => $levelName['name'] ?? '',
            'name_en' => $levelName['name_en'] ?? ''
        ]
    ];
}

function getFactory($factoryId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, u.username, u.email as user_email, u.name as user_name, u.phone as user_phone,
               u.is_active as user_is_active, u.avatar as owner_avatar
        FROM companies c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$factoryId]);
    return $stmt->fetch();
}

function buildFactoryPayload($company) {
    return [
        'id' => (int) $company['id'],
        'factory_id' => (int) $company['id'],
        'company_name' => cleanText($company['company_name']),
        'company_name_en' => cleanText($company['company_name_en'] ?? null),
        'tax_id' => $company['tax_id'] ?? null,
        'industry_types' => normalizeIndustryTypes($company['industry_type'] ?? ''),
        'company_size' => $company['company_size'] ?? null,
        'employee_count' => isset($company['employee_count']) ? (int) $company['employee_count'] : null,
        'established_year' => isset($company['established_year']) ? (int) $company['established_year'] : null,
        'description' => cleanText($company['description'] ?? null),
        'address' => [
            'address' => cleanText($company['address'] ?? null),
            'district' => cleanText($company['district'] ?? null),
            'province' => cleanText($company['province'] ?? null),
            'postal_code' => $company['postal_code'] ?? null
        ],
        'contact' => [
            'name' => cleanText($company['contact_name'] ?: ($company['user_name'] ?? null)),
            'position' => cleanText($company['contact_position'] ?? null),
            'email' => $company['contact_email'] ?: ($company['user_email'] ?? null),
            'phone' => $company['contact_phone'] ?: ($company['user_phone'] ?? null),
            'office_phone' => $company['phone'] ?? null,
            'website' => $company['website'] ?? null
        ],
        'logo_url' => !empty($company['logo']) ? getBaseUrl() . '/assets/uploads/' . ltrim($company['logo'], '/') : null,
        'is_active' => (bool) ($company['is_active'] ?? true)
    ];
}

function getAssessmentsForFactory($factoryId, $scope, $periodId, $includeDraft) {
    $db = getDB();
    $params = [$factoryId];
    $where = "a.company_id = ?";

    if ($periodId) {
        $where .= " AND a.period_id = ?";
        $params[] = $periodId;
    } elseif ($scope === 'current') {
        $where .= " AND ap.status IN ('open', 'evaluating') AND ap.is_active = 1";
    }

    if (!$includeDraft) {
        $where .= " AND a.status <> 'draft'";
    }

    $limit = ($scope === 'all' || $periodId) ? '' : 'LIMIT 1';
    $order = $scope === 'current'
        ? "ap.start_date DESC, a.updated_at DESC"
        : "ap.year DESC, ap.start_date DESC, a.updated_at DESC";

    $showAuditorSelect = dbColumnExists('assessment_periods', 'show_auditor_results')
        ? 'ap.show_auditor_results'
        : '0 AS show_auditor_results';

    $stmt = $db->prepare("
        SELECT a.*,
               ap.year, ap.name as period_name, ap.description as period_description,
               ap.start_date, ap.end_date, ap.submission_deadline,
               ap.evaluation_start_date, ap.evaluation_end_date, ap.announcement_date,
               ap.status as period_status, ap.is_active as period_is_active,
               {$showAuditorSelect}
        FROM assessments a
        JOIN assessment_periods ap ON a.period_id = ap.id
        WHERE {$where}
        ORDER BY {$order}
        {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getEvaluatorSummary($assessmentId) {
    if (!dbTableExists('assessment_evaluators')) {
        return [
            'assigned_count' => 0,
            'submitted_count' => 0,
            'evaluators' => []
        ];
    }

    $db = getDB();
    $submittedSelect = dbColumnExists('assessment_evaluators', 'submitted_at')
        ? 'ae.submitted_at'
        : 'NULL AS submitted_at';

    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, {$submittedSelect}
        FROM assessment_evaluators ae
        JOIN users u ON ae.user_id = u.id
        WHERE ae.assessment_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$assessmentId]);
    $rows = $stmt->fetchAll();

    $submittedCount = 0;
    $evaluators = [];
    foreach ($rows as $row) {
        if (!empty($row['submitted_at'])) {
            $submittedCount++;
        }
        $evaluators[] = [
            'id' => (int) $row['id'],
            'name' => cleanText($row['name']),
            'email' => $row['email'],
            'submitted_at' => formatDateTimeValue($row['submitted_at'] ?? null)
        ];
    }

    return [
        'assigned_count' => count($rows),
        'submitted_count' => $submittedCount,
        'evaluators' => $evaluators
    ];
}

function getPillarScores($assessmentId) {
    $db = getDB();

    $selfNaSelect = dbColumnExists('assessment_scores', 'is_na') ? 's.is_na' : '0 AS is_na';
    $auditorNaSelect = dbColumnExists('assessment_scores', 'auditor_is_na') ? 's.auditor_is_na' : '0 AS auditor_is_na';

    $stmt = $db->prepare("
        SELECT p.code as pillar_code, p.name_th as pillar_name_th, p.name_en as pillar_name_en,
               p.weight, p.color, p.icon,
               i.id as indicator_id,
               s.self_score, {$selfNaSelect},
               s.auditor_score, {$auditorNaSelect}
        FROM assessment_scores s
        JOIN indicators i ON s.indicator_id = i.id
        JOIN pillars p ON i.pillar_id = p.id
        WHERE s.assessment_id = ? AND p.is_active = 1
        ORDER BY p.display_order, i.display_order
    ");
    $stmt->execute([$assessmentId]);
    $rows = $stmt->fetchAll();

    $pillars = [];
    foreach ($rows as $row) {
        $code = $row['pillar_code'];
        if (!isset($pillars[$code])) {
            $pillars[$code] = [
                'code' => $code,
                'name_th' => cleanText($row['pillar_name_th']),
                'name_en' => cleanText($row['pillar_name_en']),
                'weight' => (float) $row['weight'],
                'color' => $row['color'],
                'icon' => $row['icon'],
                '_total_indicators' => 0,
                '_self_active' => 0,
                '_self_sum' => 0.0,
                '_self_scored' => 0,
                '_self_na' => 0,
                '_auditor_sum' => 0.0,
                '_auditor_scored' => 0,
                '_auditor_na' => 0
            ];
        }

        $pillars[$code]['_total_indicators']++;

        if (!empty($row['is_na'])) {
            $pillars[$code]['_self_na']++;
        } else {
            $pillars[$code]['_self_active']++;
            if ($row['self_score'] !== null && $row['self_score'] !== '') {
                $pillars[$code]['_self_sum'] += (float) $row['self_score'];
                $pillars[$code]['_self_scored']++;
            }
        }

        if (!empty($row['auditor_is_na'])) {
            $pillars[$code]['_auditor_na']++;
        } elseif ($row['auditor_score'] !== null && $row['auditor_score'] !== '') {
            $pillars[$code]['_auditor_sum'] += (float) $row['auditor_score'];
            $pillars[$code]['_auditor_scored']++;
        }
    }

    $result = [];
    $selfTotal = 0.0;
    $auditorTotal = 0.0;
    $hasAnyAuditorScore = false;

    foreach ($pillars as $code => $pillar) {
        $weight = (float) $pillar['weight'];
        $selfActive = (int) $pillar['_self_active'];
        $selfScore = $selfActive > 0 ? ($pillar['_self_sum'] / $selfActive) * $weight : 0;
        $auditorScore = 0;

        if ($pillar['_auditor_scored'] > 0 && $selfActive > 0) {
            $hasAnyAuditorScore = true;
            $auditorScore = ($pillar['_auditor_sum'] / $selfActive) * $weight;
        }

        $finalScore = $pillar['_auditor_scored'] > 0 ? $auditorScore : $selfScore;
        $selfTotal += $selfScore;
        $auditorTotal += $auditorScore;

        $result[$code] = [
            'code' => $code,
            'name_th' => $pillar['name_th'],
            'name_en' => $pillar['name_en'],
            'weight' => $weight,
            'color' => $pillar['color'],
            'icon' => $pillar['icon'],
            'self_assessment' => [
                'score' => round($selfScore, 2),
                'max_score' => $weight,
                'percentage' => $weight > 0 ? round(($selfScore / $weight) * 100, 2) : 0,
                'answered_indicators' => (int) $pillar['_self_scored'],
                'active_indicators' => $selfActive,
                'na_indicators' => (int) $pillar['_self_na']
            ],
            'auditor_assessment' => [
                'score' => round($auditorScore, 2),
                'max_score' => $weight,
                'percentage' => $weight > 0 ? round(($auditorScore / $weight) * 100, 2) : 0,
                'scored_indicators' => (int) $pillar['_auditor_scored'],
                'active_indicators' => $selfActive,
                'na_indicators' => (int) $pillar['_auditor_na']
            ],
            'final' => [
                'source' => $pillar['_auditor_scored'] > 0 ? 'auditor_assessment' : 'self_assessment',
                'score' => round($finalScore, 2),
                'max_score' => $weight,
                'percentage' => $weight > 0 ? round(($finalScore / $weight) * 100, 2) : 0
            ],
            'indicator_summary' => [
                'total_indicators' => (int) $pillar['_total_indicators'],
                'active_indicators' => $selfActive
            ]
        ];
    }

    $finalTotal = $hasAnyAuditorScore ? $auditorTotal : $selfTotal;

    return [
        'overall' => [
            'self_total_score' => round($selfTotal, 2),
            'auditor_total_score' => round($auditorTotal, 2),
            'final_score' => round($finalTotal, 2),
            'final_source' => $hasAnyAuditorScore ? 'auditor_assessment' : 'self_assessment'
        ],
        'pillars' => $result
    ];
}

function buildAssessmentPayload($assessment) {
    $scoreData = getPillarScores($assessment['id']);
    $overall = $scoreData['overall'];
    $showAuditorResults = !empty($assessment['show_auditor_results']);

    return [
        'id' => (int) $assessment['id'],
        'status' => $assessment['status'],
        'period' => [
            'id' => (int) $assessment['period_id'],
            'year' => (int) $assessment['year'],
            'name' => cleanText($assessment['period_name']),
            'description' => cleanText($assessment['period_description'] ?? null),
            'status' => $assessment['period_status'],
            'is_active' => (bool) $assessment['period_is_active'],
            'show_auditor_results' => $showAuditorResults,
            'start_date' => formatDateValue($assessment['start_date']),
            'end_date' => formatDateValue($assessment['end_date']),
            'submission_deadline' => formatDateValue($assessment['submission_deadline']),
            'evaluation_start_date' => formatDateValue($assessment['evaluation_start_date'] ?? null),
            'evaluation_end_date' => formatDateValue($assessment['evaluation_end_date'] ?? null),
            'announcement_date' => formatDateTimeValue($assessment['announcement_date'] ?? null)
        ],
        'timestamps' => [
            'submitted_at' => formatDateTimeValue($assessment['submitted_at'] ?? null),
            'evaluated_at' => formatDateTimeValue($assessment['evaluated_at'] ?? null),
            'completed_at' => formatDateTimeValue($assessment['completed_at'] ?? null),
            'updated_at' => formatDateTimeValue($assessment['updated_at'] ?? null)
        ],
        'scores' => [
            'self_assessment' => scoreBlock($overall['self_total_score']),
            'auditor_assessment' => scoreBlock($overall['auditor_total_score']),
            'final' => array_merge(scoreBlock($overall['final_score']), [
                'source' => $overall['final_source']
            ]),
            'stored_values' => [
                'self_total_score' => isset($assessment['self_total_score']) ? (float) $assessment['self_total_score'] : null,
                'auditor_total_score' => isset($assessment['auditor_total_score']) ? (float) $assessment['auditor_total_score'] : null,
                'final_score' => isset($assessment['final_score']) ? (float) $assessment['final_score'] : null,
                'hicm_level' => isset($assessment['hicm_level']) ? (int) $assessment['hicm_level'] : null
            ]
        ],
        'pillars' => $scoreData['pillars'],
        'evaluators' => getEvaluatorSummary($assessment['id'])
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Method not allowed. Use GET.', 405);
}

$factoryId = isset($_GET['factory_id']) ? (int) $_GET['factory_id'] : 0;
$periodId = isset($_GET['period_id']) && $_GET['period_id'] !== '' ? (int) $_GET['period_id'] : null;
$scope = $_GET['scope'] ?? 'current';
$includeDraft = isset($_GET['include_draft']) && in_array(strtolower((string) $_GET['include_draft']), ['1', 'true', 'yes'], true);

if ($factoryId <= 0) {
    apiError('Missing or invalid factory_id.', 422, [
        'example' => getBaseUrl() . '/api/factory-assessment-summary.php?factory_id=1'
    ]);
}

if (!in_array($scope, ['current', 'latest', 'all'], true)) {
    apiError('Invalid scope. Allowed values: current, latest, all.', 422);
}

try {
    $company = getFactory($factoryId);
    if (!$company) {
        apiError('Factory/company not found.', 404, ['factory_id' => $factoryId]);
    }

    $assessments = getAssessmentsForFactory($factoryId, $scope, $periodId, $includeDraft);
    $assessmentPayloads = array_map('buildAssessmentPayload', $assessments);

    $response = [
        'success' => true,
        'meta' => [
            'api' => 'factory-assessment-summary',
            'version' => '1.0',
            'generated_at' => date('c'),
            'request' => [
                'factory_id' => $factoryId,
                'scope' => $periodId ? 'period_id' : $scope,
                'period_id' => $periodId,
                'include_draft' => $includeDraft
            ],
            'assessment_count' => count($assessmentPayloads)
        ],
        'factory' => buildFactoryPayload($company)
    ];

    if ($scope === 'all' && !$periodId) {
        $response['assessments'] = $assessmentPayloads;
    } else {
        $response['assessment'] = $assessmentPayloads[0] ?? null;
    }

    if (empty($assessmentPayloads)) {
        $response['message'] = $scope === 'current'
            ? 'No current assessment found for this factory. Try scope=latest, scope=all, or include_draft=1.'
            : 'No assessment found for this factory with the requested criteria.';
    }

    apiRespond($response);
} catch (Exception $e) {
    error_log('Factory assessment summary API error: ' . $e->getMessage());
    apiError('Internal server error while loading assessment summary.', 500);
}
?>
