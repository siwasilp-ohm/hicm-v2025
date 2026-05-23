<?php
/**
 * HICM V2025 - Factories List API
 *
 * GET /api/factories.php
 * GET /api/factories.php?search=food&province=นครราชสีมา&include_latest_assessment=1
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/assessment.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function factoriesApiRespond($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function factoriesApiError($message, $statusCode = 400, $details = []) {
    factoriesApiRespond([
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $message,
            'details' => $details
        ]
    ], $statusCode);
}

function factoriesDbColumnExists($tableName, $columnName) {
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

function factoriesCleanText($value) {
    if ($value === null) {
        return null;
    }
    return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function factoriesNormalizeIndustryTypes($value) {
    if ($value === null || $value === '') {
        return [];
    }

    $parts = strpos($value, '|') !== false ? explode('|', $value) : [$value];
    return array_values(array_filter(array_map('trim', $parts), function ($item) {
        return $item !== '';
    }));
}

function factoriesFormatDate($value) {
    if (!$value) {
        return null;
    }
    return date('Y-m-d', strtotime($value));
}

function factoriesFormatDateTime($value) {
    if (!$value) {
        return null;
    }
    return date('c', strtotime($value));
}

function factoriesScoreBlock($score) {
    if ($score === null || $score === '') {
        return null;
    }

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

function factoriesBoolParam($name, $default = false) {
    if (!isset($_GET[$name])) {
        return $default;
    }
    return in_array(strtolower((string) $_GET[$name]), ['1', 'true', 'yes'], true);
}

function factoriesBuildWhere(&$params) {
    $where = ['1=1'];

    $status = $_GET['status'] ?? 'active';
    if ($status === 'active') {
        $where[] = 'c.is_active = 1 AND (u.is_active = 1 OR u.is_active IS NULL)';
    } elseif ($status === 'inactive') {
        $where[] = '(c.is_active = 0 OR u.is_active = 0)';
    } elseif ($status !== 'all') {
        factoriesApiError('Invalid status. Allowed values: active, inactive, all.', 422);
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[] = '(c.company_name LIKE ? OR c.company_name_en LIKE ? OR c.tax_id LIKE ? OR c.contact_name LIKE ? OR c.contact_email LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $province = trim($_GET['province'] ?? '');
    if ($province !== '') {
        $where[] = 'c.province = ?';
        $params[] = $province;
    }

    $industry = trim($_GET['industry'] ?? '');
    if ($industry !== '') {
        $where[] = 'c.industry_type LIKE ?';
        $params[] = '%' . $industry . '%';
    }

    $companySize = trim($_GET['company_size'] ?? '');
    if ($companySize !== '') {
        $where[] = 'c.company_size = ?';
        $params[] = $companySize;
    }

    return implode(' AND ', $where);
}

function factoriesFetchLatestAssessments($factoryIds) {
    if (empty($factoryIds)) {
        return [];
    }

    $db = getDB();
    $showAuditorSelect = factoriesDbColumnExists('assessment_periods', 'show_auditor_results')
        ? 'ap.show_auditor_results'
        : '0 AS show_auditor_results';
    $placeholders = implode(',', array_fill(0, count($factoryIds), '?'));

    $stmt = $db->prepare("
        SELECT a.id, a.company_id, a.period_id, a.status, a.self_total_score,
               a.auditor_total_score, a.final_score, a.hicm_level,
               a.submitted_at, a.evaluated_at, a.completed_at, a.updated_at,
               ap.year, ap.name as period_name, ap.status as period_status,
               ap.start_date, ap.end_date, {$showAuditorSelect}
        FROM assessments a
        JOIN assessment_periods ap ON a.period_id = ap.id
        WHERE a.company_id IN ({$placeholders})
          AND NOT EXISTS (
              SELECT 1
              FROM assessments ax
              JOIN assessment_periods apx ON ax.period_id = apx.id
              WHERE ax.company_id = a.company_id
                AND (
                    apx.year > ap.year
                    OR (apx.year = ap.year AND apx.start_date > ap.start_date)
                    OR (apx.year = ap.year AND apx.start_date = ap.start_date AND ax.updated_at > a.updated_at)
                    OR (apx.year = ap.year AND apx.start_date = ap.start_date AND ax.updated_at = a.updated_at AND ax.id > a.id)
                )
          )
        ORDER BY ap.year DESC, ap.start_date DESC, a.updated_at DESC
    ");
    $stmt->execute($factoryIds);

    $latest = [];
    foreach ($stmt->fetchAll() as $row) {
        $latest[(int) $row['company_id']] = [
            'id' => (int) $row['id'],
            'status' => $row['status'],
            'period' => [
                'id' => (int) $row['period_id'],
                'year' => (int) $row['year'],
                'name' => factoriesCleanText($row['period_name']),
                'status' => $row['period_status'],
                'show_auditor_results' => (bool) $row['show_auditor_results'],
                'start_date' => factoriesFormatDate($row['start_date']),
                'end_date' => factoriesFormatDate($row['end_date'])
            ],
            'scores' => [
                'self_assessment' => factoriesScoreBlock($row['self_total_score']),
                'auditor_assessment' => factoriesScoreBlock($row['auditor_total_score']),
                'final' => factoriesScoreBlock($row['final_score'])
            ],
            'timestamps' => [
                'submitted_at' => factoriesFormatDateTime($row['submitted_at']),
                'evaluated_at' => factoriesFormatDateTime($row['evaluated_at']),
                'completed_at' => factoriesFormatDateTime($row['completed_at']),
                'updated_at' => factoriesFormatDateTime($row['updated_at'])
            ],
            'summary_url' => getBaseUrl() . '/api/factory-assessment-summary.php?factory_id=' . (int) $row['company_id'] . '&scope=latest'
        ];
    }

    return $latest;
}

function factoriesFetchAssessmentCounts($factoryIds) {
    if (empty($factoryIds)) {
        return [];
    }

    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($factoryIds), '?'));
    $stmt = $db->prepare("
        SELECT company_id,
               COUNT(*) as total,
               SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
               SUM(CASE WHEN status IN ('submitted', 'under_review', 'evaluated', 'completed') THEN 1 ELSE 0 END) as submitted_or_later_count
        FROM assessments
        WHERE company_id IN ({$placeholders})
        GROUP BY company_id
    ");
    $stmt->execute($factoryIds);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['company_id']] = [
            'total' => (int) $row['total'],
            'draft' => (int) $row['draft_count'],
            'submitted_or_later' => (int) $row['submitted_or_later_count']
        ];
    }
    return $counts;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    factoriesApiError('Method not allowed. Use GET.', 405);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 50);
if ($perPage < 1 || $perPage > 200) {
    factoriesApiError('Invalid per_page. Allowed range: 1-200.', 422);
}

$includeLatestAssessment = factoriesBoolParam('include_latest_assessment', true);
$params = [];
$whereSql = factoriesBuildWhere($params);

$sort = $_GET['sort'] ?? 'company_name';
$allowedSorts = [
    'company_name' => 'c.company_name ASC',
    'id' => 'c.id ASC',
    'province' => 'c.province ASC, c.company_name ASC',
    'created_desc' => 'c.created_at DESC',
    'updated_desc' => 'c.updated_at DESC'
];
if (!isset($allowedSorts[$sort])) {
    factoriesApiError('Invalid sort. Allowed values: company_name, id, province, created_desc, updated_desc.', 422);
}

try {
    $db = getDB();
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM companies c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $latitudeSelect = factoriesDbColumnExists('companies', 'latitude') ? 'c.latitude' : 'NULL AS latitude';
    $longitudeSelect = factoriesDbColumnExists('companies', 'longitude') ? 'c.longitude' : 'NULL AS longitude';

    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;

    $stmt = $db->prepare("
        SELECT c.id, c.company_name, c.company_name_en, c.tax_id,
               c.address, c.district, c.province, c.postal_code,
               {$latitudeSelect}, {$longitudeSelect},
               c.phone, c.website, c.industry_type, c.company_size,
               c.employee_count, c.established_year, c.contact_name,
               c.contact_position, c.contact_email, c.contact_phone,
               c.logo, c.description, c.is_active, c.created_at, c.updated_at,
               u.username, u.email as user_email, u.name as user_name,
               u.phone as user_phone, u.is_active as user_is_active
        FROM companies c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE {$whereSql}
        ORDER BY {$allowedSorts[$sort]}
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($listParams);
    $rows = $stmt->fetchAll();

    $factoryIds = array_map(function ($row) {
        return (int) $row['id'];
    }, $rows);
    $assessmentCounts = factoriesFetchAssessmentCounts($factoryIds);
    $latestAssessments = $includeLatestAssessment ? factoriesFetchLatestAssessments($factoryIds) : [];

    $factories = [];
    foreach ($rows as $row) {
        $factoryId = (int) $row['id'];
        $item = [
            'id' => $factoryId,
            'factory_id' => $factoryId,
            'company_name' => factoriesCleanText($row['company_name']),
            'company_name_en' => factoriesCleanText($row['company_name_en']),
            'tax_id' => $row['tax_id'],
            'industry_types' => factoriesNormalizeIndustryTypes($row['industry_type']),
            'company_size' => $row['company_size'],
            'employee_count' => $row['employee_count'] !== null ? (int) $row['employee_count'] : null,
            'established_year' => $row['established_year'] !== null ? (int) $row['established_year'] : null,
            'address' => [
                'address' => factoriesCleanText($row['address']),
                'district' => factoriesCleanText($row['district']),
                'province' => factoriesCleanText($row['province']),
                'postal_code' => $row['postal_code'],
                'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null
            ],
            'contact' => [
                'name' => factoriesCleanText($row['contact_name'] ?: $row['user_name']),
                'position' => factoriesCleanText($row['contact_position']),
                'email' => $row['contact_email'] ?: $row['user_email'],
                'phone' => $row['contact_phone'] ?: $row['user_phone'],
                'office_phone' => $row['phone'],
                'website' => $row['website']
            ],
            'is_active' => (bool) $row['is_active'],
            'user_is_active' => $row['user_is_active'] !== null ? (bool) $row['user_is_active'] : null,
            'assessment_counts' => $assessmentCounts[$factoryId] ?? [
                'total' => 0,
                'draft' => 0,
                'submitted_or_later' => 0
            ],
            'summary_url' => getBaseUrl() . '/api/factory-assessment-summary.php?factory_id=' . $factoryId . '&scope=latest',
            'created_at' => factoriesFormatDateTime($row['created_at']),
            'updated_at' => factoriesFormatDateTime($row['updated_at'])
        ];

        if ($includeLatestAssessment) {
            $item['latest_assessment'] = $latestAssessments[$factoryId] ?? null;
        }

        $factories[] = $item;
    }

    factoriesApiRespond([
        'success' => true,
        'meta' => [
            'api' => 'factories',
            'version' => '1.0',
            'generated_at' => date('c'),
            'request' => [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $_GET['search'] ?? null,
                'province' => $_GET['province'] ?? null,
                'industry' => $_GET['industry'] ?? null,
                'company_size' => $_GET['company_size'] ?? null,
                'status' => $_GET['status'] ?? 'active',
                'sort' => $sort,
                'include_latest_assessment' => $includeLatestAssessment
            ],
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
                'has_next' => ($page * $perPage) < $total,
                'has_prev' => $page > 1
            ]
        ],
        'data' => $factories
    ]);
} catch (Exception $e) {
    error_log('Factories API error: ' . $e->getMessage());
    factoriesApiError('Internal server error while loading factories.', 500);
}
?>
