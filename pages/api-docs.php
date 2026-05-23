<?php
/**
 * HICM V2025 - API Documentation & Design Tools
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCEO = hasRole('ceo');

if (!$isAdmin && !$isAuditor && !$isCEO) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$isPreview = !empty($_GET['_preview']);
$baseApiUrl = getBaseUrl() . '/api';
$docsTxtUrl = getBaseUrl() . '/FACTORY_ASSESSMENT_API.txt';

$endpoints = [
    [
        'id' => 'factories',
        'method' => 'GET',
        'path' => '/api/factories.php',
        'url' => $baseApiUrl . '/factories.php',
        'title' => 'Factories List',
        'subtitle' => 'ค้นหาและดึงรายชื่อโรงงานทั้งหมดสำหรับตรวจสอบ factory_id',
        'description' => 'ใช้เป็น discovery endpoint ก่อนเรียกข้อมูลประเมินรายโรงงาน รองรับ pagination, search, filter และ latest assessment แบบย่อ',
        'group' => 'Factory',
        'status' => 'Stable',
        'auth' => 'Internal / No API key yet',
        'defaultQuery' => 'per_page=5&include_latest_assessment=1',
        'params' => [
            ['name' => 'page', 'type' => 'integer', 'required' => 'No', 'default' => '1', 'description' => 'หน้าที่ต้องการ'],
            ['name' => 'per_page', 'type' => 'integer', 'required' => 'No', 'default' => '50', 'description' => 'จำนวนรายการต่อหน้า 1-200'],
            ['name' => 'search', 'type' => 'string', 'required' => 'No', 'default' => '-', 'description' => 'ค้นหาจากชื่อโรงงาน, ชื่ออังกฤษ, tax_id, ผู้ติดต่อ, อีเมล'],
            ['name' => 'province', 'type' => 'string', 'required' => 'No', 'default' => '-', 'description' => 'กรองจังหวัดแบบ exact match'],
            ['name' => 'industry', 'type' => 'string', 'required' => 'No', 'default' => '-', 'description' => 'กรองประเภทอุตสาหกรรมแบบ LIKE'],
            ['name' => 'company_size', 'type' => 'string', 'required' => 'No', 'default' => '-', 'description' => 'กรองขนาดบริษัทตามค่าจริงในฐานข้อมูล'],
            ['name' => 'status', 'type' => 'enum', 'required' => 'No', 'default' => 'active', 'description' => 'active, inactive, all'],
            ['name' => 'sort', 'type' => 'enum', 'required' => 'No', 'default' => 'company_name', 'description' => 'company_name, id, province, created_desc, updated_desc'],
            ['name' => 'include_latest_assessment', 'type' => 'boolean', 'required' => 'No', 'default' => 'true', 'description' => 'แนบ latest_assessment แบบย่อ']
        ],
        'examples' => [
            '/api/factories.php',
            '/api/factories.php?search=LEAR&per_page=10',
            '/api/factories.php?province=นครราชสีมา&include_latest_assessment=1',
            '/api/factories.php?status=all&sort=id'
        ],
        'response' => [
            'success' => true,
            'meta' => [
                'api' => 'factories',
                'pagination' => [
                    'total' => 24,
                    'page' => 1,
                    'per_page' => 50,
                    'total_pages' => 1
                ]
            ],
            'data' => [
                [
                    'factory_id' => 20,
                    'company_name' => 'บริษัทตัวอย่าง จำกัด',
                    'province' => 'นครราชสีมา',
                    'assessment_counts' => [
                        'total' => 5,
                        'submitted_or_later' => 4
                    ],
                    'summary_url' => 'http://localhost/hicm-v2025/api/factory-assessment-summary.php?factory_id=20&scope=latest'
                ]
            ]
        ]
    ],
    [
        'id' => 'summary',
        'method' => 'GET',
        'path' => '/api/factory-assessment-summary.php',
        'url' => $baseApiUrl . '/factory-assessment-summary.php',
        'title' => 'Factory Assessment Summary',
        'subtitle' => 'สรุปผลประเมินรายโรงงานตาม factory_id',
        'description' => 'ดึงข้อมูลโรงงาน รอบประเมิน คะแนนรวม คะแนนประเมินตนเอง คะแนนกรรมการ และคะแนนแยก Pillar H1/I2/C3/M4 โดยไม่ลงรายข้อย่อย',
        'group' => 'Assessment',
        'status' => 'Stable',
        'auth' => 'Internal / No API key yet',
        'defaultQuery' => 'factory_id=20&scope=latest',
        'params' => [
            ['name' => 'factory_id', 'type' => 'integer', 'required' => 'Yes', 'default' => '-', 'description' => 'companies.id ของโรงงาน'],
            ['name' => 'scope', 'type' => 'enum', 'required' => 'No', 'default' => 'current', 'description' => 'current, latest, all'],
            ['name' => 'period_id', 'type' => 'integer', 'required' => 'No', 'default' => '-', 'description' => 'ระบุรอบประเมินโดยตรง ใช้แทน scope'],
            ['name' => 'include_draft', 'type' => 'boolean', 'required' => 'No', 'default' => 'false', 'description' => 'รวม assessment สถานะ draft']
        ],
        'examples' => [
            '/api/factory-assessment-summary.php?factory_id=20',
            '/api/factory-assessment-summary.php?factory_id=20&scope=latest',
            '/api/factory-assessment-summary.php?factory_id=20&scope=all',
            '/api/factory-assessment-summary.php?factory_id=20&period_id=15'
        ],
        'response' => [
            'success' => true,
            'factory' => [
                'factory_id' => 20,
                'company_name' => 'บริษัทตัวอย่าง จำกัด'
            ],
            'assessment' => [
                'status' => 'evaluated',
                'period' => [
                    'id' => 15,
                    'name' => 'รอบประเมินประจำปี 2570'
                ],
                'scores' => [
                    'final' => [
                        'score' => 864.4,
                        'source' => 'auditor_assessment',
                        'hicm_level' => [
                            'level' => 4,
                            'name_en' => 'Excellence'
                        ]
                    ]
                ],
                'pillars' => [
                    'H1' => ['final' => ['score' => 258.4, 'max_score' => 300]],
                    'I2' => ['final' => ['score' => 258.4, 'max_score' => 300]],
                    'C3' => ['final' => ['score' => 184, 'max_score' => 200]],
                    'M4' => ['final' => ['score' => 163.6, 'max_score' => 200]]
                ]
            ]
        ]
    ]
];

function docsJson($value) {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .api-shell {
            max-width: 1440px;
            margin: 0 auto;
        }

        .api-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr);
            gap: 1.5rem;
            align-items: stretch;
            margin-bottom: 1.5rem;
        }

        .api-hero-main {
            background: #0f172a;
            color: white;
            border-radius: 8px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .api-hero-main::after {
            content: "";
            position: absolute;
            inset: auto 0 0 auto;
            width: 42%;
            height: 5px;
            background: linear-gradient(90deg, #10b981, #3b82f6, #f59e0b);
        }

        .api-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            color: #bfdbfe;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .api-hero h1 {
            margin: 0 0 0.75rem;
            font-size: 2rem;
            line-height: 1.2;
            letter-spacing: 0;
        }

        .api-hero p {
            margin: 0;
            color: #cbd5e1;
            line-height: 1.8;
            max-width: 760px;
        }

        .api-hero-side {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1.25rem;
            display: grid;
            gap: 0.85rem;
        }

        .api-mini-stat {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .api-mini-stat:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .api-mini-stat span {
            color: var(--gray-500);
            font-size: 0.82rem;
        }

        .api-mini-stat strong {
            color: var(--gray-900);
            font-weight: 700;
            text-align: right;
        }

        .api-layout {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        .api-nav-panel {
            position: sticky;
            top: 90px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
        }

        .api-nav-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .api-search {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.65rem 0.8rem;
            font: inherit;
        }

        .api-nav-list {
            padding: 0.65rem;
            display: grid;
            gap: 0.45rem;
        }

        .api-nav-item {
            display: grid;
            gap: 0.25rem;
            text-decoration: none;
            padding: 0.75rem;
            border-radius: 8px;
            color: var(--gray-700);
            border: 1px solid transparent;
        }

        .api-nav-item:hover,
        .api-nav-item.active {
            background: var(--primary-50);
            border-color: var(--primary-100);
            color: var(--primary-800);
        }

        .api-method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            min-width: 46px;
            height: 24px;
            padding: 0 0.5rem;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .api-nav-title {
            font-weight: 700;
            color: inherit;
        }

        .api-nav-path {
            color: var(--gray-500);
            font-size: 0.78rem;
            word-break: break-all;
        }

        .api-doc-stack {
            display: grid;
            gap: 1.25rem;
        }

        .api-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            align-items: center;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1rem;
        }

        .api-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .api-endpoint {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
        }

        .api-endpoint-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            display: grid;
            gap: 0.9rem;
        }

        .api-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
        }

        .api-title-row h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--gray-900);
        }

        .api-path-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--gray-900);
            color: white;
            border-radius: 8px;
            padding: 0.7rem 0.8rem;
            overflow: auto;
        }

        .api-path-row code {
            color: #e5e7eb;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .api-section {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .api-section:last-child {
            border-bottom: 0;
        }

        .api-section h3 {
            margin: 0 0 0.9rem;
            font-size: 1rem;
            color: var(--gray-900);
        }

        .api-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
        }

        .api-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .api-table th,
        .api-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
            text-align: left;
            vertical-align: top;
            font-size: 0.86rem;
        }

        .api-table th {
            background: var(--gray-50);
            color: var(--gray-600);
            font-weight: 700;
        }

        .api-table tr:last-child td {
            border-bottom: 0;
        }

        .api-code {
            background: #0b1220;
            color: #dbeafe;
            border-radius: 8px;
            padding: 1rem;
            overflow: auto;
            font-size: 0.82rem;
            line-height: 1.6;
            max-height: 460px;
        }

        .api-code code,
        .api-code pre {
            color: inherit;
            margin: 0;
            white-space: pre;
        }

        .api-examples {
            display: grid;
            gap: 0.55rem;
        }

        .api-example-line {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            background: var(--gray-50);
        }

        .api-example-line code {
            flex: 1;
            min-width: 0;
            word-break: break-all;
            color: var(--gray-800);
        }

        .api-try {
            display: grid;
            gap: 0.75rem;
        }

        .api-try-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 0.65rem;
        }

        .api-try-input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.7rem 0.8rem;
            font: inherit;
        }

        .api-result {
            display: none;
        }

        .api-result.show {
            display: block;
        }

        .api-chip {
            display: inline-flex;
            align-items: center;
            height: 26px;
            padding: 0 0.55rem;
            border-radius: 999px;
            background: var(--gray-100);
            color: var(--gray-700);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .api-chip.green {
            background: #dcfce7;
            color: #166534;
        }

        .api-chip.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .api-flow {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .api-flow-step {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.9rem;
            background: white;
        }

        .api-flow-step strong {
            display: block;
            color: var(--gray-900);
            margin-bottom: 0.35rem;
        }

        .api-flow-step span {
            display: block;
            color: var(--gray-500);
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .api-toast {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            background: var(--gray-900);
            color: white;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            opacity: 0;
            transform: translateY(8px);
            transition: 0.2s ease;
            z-index: 9999;
        }

        .api-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 1100px) {
            .api-hero,
            .api-layout,
            .api-flow {
                grid-template-columns: 1fr;
            }

            .api-nav-panel {
                position: static;
            }
        }

        @media (max-width: 720px) {
            .api-hero-main {
                padding: 1.25rem;
            }

            .api-hero h1 {
                font-size: 1.45rem;
            }

            .api-try-row {
                grid-template-columns: 1fr;
            }

            .api-toolbar {
                align-items: stretch;
            }

            .api-toolbar-actions,
            .api-toolbar-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="<?php echo $isPreview ? '' : 'has-sidebar'; ?>">
    <?php if (!$isPreview): ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <?php endif; ?>

    <main id="main-content">
        <div class="main-content">
            <div class="api-shell">
                <section class="api-hero">
                    <div class="api-hero-main">
                        <div class="api-kicker">
                            <span class="api-method">GET</span>
                            HICM API Workspace
                        </div>
                        <h1>API Documentation & Design Tools for Teams</h1>
                        <p>
                            ศูนย์กลางเอกสาร API สำหรับค้นหาโรงงานและดึงสรุปผลประเมิน HICM V2025
                            พร้อมตัวอย่าง request, response schema, quick test และแนวทาง integration สำหรับทีมพัฒนา
                        </p>
                    </div>
                    <aside class="api-hero-side">
                        <div class="api-mini-stat">
                            <span>Base URL</span>
                            <strong><?php echo htmlspecialchars($baseApiUrl); ?></strong>
                        </div>
                        <div class="api-mini-stat">
                            <span>Endpoints</span>
                            <strong><?php echo count($endpoints); ?> active</strong>
                        </div>
                        <div class="api-mini-stat">
                            <span>Format</span>
                            <strong>JSON UTF-8</strong>
                        </div>
                        <div class="api-mini-stat">
                            <span>Reference</span>
                            <strong>FACTORY_ASSESSMENT_API.txt</strong>
                        </div>
                    </aside>
                </section>

                <section class="api-toolbar">
                    <div>
                        <strong>Team workflow</strong>
                        <div style="color: var(--gray-500); font-size: .86rem; margin-top: .25rem;">
                            ค้นหา factory_id จาก Factories List แล้วนำไปเรียก Factory Assessment Summary
                        </div>
                    </div>
                    <div class="api-toolbar-actions">
                        <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($docsTxtUrl); ?>" target="_blank" rel="noopener">เปิด TXT Reference</a>
                        <button class="btn btn-secondary btn-sm" type="button" onclick="copyText('<?php echo htmlspecialchars($baseApiUrl, ENT_QUOTES); ?>')">Copy Base URL</button>
                    </div>
                </section>

                <section class="api-section" style="background: transparent; padding-left:0; padding-right:0;">
                    <div class="api-flow">
                        <div class="api-flow-step">
                            <strong>1. Discover</strong>
                            <span>เรียก /factories.php เพื่อค้นหาโรงงานและตรวจ factory_id</span>
                        </div>
                        <div class="api-flow-step">
                            <strong>2. Select</strong>
                            <span>เลือก factory_id จาก data[n].factory_id หรือ summary_url</span>
                        </div>
                        <div class="api-flow-step">
                            <strong>3. Summarize</strong>
                            <span>เรียก /factory-assessment-summary.php?factory_id=ID&scope=latest</span>
                        </div>
                        <div class="api-flow-step">
                            <strong>4. Visualize</strong>
                            <span>ใช้ scores.final และ pillars.H1/I2/C3/M4 ทำ dashboard</span>
                        </div>
                    </div>
                </section>

                <div class="api-layout">
                    <aside class="api-nav-panel">
                        <div class="api-nav-header">
                            <input id="endpointSearch" class="api-search" type="search" placeholder="Search endpoints..." oninput="filterEndpoints()">
                        </div>
                        <nav class="api-nav-list" id="apiNavList">
                            <?php foreach ($endpoints as $index => $endpoint): ?>
                            <a class="api-nav-item <?php echo $index === 0 ? 'active' : ''; ?>" href="#<?php echo htmlspecialchars($endpoint['id']); ?>" data-filter-text="<?php echo htmlspecialchars(strtolower($endpoint['title'] . ' ' . $endpoint['path'] . ' ' . $endpoint['group'])); ?>">
                                <span class="api-method"><?php echo htmlspecialchars($endpoint['method']); ?></span>
                                <span class="api-nav-title"><?php echo htmlspecialchars($endpoint['title']); ?></span>
                                <span class="api-nav-path"><?php echo htmlspecialchars($endpoint['path']); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </aside>

                    <div class="api-doc-stack">
                        <?php foreach ($endpoints as $endpoint): ?>
                        <article class="api-endpoint" id="<?php echo htmlspecialchars($endpoint['id']); ?>">
                            <header class="api-endpoint-header">
                                <div class="api-title-row">
                                    <span class="api-method"><?php echo htmlspecialchars($endpoint['method']); ?></span>
                                    <h2><?php echo htmlspecialchars($endpoint['title']); ?></h2>
                                    <span class="api-chip green"><?php echo htmlspecialchars($endpoint['status']); ?></span>
                                    <span class="api-chip blue"><?php echo htmlspecialchars($endpoint['group']); ?></span>
                                </div>
                                <div>
                                    <p style="margin: 0 0 .45rem; color: var(--gray-800); font-weight: 600;"><?php echo htmlspecialchars($endpoint['subtitle']); ?></p>
                                    <p style="margin: 0; color: var(--gray-500); line-height: 1.75;"><?php echo htmlspecialchars($endpoint['description']); ?></p>
                                </div>
                                <div class="api-path-row">
                                    <span class="api-method"><?php echo htmlspecialchars($endpoint['method']); ?></span>
                                    <code><?php echo htmlspecialchars($endpoint['path']); ?></code>
                                    <button class="btn btn-secondary btn-sm" type="button" onclick="copyText('<?php echo htmlspecialchars($endpoint['url'], ENT_QUOTES); ?>')">Copy</button>
                                </div>
                            </header>

                            <section class="api-section">
                                <h3>Overview</h3>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <span style="display:block;color:var(--gray-500);font-size:.78rem;">Authentication</span>
                                        <strong><?php echo htmlspecialchars($endpoint['auth']); ?></strong>
                                    </div>
                                    <div>
                                        <span style="display:block;color:var(--gray-500);font-size:.78rem;">Content Type</span>
                                        <strong>application/json</strong>
                                    </div>
                                    <div>
                                        <span style="display:block;color:var(--gray-500);font-size:.78rem;">Charset</span>
                                        <strong>UTF-8</strong>
                                    </div>
                                </div>
                            </section>

                            <section class="api-section">
                                <h3>Parameters</h3>
                                <div class="api-table-wrap">
                                    <table class="api-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Required</th>
                                                <th>Default</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($endpoint['params'] as $param): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($param['name']); ?></code></td>
                                                <td><?php echo htmlspecialchars($param['type']); ?></td>
                                                <td><?php echo htmlspecialchars($param['required']); ?></td>
                                                <td><?php echo htmlspecialchars($param['default']); ?></td>
                                                <td><?php echo htmlspecialchars($param['description']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="api-section">
                                <h3>Request Examples</h3>
                                <div class="api-examples">
                                    <?php foreach ($endpoint['examples'] as $example): ?>
                                    <div class="api-example-line">
                                        <span class="api-method">GET</span>
                                        <code><?php echo htmlspecialchars($example); ?></code>
                                        <button class="btn btn-secondary btn-sm" type="button" onclick="copyText('<?php echo htmlspecialchars(getBaseUrl() . $example, ENT_QUOTES); ?>')">Copy URL</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <section class="api-section">
                                <h3>Try It</h3>
                                <div class="api-try" data-endpoint="<?php echo htmlspecialchars($endpoint['url']); ?>">
                                    <div class="api-try-row">
                                        <input class="api-try-input" id="query-<?php echo htmlspecialchars($endpoint['id']); ?>" value="<?php echo htmlspecialchars($endpoint['defaultQuery']); ?>" aria-label="Query string for <?php echo htmlspecialchars($endpoint['title']); ?>">
                                        <button class="btn btn-primary" type="button" onclick="runEndpoint('<?php echo htmlspecialchars($endpoint['id']); ?>', '<?php echo htmlspecialchars($endpoint['url'], ENT_QUOTES); ?>')">Send Request</button>
                                        <button class="btn btn-secondary" type="button" onclick="copyRequest('<?php echo htmlspecialchars($endpoint['id']); ?>', '<?php echo htmlspecialchars($endpoint['url'], ENT_QUOTES); ?>')">Copy Request</button>
                                    </div>
                                    <div class="api-result" id="result-<?php echo htmlspecialchars($endpoint['id']); ?>">
                                        <pre class="api-code"><code></code></pre>
                                    </div>
                                </div>
                            </section>

                            <section class="api-section">
                                <h3>Response Example</h3>
                                <pre class="api-code"><code><?php echo htmlspecialchars(docsJson($endpoint['response'])); ?></code></pre>
                            </section>
                        </article>
                        <?php endforeach; ?>

                        <article class="api-endpoint">
                            <section class="api-section">
                                <h3>Error Model</h3>
                                <pre class="api-code"><code><?php echo htmlspecialchars(docsJson([
    'success' => false,
    'error' => [
        'code' => 422,
        'message' => 'Missing or invalid factory_id.',
        'details' => [
            'example' => getBaseUrl() . '/api/factory-assessment-summary.php?factory_id=1'
        ]
    ]
])); ?></code></pre>
                            </section>
                            <section class="api-section">
                                <h3>Scoring Notes</h3>
                                <div class="api-table-wrap">
                                    <table class="api-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Meaning</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>scores.self_assessment</code></td><td>คะแนนรวมจากการประเมินตนเอง</td></tr>
                                            <tr><td><code>scores.auditor_assessment</code></td><td>คะแนนรวมจากกรรมการ หรือค่าเฉลี่ย/aggregate ที่ระบบบันทึกไว้</td></tr>
                                            <tr><td><code>scores.final</code></td><td>คะแนนสุดท้ายที่ควรใช้แสดงใน dashboard</td></tr>
                                            <tr><td><code>pillars.H1/I2/C3/M4</code></td><td>คะแนนแยกตาม 4 Pillars โดย H1/I2 เต็ม 300 และ C3/M4 เต็ม 200</td></tr>
                                            <tr><td><code>stored_values</code></td><td>ค่ารวมที่บันทึกอยู่ในตาราง assessments ใช้เทียบตรวจสอบกับการคำนวณจาก assessment_scores</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="api-toast" id="apiToast">Copied</div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        function showToast(message) {
            const toast = document.getElementById('apiToast');
            toast.textContent = message || 'Done';
            toast.classList.add('show');
            window.clearTimeout(window.__apiToastTimer);
            window.__apiToastTimer = window.setTimeout(() => toast.classList.remove('show'), 1800);
        }

        async function copyText(text) {
            try {
                await navigator.clipboard.writeText(text);
                showToast('Copied to clipboard');
            } catch (error) {
                const temp = document.createElement('textarea');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                temp.remove();
                showToast('Copied to clipboard');
            }
        }

        function buildRequestUrl(endpointUrl, id) {
            const query = document.getElementById('query-' + id).value.trim();
            return query ? endpointUrl + '?' + query.replace(/^\?/, '') : endpointUrl;
        }

        function copyRequest(id, endpointUrl) {
            copyText(buildRequestUrl(endpointUrl, id));
        }

        async function runEndpoint(id, endpointUrl) {
            const result = document.getElementById('result-' + id);
            const code = result.querySelector('code');
            result.classList.add('show');
            code.textContent = 'Loading...';

            try {
                const response = await fetch(buildRequestUrl(endpointUrl, id), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                const text = await response.text();
                try {
                    code.textContent = JSON.stringify(JSON.parse(text), null, 2);
                } catch (error) {
                    code.textContent = text;
                }
            } catch (error) {
                code.textContent = JSON.stringify({ success: false, error: error.message }, null, 2);
            }
        }

        function filterEndpoints() {
            const keyword = document.getElementById('endpointSearch').value.toLowerCase().trim();
            document.querySelectorAll('.api-nav-item').forEach(item => {
                const match = item.dataset.filterText.includes(keyword);
                item.style.display = match ? '' : 'none';
            });
        }

        document.querySelectorAll('.api-nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.api-nav-item').forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
            });
        });
    </script>
</body>
</html>
