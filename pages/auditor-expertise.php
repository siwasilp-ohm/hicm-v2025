<?php
/**
 * HICM V2025 Assessment System - Auditor HICM Expertise Management (Admin Only)
 * จัดการความเชี่ยวชาญของกรรมการตาม HICM Pillars (H1, I2, C3, M4)
 * เวอร์ชันเรียบง่าย - ใช้แค่ 4 หัวข้อหลัก
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$errors = [];
$success = false;
$db = getDB();

// Check if user has hicm_expertise column
try {
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'hicm_expertise'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $db->prepare("ALTER TABLE users ADD COLUMN hicm_expertise TEXT AFTER expertise")->execute();
    }
} catch (Exception $e) {
    $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_auditor_expertise'])) {
        $auditorId = intval($_POST['auditor_id']);
        $selectedPillars = $_POST['hicm_pillars'] ?? [];
        $expertiseStr = implode('|', $selectedPillars);
        
        $stmt = $db->prepare("UPDATE users SET hicm_expertise = ? WHERE id = ?");
        $stmt->execute([$expertiseStr, $auditorId]);
        $success = 'อัปเดตความเชี่ยวชาญของกรรมการสำเร็จ';
    }
    
    if (isset($_POST['bulk_update'])) {
        $updates = $_POST['auditor_pillars'] ?? [];
        foreach ($updates as $auditorId => $pillars) {
            $expertiseStr = implode('|', $pillars);
            $stmt = $db->prepare("UPDATE users SET hicm_expertise = ? WHERE id = ?");
            $stmt->execute([$expertiseStr, intval($auditorId)]);
        }
        $success = 'อัปเดตความเชี่ยวชาญทั้งหมดสำเร็จ';
    }
}

// Get all auditors with their expertise
$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.expertise, u.hicm_expertise, u.organization_id,
           o.name as organization_name
    FROM users u
    LEFT JOIN organizations o ON u.organization_id = o.id
    WHERE u.role = 'auditor' AND u.is_active = 1 
    ORDER BY u.name
");
$stmt->execute();
$auditors = $stmt->fetchAll();

// Pillar info from config
$pillars = PILLARS;

// Count auditors per pillar
function countAuditorsWithPillar($auditors, $pillarCode) {
    $count = 0;
    foreach ($auditors as $auditor) {
        $expertise = explode('|', $auditor['hicm_expertise'] ?? '');
        if (in_array($pillarCode, $expertise)) {
            $count++;
        }
    }
    return $count;
}

// Get auditors for a specific pillar
function getAuditorsWithPillar($auditors, $pillarCode) {
    $result = [];
    foreach ($auditors as $auditor) {
        $expertise = explode('|', $auditor['hicm_expertise'] ?? '');
        if (in_array($pillarCode, $expertise)) {
            $result[] = $auditor;
        }
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ความเชี่ยวชาญกรรมการ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #6366F1, #4F46E5);
            color: white;
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::after {
            content: "\f005";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 5%;
            transform: translateY(-50%);
            font-size: 8rem;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .pillar-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border-left: 5px solid;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pillar-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .pillar-card.H1 { border-color: #10B981; }
        .pillar-card.I2 { border-color: #3B82F6; }
        .pillar-card.C3 { border-color: #F59E0B; }
        .pillar-card.M4 { border-color: #8B5CF6; }
        
        .pillar-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .pillar-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .pillar-icon.H1 { background: linear-gradient(135deg, #10B981, #059669); }
        .pillar-icon.I2 { background: linear-gradient(135deg, #3B82F6, #2563EB); }
        .pillar-icon.C3 { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .pillar-icon.M4 { background: linear-gradient(135deg, #8B5CF6, #7C3AED); }
        
        .pillar-card-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .pillar-card-info .code {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .pillar-card-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }
        
        .pillar-count {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .pillar-count.H1 { color: #10B981; }
        .pillar-count.I2 { color: #3B82F6; }
        .pillar-count.C3 { color: #F59E0B; }
        .pillar-count.M4 { color: #8B5CF6; }
        
        .pillar-label {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        /* Auditor Table */
        .section-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary-500);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-600);
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }
        
        .data-table th.center {
            text-align: center;
        }
        
        .data-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: var(--gray-50);
        }
        
        .auditor-name {
            font-weight: 500;
            color: var(--gray-900);
        }
        
        .auditor-email {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .auditor-org {
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        /* Pillar Checkbox */
        .pillar-checkbox {
            text-align: center;
        }
        
        .pillar-check {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .pillar-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.125rem;
        }
        
        .pillar-badge.H1 { background: #D1FAE5; color: #047857; }
        .pillar-badge.I2 { background: #DBEAFE; color: #1D4ED8; }
        .pillar-badge.C3 { background: #FEF3C7; color: #B45309; }
        .pillar-badge.M4 { background: #EDE9FE; color: #6D28D9; }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
        }
        
        .modal-close:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }
        
        .member-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .member-item:last-child {
            border-bottom: none;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-weight: 600;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 500;
            color: var(--gray-900);
        }
        
        .member-org {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Actions */
        .btn-save {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #047857;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #B91C1C;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-star"></i> ความเชี่ยวชาญกรรมการ HICM
                    </h1>
                    <p class="page-subtitle" style="color: rgba(255,255,255,0.9);">กำหนดความเชี่ยวชาญของกรรมการตาม 4 Pillars หลัก</p>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo implode('<br>', $errors); ?>
            </div>
            <?php endif; ?>
            
            <!-- Pillar Stats -->
            <div class="stats-grid">
                <?php foreach ($pillars as $code => $pillar): ?>
                <?php $count = countAuditorsWithPillar($auditors, $code); ?>
                <div class="pillar-card <?php echo $code; ?>" onclick="showPillarMembers('<?php echo $code; ?>')">
                    <div class="pillar-card-header">
                        <div class="pillar-icon <?php echo $code; ?>">
                            <i class="fas fa-<?php echo $pillar['icon']; ?>"></i>
                        </div>
                        <div class="pillar-card-info">
                            <h3><?php echo $pillar['name_th']; ?></h3>
                            <span class="code"><?php echo $code; ?> - <?php echo $pillar['name_en']; ?></span>
                        </div>
                    </div>
                    <div class="pillar-card-stats">
                        <div>
                            <div class="pillar-count <?php echo $code; ?>"><?php echo $count; ?></div>
                            <div class="pillar-label">กรรมการ</div>
                        </div>
                        <i class="fas fa-users" style="font-size: 2rem; opacity: 0.1;"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Auditor Table -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user-check"></i>
                        กำหนดความเชี่ยวชาญกรรมการ
                    </h2>
                    <span style="color: var(--gray-500); font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i>
                        คลิก checkbox เพื่อกำหนด Pillar ที่กรรมการแต่ละท่านเชี่ยวชาญ
                    </span>
                </div>
                
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="bulk_update" value="1">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>ชื่อกรรมการ</th>
                                    <th>หน่วยงาน</th>
                                    <?php foreach ($pillars as $code => $pillar): ?>
                                    <th class="center" style="background: <?php echo $pillar['color']; ?>15;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                                            <span style="color: <?php echo $pillar['color']; ?>; font-weight: 700;"><?php echo $code; ?></span>
                                            <span style="font-size: 0.7rem; color: var(--gray-500);"><?php echo $pillar['name_th']; ?></span>
                                        </div>
                                    </th>
                                    <?php endforeach; ?>
                                    <th>ความเชี่ยวชาญปัจจุบัน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditors as $idx => $auditor): ?>
                                <?php $currentExpertise = explode('|', $auditor['hicm_expertise'] ?? ''); ?>
                                <tr>
                                    <td style="color: var(--gray-400);"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <div class="auditor-name"><?php echo htmlspecialchars($auditor['name']); ?></div>
                                        <div class="auditor-email"><?php echo htmlspecialchars($auditor['email']); ?></div>
                                    </td>
                                    <td>
                                        <div class="auditor-org"><?php echo htmlspecialchars($auditor['organization_name'] ?? '-'); ?></div>
                                    </td>
                                    <?php foreach ($pillars as $code => $pillar): ?>
                                    <td class="pillar-checkbox" style="background: <?php echo $pillar['color']; ?>05;">
                                        <input type="checkbox" 
                                               name="auditor_pillars[<?php echo $auditor['id']; ?>][]" 
                                               value="<?php echo $code; ?>"
                                               class="pillar-check"
                                               <?php echo in_array($code, $currentExpertise) ? 'checked' : ''; ?>
                                               onchange="markChanged()">
                                    </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php if (!empty($auditor['hicm_expertise'])): ?>
                                            <?php foreach ($currentExpertise as $exp): ?>
                                                <?php if (!empty($exp) && isset($pillars[$exp])): ?>
                                                <span class="pillar-badge <?php echo $exp; ?>">
                                                    <?php echo $exp; ?>
                                                </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: var(--gray-400); font-size: 0.85rem;">ยังไม่กำหนด</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="padding: 1.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="fas fa-save"></i>
                            บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Legend - Detailed Pillar Descriptions -->
            <div class="section-card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--gray-700);">
                    <i class="fas fa-info-circle" style="color: var(--primary-500);"></i>
                    คำอธิบาย HICM Pillars และความเชี่ยวชาญเฉพาะด้าน
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                    <!-- H1 - Health Promotion -->
                    <div style="background: linear-gradient(135deg, #10B98110, #05966905); border-radius: var(--radius-xl); padding: 1.25rem; border-left: 4px solid #10B981;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10B981, #059669); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">H1</div>
                            <div>
                                <div style="font-weight: 600; color: #047857; font-size: 1rem;">Health Promotion</div>
                                <div style="font-size: 0.8rem; color: var(--gray-600);">ความเชี่ยวชาญด้านการส่งเสริมสุขภาพ</div>
                            </div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem; padding: 0.5rem; background: white; border-radius: var(--radius-md);">
                            <i class="fas fa-user-tie" style="color: #10B981;"></i>
                            กรรมการกลุ่มนี้มักจะมีพื้นฐานด้านสาธารณสุขหรือทรัพยากรบุคคล
                        </p>
                        <ul style="font-size: 0.8rem; color: var(--gray-700); margin: 0; padding-left: 1.25rem; line-height: 1.8;">
                            <li><strong>Health Policy Design:</strong> การวางนโยบายและยุทธศาสตร์สุขภาพองค์กร</li>
                            <li><strong>Holistic Wellness Program:</strong> การออกแบบโปรแกรมสุขภาพ 4 มิติ (กาย ใจ สังคม ปัญญา)</li>
                            <li><strong>Occupational Nutrition:</strong> โภชนาการสำหรับวัยทำงาน</li>
                            <li><strong>Health Screening & Surveillance:</strong> การคัดกรองและเฝ้าระวังสุขภาพเชิงรุก</li>
                            <li><strong>Physical Ergonomics:</strong> การส่งเสริมสมรรถภาพทางกายและยุทธศาสตร์ลดพฤติกรรมเนือยนิ่ง</li>
                        </ul>
                    </div>
                    
                    <!-- I2 - Industrial Safety -->
                    <div style="background: linear-gradient(135deg, #3B82F610, #2563EB05); border-radius: var(--radius-xl); padding: 1.25rem; border-left: 4px solid #3B82F6;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">I2</div>
                            <div>
                                <div style="font-weight: 600; color: #1D4ED8; font-size: 1rem;">Industrial Safety & Environment</div>
                                <div style="font-size: 0.8rem; color: var(--gray-600);">ความเชี่ยวชาญด้านความปลอดภัยและสิ่งแวดล้อม</div>
                            </div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem; padding: 0.5rem; background: white; border-radius: var(--radius-md);">
                            <i class="fas fa-user-tie" style="color: #3B82F6;"></i>
                            กรรมการกลุ่มนี้มักเป็นผู้เชี่ยวชาญด้านวิศวกรรมความปลอดภัย หรือ จป.วิชาชีพ
                        </p>
                        <ul style="font-size: 0.8rem; color: var(--gray-700); margin: 0; padding-left: 1.25rem; line-height: 1.8;">
                            <li><strong>ISO 45001 / 14001 Auditor:</strong> ผู้ตรวจประเมินมาตรฐานความปลอดภัยและสิ่งแวดล้อม</li>
                            <li><strong>Occupational Health & Hygiene:</strong> การจัดการสุขศาสตร์อุตสาหกรรม (ฝุ่น, เสียง, สารเคมี)</li>
                            <li><strong>Industrial Pollution Control:</strong> การจัดการมลพิษในโรงงานและรอบสถานประกอบการ</li>
                            <li><strong>Proactive Risk Assessment:</strong> การประเมินและบริหารจัดการความเสี่ยงเชิงรุก</li>
                            <li><strong>Environmental Impact Management:</strong> การจัดการผลกระทบด้านสิ่งแวดล้อมและนิเวศอุตสาหกรรม</li>
                        </ul>
                    </div>
                    
                    <!-- C3 - Community Engagement -->
                    <div style="background: linear-gradient(135deg, #F59E0B10, #D9770605); border-radius: var(--radius-xl); padding: 1.25rem; border-left: 4px solid #F59E0B;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">C3</div>
                            <div>
                                <div style="font-weight: 600; color: #B45309; font-size: 1rem;">Community Engagement</div>
                                <div style="font-size: 0.8rem; color: var(--gray-600);">ความเชี่ยวชาญด้านการมีส่วนร่วมชุมชน</div>
                            </div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem; padding: 0.5rem; background: white; border-radius: var(--radius-md);">
                            <i class="fas fa-user-tie" style="color: #F59E0B;"></i>
                            กรรมการกลุ่มนี้มักมีความเชี่ยวชาญด้านสังคมและการสื่อสารองค์กร
                        </p>
                        <ul style="font-size: 0.8rem; color: var(--gray-700); margin: 0; padding-left: 1.25rem; line-height: 1.8;">
                            <li><strong>CSR for Health:</strong> การออกแบบกิจกรรมรับผิดชอบต่อสังคมที่มุ่งเน้นเรื่องสุขภาพ</li>
                            <li><strong>Community Relations Management:</strong> การจัดการความสัมพันธ์และสร้างความร่วมมือกับชุมชน</li>
                            <li><strong>Stakeholder Engagement:</strong> การจัดการผู้มีส่วนได้ส่วนเสียและภาคีเครือข่าย</li>
                            <li><strong>Health Leadership Development:</strong> การพัฒนาผู้นำและแกนนำสุขภาพในชุมชน</li>
                            <li><strong>Public-Private Partnership (PPP):</strong> การประสานความร่วมมือระหว่างรัฐและเอกชน</li>
                        </ul>
                    </div>
                    
                    <!-- M4 - Management & Sustainability -->
                    <div style="background: linear-gradient(135deg, #8B5CF610, #7C3AED05); border-radius: var(--radius-xl); padding: 1.25rem; border-left: 4px solid #8B5CF6;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #8B5CF6, #7C3AED); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">M4</div>
                            <div>
                                <div style="font-weight: 600; color: #6D28D9; font-size: 1rem;">Management & Sustainability</div>
                                <div style="font-size: 0.8rem; color: var(--gray-600);">ความเชี่ยวชาญด้านการจัดการและความยั่งยืน</div>
                            </div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem; padding: 0.5rem; background: white; border-radius: var(--radius-md);">
                            <i class="fas fa-user-tie" style="color: #8B5CF6;"></i>
                            กรรมการกลุ่มนี้มักเป็นผู้บริหารระดับสูงหรือนักยุทธศาสตร์
                        </p>
                        <ul style="font-size: 0.8rem; color: var(--gray-700); margin: 0; padding-left: 1.25rem; line-height: 1.8;">
                            <li><strong>Strategic Planning & Visioning:</strong> การวางแผนยุทธศาสตร์ความยั่งยืนระยะยาว</li>
                            <li><strong>Integrated M&E:</strong> ระบบติดตามและประเมินผลแบบบูรณาการ</li>
                            <li><strong>Learning Organization Specialist:</strong> ผู้เชี่ยวชาญการสร้างองค์กรแห่งการเรียนรู้</li>
                            <li><strong>Data-Driven Management:</strong> การใช้ข้อมูลเชิงประจักษ์เพื่อการบริหารจัดการ</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div><!-- end main-content -->
    </main><!-- end main-wrapper -->
    
    <!-- Modal for Pillar Members -->
    <div class="modal-overlay" id="membersModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">กรรมการที่เชี่ยวชาญ</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    
    <script>
        // Pillar members data
        const pillarMembers = {
            <?php foreach ($pillars as $code => $pillar): ?>
            '<?php echo $code; ?>': {
                name: '<?php echo $pillar['name_th']; ?>',
                color: '<?php echo $pillar['color']; ?>',
                members: [
                    <?php foreach (getAuditorsWithPillar($auditors, $code) as $auditor): ?>
                    {
                        name: '<?php echo addslashes($auditor['name']); ?>',
                        org: '<?php echo addslashes($auditor['organization_name'] ?? '-'); ?>'
                    },
                    <?php endforeach; ?>
                ]
            },
            <?php endforeach; ?>
        };
        
        function showPillarMembers(pillarCode) {
            const data = pillarMembers[pillarCode];
            const modal = document.getElementById('membersModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            
            title.innerHTML = `<span style="color: ${data.color};">${pillarCode}</span> - ${data.name}`;
            
            if (data.members.length === 0) {
                body.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>ยังไม่มีกรรมการที่เชี่ยวชาญด้านนี้</p>
                    </div>
                `;
            } else {
                let html = '<ul class="member-list">';
                data.members.forEach(member => {
                    const initial = member.name.charAt(0);
                    html += `
                        <li class="member-item">
                            <div class="member-avatar" style="background: ${data.color}20; color: ${data.color};">${initial}</div>
                            <div class="member-info">
                                <div class="member-name">${member.name}</div>
                                <div class="member-org">${member.org}</div>
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                body.innerHTML = html;
            }
            
            modal.classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('membersModal').classList.remove('active');
        }
        
        // Close modal on overlay click
        document.getElementById('membersModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Track changes
        let hasChanges = false;
        function markChanged() {
            hasChanges = true;
            document.getElementById('saveBtn').style.animation = 'pulse 0.5s';
        }
        
        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Reset changes flag on form submit
        document.getElementById('bulkForm').addEventListener('submit', function() {
            hasChanges = false;
        });
    </script>
</body>
</html>
