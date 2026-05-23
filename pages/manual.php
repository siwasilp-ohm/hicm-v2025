<?php
/**
 * HICM V2025 - คู่มือการใช้งานระบบ (User Manual)
 * Professional manual page with ToC, role map, formulas, and admin-editable content
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);

// Load manual content (JSON)
$manualFile = dirname(__DIR__) . '/manual_content.json';
$manualData = ['content' => '', 'download_files' => [], 'sections' => ['intro' => '', 'part2' => '', 'features' => '', 'references' => '']];
if (file_exists($manualFile)) {
    $raw = json_decode(file_get_contents($manualFile), true);
    if (is_array($raw)) {
        $manualData = array_merge($manualData, $raw);
        if (!empty($raw['sections']) && is_array($raw['sections'])) {
            $manualData['sections'] = array_merge($manualData['sections'], $raw['sections']);
        }
    }
}

$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือการใช้งานระบบ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root { --manual-sidebar-width: 260px; }
        .manual-layout { display: flex; gap: 2rem; max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .manual-toc { flex: 0 0 var(--manual-sidebar-width); position: sticky; top: 5rem; height: fit-content; max-height: calc(100vh - 7rem); overflow-y: auto; }
        .manual-main { flex: 1; min-width: 0; }
        .manual-toc-title { font-size: 0.875rem; font-weight: 700; color: var(--gray-600); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .manual-toc-list { list-style: none; padding: 0; margin: 0; }
        .manual-toc-list a { display: block; padding: 0.4rem 0.75rem; color: var(--gray-600); text-decoration: none; border-radius: var(--radius-md); font-size: 0.9rem; transition: all 0.2s; }
        .manual-toc-list a:hover { background: var(--primary-50); color: var(--primary-700); }
        .manual-toc-list a.active { background: var(--primary-100); color: var(--primary-700); font-weight: 600; }
        .manual-toc-list .toc-h3 { padding-left: 1.25rem; font-size: 0.85rem; }

        .manual-hero { background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%); color: #fff; padding: 3rem 2rem; border-radius: var(--radius-2xl); margin-bottom: 2rem; position: relative; overflow: hidden; }
        .manual-hero::before { content: ''; position: absolute; top: -50%; right: -20%; width: 60%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); }
        .manual-hero h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .manual-hero p { opacity: 0.95; font-size: 1.1rem; }
        .manual-hero-actions { margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .manual-hero-actions .btn { background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4); padding: 0.5rem 1.25rem; border-radius: var(--radius-lg); text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .manual-hero-actions .btn:hover { background: rgba(255,255,255,0.3); }

        .manual-section { background: #fff; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; padding: 2rem; border: 1px solid var(--gray-100); }
        .manual-section h2 { font-size: 1.5rem; color: var(--primary-700); margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--primary-100); display: flex; align-items: center; gap: 0.5rem; }
        .manual-section h3 { font-size: 1.2rem; color: var(--gray-800); margin: 1.5rem 0 0.75rem; }
        .manual-section h4 { font-size: 1rem; color: var(--gray-700); margin: 1rem 0 0.5rem; }

        /* Role Map */
        .role-map { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin: 1.5rem 0; }
        .role-card { padding: 1.25rem; border-radius: var(--radius-lg); border-left: 4px solid; transition: transform 0.2s, box-shadow 0.2s; }
        .role-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .role-card.admin { border-color: var(--primary-500); background: linear-gradient(135deg, var(--primary-50) 0%, #fff 100%); }
        .role-card.auditor { border-color: var(--pillar-i2); background: linear-gradient(135deg, var(--pillar-i2-light) 0%, #fff 100%); }
        .role-card.company { border-color: var(--pillar-h1); background: linear-gradient(135deg, var(--pillar-h1-light) 0%, #fff 100%); }
        .role-card.ceo { border-color: var(--pillar-m4); background: linear-gradient(135deg, var(--pillar-m4-light) 0%, #fff 100%); }
        .role-card h4 { margin: 0 0 0.5rem; font-size: 1.1rem; }
        .role-card ul { margin: 0; padding-left: 1.25rem; font-size: 0.9rem; color: var(--gray-700); line-height: 1.7; }
        .role-card .role-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; margin-bottom: 0.5rem; }
        .role-card.admin .role-badge { background: var(--primary-200); color: var(--primary-800); }
        .role-card.auditor .role-badge { background: var(--pillar-i2-light); color: var(--primary-800); }
        .role-card.company .role-badge { background: var(--pillar-h1-light); color: #065f46; }
        .role-card.ceo .role-badge { background: var(--pillar-m4-light); color: #5b21b6; }

        /* Formulas */
        .formula-block { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 1.25rem; margin: 1rem 0; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.95rem; overflow-x: auto; }
        .formula-block code { color: var(--primary-700); }
        .formula-desc { font-size: 0.9rem; color: var(--gray-600); margin-top: 0.5rem; }
        .pillar-table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .pillar-table th, .pillar-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        .pillar-table th { background: var(--gray-50); font-weight: 600; }
        .pillar-table td:first-child { font-weight: 500; }
        .level-table { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.75rem; margin: 1rem 0; }
        .level-badge { padding: 1rem; border-radius: var(--radius-lg); text-align: center; border: 1px solid var(--gray-200); }
        .level-badge .range { font-size: 0.85rem; font-weight: 700; color: var(--gray-700); }
        .level-badge .name { font-size: 0.9rem; margin-top: 0.25rem; }
        .level-badge .name-en { font-size: 0.75rem; color: var(--gray-500); }

        /* Reference links */
        .ref-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin: 1.5rem 0; }
        .ref-card { display: flex; align-items: flex-start; gap: 1rem; padding: 1.25rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200); text-decoration: none; color: inherit; transition: all 0.2s; }
        .ref-card:hover { background: var(--primary-50); border-color: var(--primary-200); }
        .ref-card-icon { width: 48px; height: 48px; background: var(--primary-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ref-card-title { font-weight: 600; margin-bottom: 0.25rem; }
        .ref-card-desc { font-size: 0.875rem; color: var(--gray-600); }
        .manual-content-rendered { line-height: 1.8; }
        .manual-content-rendered p { margin-bottom: 1rem; }
        .manual-content-rendered ul, .manual-content-rendered ol { margin: 0.75rem 0 1rem 1.5rem; }
        .manual-content-rendered li { margin-bottom: 0.5rem; }
        .manual-content-rendered a { color: var(--primary-600); text-decoration: underline; }
        .manual-content-rendered a:hover { color: var(--primary-700); }
        .manual-content-rendered table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .manual-content-rendered th, .manual-content-rendered td { padding: 0.5rem 0.75rem; border: 1px solid var(--gray-200); }
        .manual-content-rendered th { background: var(--gray-50); }

        .manual-animate { opacity: 0; transform: translateY(20px); }
        .manual-animate.visible { opacity: 1; transform: translateY(0); transition: opacity 0.5s ease, transform 0.5s ease; }
        @media (max-width: 1024px) { .manual-toc { display: none; } .manual-layout { flex-direction: column; } }
        @media (max-width: 600px) { .manual-hero { padding: 2rem 1.5rem; } .manual-section { padding: 1.25rem; } .role-map { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <main class="main-wrapper">
        <div class="main-content">
        <div class="manual-layout">
            <!-- Sidebar ToC -->
            <aside class="manual-toc animate__animated animate__fadeInLeft">
                <div class="manual-toc-title">สารบัญ</div>
                <ul class="manual-toc-list">
                    <li><a href="#section-intro" class="toc-link">บทนำและหลักการ</a></li>
                    <li><a href="#section-part2" class="toc-link">โครงสร้างและระบบคะแนน</a></li>
                    <li><a href="#section-role-map" class="toc-link">แผนที่บทบาทผู้ใช้</a></li>
                    <li><a href="#section-features" class="toc-link">ฟีเจอร์และฟังก์ชัน</a></li>
                    <li><a href="#section-formulas" class="toc-link">สูตรการคำนวณ</a></li>
                    <li><a href="#section-score-levels" class="toc-link">ระดับคะแนนตัวชี้วัด</a></li>
                    <li><a href="#section-hicm-levels" class="toc-link">ระดับการรับรอง HICM</a></li>
                    <li><a href="#section-references" class="toc-link">เอกสารอ้างอิง</a></li>
                </ul>
            </aside>

            <div class="manual-main">
                <!-- Hero -->
                <section class="manual-hero animate__animated animate__fadeInDown">
                    <h1>คู่มือการใช้งานระบบ HICM V2025</h1>
                    <p>ระบบแบบประเมินสถานประกอบการตามเกณฑ์ Health Industrial Community Model ครบถ้วนทุกบทบาท ฟีเจอร์ และสูตรการคำนวณ</p>
                    <div class="manual-hero-actions">
            <?php if ($isAdmin): ?>
                            <a href="manual-edit.php" class="btn">✏️ แก้ไขคู่มือ</a>
                        <?php endif; ?>
                        <?php 
                        $dlFiles = $manualData['download_files'] ?? [];
                        foreach ($dlFiles as $i => $df): 
                            if (empty($df['filename'])) continue;
                        ?>
                        <a href="<?php echo $baseUrl; ?>/api/download-manual-ref.php?id=<?php echo $i; ?>" class="btn">📄 <?php echo htmlspecialchars($df['label'] ?? $df['filename']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- บทนำและหลักการ (Editable) -->
                <section id="section-intro" class="manual-section manual-animate" data-section>
                    <h2>📖 บทนำและหลักการ</h2>
                    <div id="manual-content-intro" class="manual-content-rendered"></div>
                </section>

                <!-- ส่วนที่ 2: โครงสร้างและระบบคะแนน (Editable) -->
                <section id="section-part2" class="manual-section manual-animate" data-section>
                    <h2>📊 โครงสร้างและระบบคะแนน</h2>
                    <div id="manual-content-part2" class="manual-content-rendered"></div>
                </section>

                <!-- แผนที่บทบาทผู้ใช้ (Role Map) -->
                <section id="section-role-map" class="manual-section manual-animate" data-section>
                    <h2>👥 แผนที่บทบาทผู้ใช้ (Role Map)</h2>
                    <p class="manual-subtitle" style="margin-bottom:1rem;">แต่ละบทบาทมีสิทธิ์และหน้าที่แตกต่างกัน ดังนี้</p>
                    <div class="role-map">
                        <div class="role-card admin">
                            <span class="role-badge">Admin</span>
                            <h4>ผู้ดูแลระบบ</h4>
                            <ul>
                                <li>จัดการผู้ใช้ (Users)</li>
                                <li>ตั้งค่าตัวชี้วัด (Indicators)</li>
                                <li>จัดการรอบการประเมิน (Periods)</li>
                                <li>แก้ไขคู่มือการใช้งาน</li>
                                <li>ดูรายงานและส่งออกข้อมูล</li>
                                <li>จัดการประกาศ ข่าวสาร</li>
                                <li>ตั้งค่าระบบ (SMTP, Email)</li>
                            </ul>
                        </div>
                        <div class="role-card auditor">
                            <span class="role-badge">Auditor</span>
                            <h4>กรรมการประเมิน</h4>
                            <ul>
                                <li>ดูรายการประเมินที่มอบหมาย</li>
                                <li>ประเมินและให้คะแนน (Evaluation)</li>
                                <li>ตรวจสอบหลักฐานแนบ</li>
                                <li>เขียนความเห็นและข้อเสนอแนะ</li>
                                <li>ดูบริษัทและสถานประกอบการ</li>
                                <li>ดูผลการประเมินและรายงาน</li>
                            </ul>
                        </div>
                        <div class="role-card company">
                            <span class="role-badge">Company</span>
                            <h4>สถานประกอบการ</h4>
                            <ul>
                                <li>กรอกแบบประเมินตนเอง (Self-Assessment)</li>
                                <li>แนบไฟล์หลักฐาน</li>
                                <li>ดูผลการประเมิน</li>
                                <li>บันทึกพัฒนาการ (Milestones)</li>
                                <li>จัดการโปรไฟล์บริษัท</li>
                            </ul>
                        </div>
                        <div class="role-card ceo">
                            <span class="role-badge">CEO</span>
                            <h4>ผู้บริหาร</h4>
                            <ul>
                                <li>ดูรายงานสรุป</li>
                                <li>วิเคราะห์คะแนนรวม</li>
                                <li>ดู Leaderboard</li>
                                <li>ดูข้อมูลสถานประกอบการ</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- ฟีเจอร์และฟังก์ชัน -->
                <section id="section-features" class="manual-section manual-animate" data-section>
                    <h2>⚙️ ฟีเจอร์และฟังก์ชันหลัก</h2>
                    <div id="manual-content-features" class="manual-content-rendered"></div>
                </section>

                <!-- สูตรการคำนวณ (Dynamic from config) -->
                <section id="section-formulas" class="manual-section manual-animate" data-section>
                    <h2>📐 สูตรการคำนวณคะแนน</h2>

                    <h3>1. คะแนนรายตัวชี้วัด (Indicator)</h3>
                    <p>แต่ละตัวชี้วัดให้คะแนนระดับ 0, 0.25, 0.5, 0.75 หรือ 1.0 ตามเกณฑ์ที่กำหนด</p>
                    <div class="formula-block">
                        <code>indicator_score ∈ {0, 0.25, 0.5, 0.75, 1.0}</code>
                    </div>

                    <h3>2. คะแนนรายเสาหลัก (Pillar)</h3>
                    <p>คำนวณจากค่าเฉลี่ยของตัวชี้วัดที่ทำการประเมิน (ไม่รวม N/A) × น้ำหนักเสาหลัก</p>
                    <div class="formula-block">
                        <code>pillarScore = (Σ indicator_scores / จำนวนตัวชี้วัดที่ประเมิน) × pillarWeight</code>
                    </div>
                    <div class="formula-desc">ตัวชี้วัดที่ระบุ "N/A" (ไม่ใช้กับองค์กร) จะไม่นำมาคำนวณ</div>

                    <h3>3. โครงสร้างน้ำหนัก 4 Pillars</h3>
                    <table class="pillar-table">
                        <thead>
                            <tr><th>Pillar</th><th>ชื่อ</th><th>น้ำหนัก (คะแนน)</th><th>จำนวนตัวชี้วัด</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (PILLARS as $code => $p): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($code); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['name_th']); ?></td>
                                <td><?php echo $p['weight']; ?></td>
                                <td><?php echo $p['indicators_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:600;"><td colspan="2">รวม</td><td>1,000</td><td>60</td></tr>
                        </tbody>
                    </table>

                    <h3>4. คะแนนรวมสุดท้าย</h3>
                    <div class="formula-block">
                        <code>totalScore = H1_score + I2_score + C3_score + M4_score</code>
                    </div>
                    <div class="formula-desc">คะแนนรวมสูงสุด 1,000 คะแนน</div>
                </section>

                <!-- ระดับคะแนนตัวชี้วัด -->
                <section id="section-score-levels" class="manual-section manual-animate" data-section>
                    <h2>📊 ระดับคะแนนตัวชี้วัด</h2>
                    <table class="pillar-table">
                        <thead>
                            <tr><th>คะแนน</th><th>ระดับ</th><th>คำอธิบาย</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (SCORE_LEVELS as $score => $level): ?>
                            <tr>
                                <td><strong><?php echo $score; ?></strong></td>
                                <td><?php echo htmlspecialchars($level['label']); ?></td>
                                <td><?php echo htmlspecialchars($level['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <!-- ระดับการรับรอง HICM -->
                <section id="section-hicm-levels" class="manual-section manual-animate" data-section>
                    <h2>🏆 ระดับการรับรอง HICM</h2>
                    <div class="level-table">
                        <?php foreach (HICM_LEVELS as $hl): ?>
                        <div class="level-badge" style="border-left: 4px solid var(--primary-500);">
                            <div class="range">Level <?php echo $hl['level']; ?>: <?php echo $hl['min']; ?>-<?php echo $hl['max']; ?></div>
                            <div class="name"><?php echo htmlspecialchars($hl['name']); ?></div>
                            <div class="name-en"><?php echo htmlspecialchars($hl['name_en']); ?></div>
                            <div class="formula-desc" style="margin-top:0.5rem;"><?php echo htmlspecialchars($hl['description']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- เอกสารอ้างอิง -->
                <section id="section-references" class="manual-section manual-animate" data-section>
                    <h2>📚 เอกสารอ้างอิง</h2>
                    <div id="manual-content-refs" class="manual-content-rendered"></div>
                    <div class="ref-cards" id="manual-ref-cards">
                        <?php 
                        $dlFiles = $manualData['download_files'] ?? [];
                        foreach ($dlFiles as $i => $df): 
                            if (empty($df['filename'])) continue;
                            $title = $df['label'] ?? $df['filename'];
                            $desc = $df['description'] ?? $df['filename'];
                        ?>
                        <a href="<?php echo $baseUrl; ?>/api/download-manual-ref.php?id=<?php echo $i; ?>" class="ref-card">
                            <div class="ref-card-icon">📄</div>
                            <div>
                                <div class="ref-card-title"><?php echo htmlspecialchars($title); ?></div>
                                <div class="ref-card-desc"><?php echo htmlspecialchars($desc); ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
                </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        (function() {
            const manualData = <?php echo json_encode($manualData); ?>;

            function renderMarkdown(md) {
                if (!md || typeof marked === 'undefined') return md || '';
                return marked.parse(md || '');
            }

            // Render editable sections
            const sections = manualData.sections || {};
            const content = manualData.content || '';

            const defaultIntro = '## บทนำ\n\n**HICM (Health Industrial Community Model)** เป็นโมเดลการประเมินสถานประกอบการด้านสุขภาวะที่ครอบคลุม 4 เสาหลักหลัก ได้แก่ การส่งเสริมสุขภาพ (H1) ความปลอดภัยและสิ่งแวดล้อม (I2) การมีส่วนร่วมกับชุมชน (C3) และการบริหารจัดการและความยั่งยืน (M4)\n\nรายละเอียดเพิ่มเติมโปรดดูในเอกสาร **ส่วนที่ 1 บทนำและหลักการ**';
            const defaultFeatures = '### ระบบหลัก\n- **ระบบประเมิน (Assessment)** — กรอกแบบประเมินตนเอง 60 ตัวชี้วัด แนบหลักฐาน\n- **ระบบคะแนน** — คำนวณ Real-time แสดงผล 4 Pillars\n- **ระบบรายงานและ Dashboard** — กราฟ Radar Chart สรุปคะแนน\n- **ระบบแนบไฟล์** — รองรับ JPG, PNG, PDF, DOC, XLSX\n- **ระบบผู้ใช้และสิทธิ์** — Admin, Auditor, Company, CEO\n- **ระบบ Milestone** — บันทึกพัฒนาการตามเป้าหมาย\n- **ระบบคู่มือออนไลน์** — Admin แก้ไขได้';
            const defaultRefs = 'เอกสารอ้างอิงจากไฟล์ Excel ที่ใช้ประกอบคู่มือนี้';

            function setContent(id, html) {
                const el = document.getElementById(id);
                if (el) el.innerHTML = html;
            }
            setContent('manual-content-intro', renderMarkdown(sections.intro || defaultIntro));
            const defaultPart2 = '## โครงสร้างและระบบคะแนน\n\nดูรายละเอียดในส่วนสูตรการคำนวณ และระดับการรับรอง HICM ด้านล่าง';
            setContent('manual-content-part2', renderMarkdown(sections.part2 || defaultPart2));
            setContent('manual-content-features', renderMarkdown(sections.features || defaultFeatures));
            setContent('manual-content-refs', renderMarkdown(sections.references || defaultRefs));

            // Scroll spy for ToC
            const sectionsEl = document.querySelectorAll('[data-section]');
            const tocLinks = document.querySelectorAll('.toc-link');

            function updateActiveToc() {
                let current = '';
                const scrollY = window.scrollY + 120;
                sectionsEl.forEach(el => {
                    const top = el.offsetTop;
                    if (scrollY >= top) current = el.id;
                });
                tocLinks.forEach(a => {
                    a.classList.toggle('active', a.getAttribute('href') === '#' + current);
                });
            }
            window.addEventListener('scroll', updateActiveToc);
            updateActiveToc();

            // Intersection Observer for animate on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) e.target.classList.add('visible');
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
            document.querySelectorAll('.manual-animate').forEach(el => observer.observe(el));
        })();
    </script>
</body>
</html>
