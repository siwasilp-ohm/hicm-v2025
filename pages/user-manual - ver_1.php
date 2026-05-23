<?php
/**
 * HICM V2025 - ศูนย์ช่วยเหลือและคู่มือการใช้งาน (Documentation & Help Center)
 * Professional manual page with project structure, flow diagrams, tables, and print support
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAuth();
$user = getCurrentUser();
$userRole = $user['role'] ?? 'company';
$isAdmin = ($userRole === 'admin');

/** Static built-in manual sections editable by Admin. Overrides are stored in user_manual with category = static:<section-id>. */
function getStaticManualKey($sectionId) {
    return 'static:' . preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$sectionId);
}

function renderStaticManualSection($sectionId, $override, $defaultRole, $defaultCategory, $defaultContentSearch) {
    $title = $override['title'] ?? $sectionId;
    $role = $override['role'] ?? $defaultRole;
    $category = $override['display_category'] ?? $defaultCategory;
    $content = $override['content'] ?? '';
    $badge = $role ?: 'ทั่วไป';
    ob_start();
    ?>
    <section id="<?php echo htmlspecialchars($sectionId); ?>"
             class="manual-section reveal-on-scroll static-manual-section"
             data-section data-static-key="<?php echo htmlspecialchars($sectionId); ?>"
             data-role="<?php echo htmlspecialchars($role); ?>"
             data-category="<?php echo htmlspecialchars($category); ?>"
             data-content="<?php echo htmlspecialchars($defaultContentSearch . ' ' . strip_tags($title . ' ' . $content)); ?>">
        <h2>
            <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <span class="static-title-text"><?php echo htmlspecialchars($title); ?></span>
            <span class="section-badge"><?php echo htmlspecialchars($badge); ?></span>
            <?php if (!empty($GLOBALS['isAdmin'])): ?>
            <button class="btn-edit-section" onclick="openEditModal('static:<?php echo htmlspecialchars($sectionId); ?>')" title="แก้ไขเนื้อหา">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <?php endif; ?>
        </h2>
        <div class="static-editable-content manual-content-rendered"><?php echo $content; ?></div>
    </section>
    <?php
    return ob_get_clean();
}

// Map CEO role
$filterRole = $userRole;
if ($filterRole === 'ceo') $filterRole = 'ceo';

// Preview mode — hide navbar/sidebar when rendered inside preview.php iframe
$isPreview = !empty($_GET['_preview']);

$allManualItems = [];
try {
    $db = getDB()->getConnection();
    if ($isAdmin) {
        // Admin sees all items
        $stmt = $db->prepare("SELECT * FROM user_manual WHERE is_active = 1 AND (category IS NULL OR category NOT LIKE 'static:%') ORDER BY display_order ASC");
        $stmt->execute();
    } else {
        // Other roles see only their role's items + shared items (role='')
        $stmt = $db->prepare("SELECT * FROM user_manual WHERE is_active = 1 AND (category IS NULL OR category NOT LIKE 'static:%') AND (role = :role OR role = '') ORDER BY display_order ASC");
        $stmt->execute([':role' => $filterRole]);
    }
    $allManualItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allManualItems = [];
}

// Handle Admin save for static built-in sections from this page.
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['manual_ajax'] ?? '') === 'static_save') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $sectionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_POST['section_id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $displayCategory = trim($_POST['display_category'] ?? 'overview');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($sectionId === '' || $title === '' || $content === '') {
            throw new Exception('กรุณาระบุข้อมูลให้ครบถ้วน');
        }

        $db = getDB()->getConnection();
        $staticKey = getStaticManualKey($sectionId);
        $meta = json_encode(['display_category' => $displayCategory], JSON_UNESCAPED_UNICODE);
        $storedContent = "<!--MANUAL_META:$meta-->\n" . preg_replace('/^<!--MANUAL_META:.*?-->\s*/s', '', $content);

        $stmt = $db->prepare("SELECT id FROM user_manual WHERE category = :category LIMIT 1");
        $stmt->execute([':category' => $staticKey]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $db->prepare("UPDATE user_manual SET title = :title, content = :content, role = :role, display_order = :display_order, is_active = 1 WHERE id = :id");
            $stmt->execute([
                ':title' => $title,
                ':content' => $storedContent,
                ':role' => $role,
                ':display_order' => $displayOrder,
                ':id' => $existingId
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO user_manual (title, content, role, category, display_order, is_active) VALUES (:title, :content, :role, :category, :display_order, 1)");
            $stmt->execute([
                ':title' => $title,
                ':content' => $storedContent,
                ':role' => $role,
                ':category' => $staticKey,
                ':display_order' => $displayOrder
            ]);
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Load static section overrides.
$staticManualOverrides = [];
try {
    $db = getDB()->getConnection();
    $stmt = $db->prepare("SELECT * FROM user_manual WHERE is_active = 1 AND category LIKE 'static:%'");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = substr($row['category'], 7);
        $content = $row['content'] ?? '';
        $displayCategory = 'overview';
        if (preg_match('/^<!--MANUAL_META:(.*?)-->\s*/s', $content, $m)) {
            $meta = json_decode($m[1], true);
            if (is_array($meta) && !empty($meta['display_category'])) {
                $displayCategory = $meta['display_category'];
            }
            $content = preg_replace('/^<!--MANUAL_META:.*?-->\s*/s', '', $content);
        }
        $row['content'] = $content;
        $row['display_category'] = $displayCategory;
        $staticManualOverrides[$key] = $row;
    }
} catch (Exception $e) {
    $staticManualOverrides = [];
}

$baseUrl = getBaseUrl();
$pageTitle = 'ศูนย์ช่วยเหลือและคู่มือการใช้งาน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/pdf-export.js"></script>
    <style>
        :root {
            --manual-sidebar-width: 280px;
            --manual-primary: var(--primary-600, #2563eb);
            --manual-bg: #f8fafc;
        }
        
        body {
            background-color: var(--manual-bg);
            scroll-behavior: smooth;
        }

        .manual-layout {
            display: flex;
            gap: 2.5rem;
            max-width: 1440px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
        }

        /* Sidebar ToC */
        .manual-toc {
            flex: 0 0 var(--manual-sidebar-width);
            position: sticky;
            top: 6rem;
            height: calc(100vh - 8rem);
            overflow-y: auto;
            padding-right: 1rem;
        }

        .manual-toc::-webkit-scrollbar { width: 4px; }
        .manual-toc::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .manual-main {
            flex: 1;
            min-width: 0;
        }
        
        /* Search & Filters */
        .search-container {
            margin-bottom: 2rem;
            position: relative;
            z-index: 10;
        }
        .search-input {
            width: 100%;
            padding: 0.85rem 1.25rem 0.85rem 3rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Prompt', sans-serif;
        }
        .search-input:focus {
            border-color: var(--manual-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        
        .role-filters {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 0.6rem 1.25rem;
            border-radius: 99px;
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .filter-chip:hover {
            border-color: var(--manual-primary);
            color: var(--manual-primary);
            background: #f0f9ff;
            transform: translateY(-1px);
        }
        .filter-chip.active {
            background: var(--manual-primary);
            color: #fff;
            border-color: var(--manual-primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        /* ToC styling */
        .manual-toc-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-left: 0.75rem;
        }
        
        .toc-category-group { margin-bottom: 2rem; }
        .toc-category-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
            padding-left: 0.75rem;
            border-left: 3px solid var(--manual-primary);
            line-height: 1.2;
        }
        .manual-toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .manual-toc-list a {
            display: block;
            padding: 0.6rem 0.85rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .manual-toc-list a:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .manual-toc-list a.active {
            background: #eff6ff;
            color: var(--manual-primary);
            font-weight: 600;
        }
        .manual-toc-list a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 60%;
            width: 3px;
            background: var(--manual-primary);
            border-radius: 0 4px 4px 0;
        }
        
        /* Hero - Premium */
        .manual-hero {
            background: linear-gradient(120deg, #1e293b 0%, #0f172a 100%);
            color: #fff;
            padding: 4rem 3rem;
            border-radius: 2rem;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.3);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .manual-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 80%;
            height: 200%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 60%);
            transform: rotate(-15deg);
        }
        .manual-hero h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.03em;
            line-height: 1.2;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
        }
        .manual-hero p {
            opacity: 0.9;
            font-size: 1.15rem;
            max-width: 700px;
            line-height: 1.7;
            font-weight: 300;
            position: relative;
        }
        .manual-hero-actions {
            margin-top: 2.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        .btn-print {
            background: rgba(16, 185, 129, 0.25);
            color: #fff;
            border: 1px solid rgba(16, 185, 129, 0.5);
            backdrop-filter: blur(10px);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
        }
        .btn-print:hover {
            background: rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
        }
        .btn-pdf {
            background: rgba(239, 68, 68, 0.25);
            color: #fff;
            border: 1px solid rgba(239, 68, 68, 0.5);
            backdrop-filter: blur(10px);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
        }
        .btn-pdf:hover {
            background: rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
        }
        /* Sections */
        .manual-section {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 2.5rem;
            padding: 3rem;
            border: 1px solid #f1f5f9;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            scroll-margin-top: 6rem;
            position: relative;
            overflow: hidden;
        }
        .manual-section:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            transform: translateY(-2px);
        }
        .manual-section h2 {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f8fafc;
        }
        .section-badge {
            font-size: 0.7rem;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            background: #eff6ff;
            color: var(--manual-primary);
            text-transform: uppercase;
            font-weight: 700;
            margin-left: auto;
            letter-spacing: 0.05em;
        }
        
        /* ===== NEW STYLES ===== */
        /* Tip/Alert Boxes */
        .tip-box { 
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 5px solid #10b981;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 2rem 0;
            display: flex;
            gap: 1rem;
            animation: slideInLeft 0.6s ease-out;
        }
        .tip-box.warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left-color: #f59e0b;
        }
        .tip-box.info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left-color: #3b82f6;
        }
        .tip-box .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.5rem;
            background: #fff;
            color: #10b981;
        }
        .tip-box.warning .icon { color: #f59e0b; }
        .tip-box.info .icon { color: #3b82f6; }
        .tip-box .content { flex: 1; }
        .tip-box .title { font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; font-size: 0.95rem; }
        .tip-box .text { font-size: 0.9rem; color: #475569; line-height: 1.6; }
        
        /* Feature Cards */
        .feature-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .feature-card {
            background: #fff;
            border: 1px solid #f1f5f9;
            border-radius: 1rem;
            padding: 1.75rem;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--manual-primary), transparent);
            transition: left 0.5s ease;
        }
        .feature-card:hover {
            border-color: var(--manual-primary);
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.15);
        }
        .feature-card:hover::before { left: 100%; }
        .feature-card .icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .feature-card .title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; }
        .feature-card .desc { font-size: 0.9rem; color: #64748b; line-height: 1.6; }
        
        /* Guide Section */
        .guide-section { background: linear-gradient(120deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 1rem; padding: 2rem; margin: 2rem 0; border: 1px solid #e2e8f0; }
        
        /* FAQ Section */
        .faq-container { margin: 2rem 0; }
        .faq-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .faq-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .faq-question {
            padding: 1.25rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #1e293b;
            user-select: none;
            transition: all 0.2s;
        }
        .faq-question:hover { color: var(--manual-primary); }
        .faq-question .icon { font-size: 1.2rem; transition: transform 0.3s; }
        .faq-item.active .faq-question .icon { transform: rotate(180deg); }
        .faq-answer {
            padding: 0 1.25rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            line-height: 1.8;
        }
        .faq-item.active .faq-answer { padding: 1.25rem; max-height: 500px; }
        
        /* Timeline */
        .timeline { position: relative; padding: 2rem 0; }
        .timeline::before { content: ''; position: absolute; left: 30px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, var(--manual-primary), transparent); }
        .timeline-item { position: relative; padding-left: 80px; margin-bottom: 2rem; }
        .timeline-item::before { content: ''; position: absolute; left: 10px; top: 0; width: 40px; height: 40px; background: #fff; border: 3px solid var(--manual-primary); border-radius: 50%; }
        .timeline-item .time { font-size: 0.85rem; font-weight: 700; color: var(--manual-primary); text-transform: uppercase; letter-spacing: 0.05em; }
        .timeline-item .title { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0.5rem 0; }
        .timeline-item .desc { color: #64748b; line-height: 1.6; }
        
        /* Keyframe Animations */
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Role Cards */
        .role-map { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .role-card {
            padding: 1.75rem;
            border-radius: 1.25rem;
            border-left: 6px solid;
            background: #fff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .role-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px -5px rgba(0,0,0,0.1); }
        .role-card.admin { border-color: #3b82f6; }
        .role-card.auditor { border-color: #10b981; }
        .role-card.company { border-color: #f59e0b; }
        .role-card.ceo { border-color: #6d28d9; }
        .role-card h4 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
        .role-card ul li { margin-bottom: 0.5rem; display: flex; align-items: start; gap: 0.5rem; color: #64748b; font-size: 0.95rem; }
        .role-card ul li::before { content: '•'; color: currentColor; font-weight: bold; }

        /* Content Typography */
        .manual-content-rendered { font-size: 1.05rem; line-height: 1.8; color: #334155; }
        .manual-content-rendered h3 { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin: 2.5rem 0 1.25rem; }
        .manual-content-rendered p { margin-bottom: 1.5rem; }
        .manual-content-rendered img { max-width: 100%; border-radius: 0.75rem; margin: 1rem 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        /* Admin Edit Button */
        .btn-edit-section {
            margin-left: auto;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border-radius: 0.5rem;
            padding: 0.4rem 0.6rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .btn-edit-section:hover { background: #3b82f6; color: #fff; transform: scale(1.05); }

        /* Edit Modal */
        .edit-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .edit-modal-overlay.active { display: flex; }
        .edit-modal {
            background: #fff;
            border-radius: 1.5rem;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.3s ease-out;
        }
        .edit-modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .edit-modal-header h3 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin:0; }
        .edit-modal-body { padding: 2rem; overflow-y: auto; flex: 1; }
        .edit-modal-footer {
            padding: 1rem 2rem;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .edit-form-group { margin-bottom: 1.25rem; }
        .edit-form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 0.4rem; font-size: 0.9rem; }
        .edit-form-group input,
        .edit-form-group select,
        .edit-form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            color: #1e293b;
            transition: border-color 0.2s;
        }
        .edit-form-group textarea { min-height: 300px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; line-height: 1.6; resize: vertical; }
        .edit-form-group input:focus,
        .edit-form-group select:focus,
        .edit-form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .edit-modal .btn-save {
            background: #3b82f6; color: #fff; border: none; padding: 0.6rem 1.5rem;
            border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .edit-modal .btn-save:hover { background: #2563eb; transform: translateY(-1px); }
        .edit-modal .btn-cancel {
            background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.5rem;
            border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .edit-modal .btn-cancel:hover { background: #e2e8f0; }
        .edit-modal .btn-close-modal {
            background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem;
            border-radius: 0.5rem; transition: all 0.2s;
        }
        .edit-modal .btn-close-modal:hover { color: #1e293b; background: #f1f5f9; }
        .img-upload-zone {
            border: 2px dashed #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            color: #94a3b8;
        }
        .img-upload-zone:hover { border-color: #3b82f6; color: #3b82f6; background: #f8fafc; }
        .img-upload-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
        .img-preview-list { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .img-preview-item {
            position: relative; width: 100px; height: 100px; border-radius: 0.5rem;
            overflow: hidden; border: 1px solid #e2e8f0;
        }
        .img-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .img-preview-item .remove-img {
            position: absolute; top: 4px; right: 4px; background: rgba(239,68,68,0.9);
            color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            font-size: 0.75rem;
        }
        .markdown-hint {
            font-size: 0.8rem; color: #94a3b8; margin-top: 0.5rem;
        }
        .markdown-hint code { background: #f1f5f9; padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-size: 0.75rem; }
        
        /* Project Tree */
        .project-tree {
            background: #1e293b;
            color: #f1f5f9;
            border-radius: 1rem;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            overflow-x: auto;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }
        .project-tree .folder { color: #60a5fa; font-weight: 600; }
        .project-tree .file { color: #a5f3fc; }
        .project-tree .count { color: #fbbf24; font-size: 0.75rem; opacity: 0.8; margin-left: 0.5rem; }

        /* Tables */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 1rem; overflow: hidden; border: 1px solid #e2e8f0; }
        .data-table th { background: #f8fafc; color: #475569; padding: 1rem 1.5rem; font-weight: 700; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .data-table td { padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff; color: #334155; vertical-align: top; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: #f8fafc; }

        /* Flow Diagram */
        .flow-diagram {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 2rem;
            margin: 2rem 0;
            display: flex;
            justify-content: center;
        }

        /* Step List */
        .step-list { counter-reset: step; list-style: none; padding: 0; margin: 2rem 0; }
        .step-list li { position: relative; padding-left: 4rem; padding-bottom: 2.5rem; border-left: 2px solid #e2e8f0; margin-left: 1rem; }
        .step-list li:last-child { padding-bottom: 0; border-left-color: transparent; }
        .step-list li::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: -1.25rem;
            top: 0;
            width: 2.5rem;
            height: 2.5rem;
            background: #fff;
            border: 2px solid var(--manual-primary);
            color: var(--manual-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            z-index: 1;
            transition: all 0.3s;
        }
        .step-list li:hover::before { background: var(--manual-primary); color: #fff; transform: scale(1.1); }
        .step-list .step-title { display: block; font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .step-list .step-desc { color: #64748b; line-height: 1.6; }

        /* Back to Top */
        #backToTop {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--manual-primary);
            color: #fff;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            z-index: 50;
            border: none;
        }
        #backToTop.visible { opacity: 1; visibility: visible; transform: translateY(0); }
        #backToTop:hover { transform: translateY(-5px); background: #1d4ed8; }

        /* Animations */
        .reveal-on-scroll { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
        .reveal-on-scroll.is-visible { opacity: 1; transform: translateY(0); }

        /* Responsive */
        @media (max-width: 1024px) {
            .manual-layout { flex-direction: column; padding: 1rem; }
            .manual-toc { display: none; }
            .manual-hero { padding: 3rem 1.5rem; border-radius: 1.5rem; }
            .manual-hero h1 { font-size: 1.2rem; }
        }

        /* PRINT STYLES - FIXED */
        @media print {
            @page {
                size: A4;
                margin: 1.5cm;
            }
            
            body {
                background: #fff !important;
                color: #000 !important;
                overflow: visible !important;
            }

            /* Hide everything by default */
            body > * { display: none !important; }
            
            /* Show only main wrapper but reset its layout */
            body > .main-wrapper {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .main-wrapper > .main-content {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .manual-layout {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
            }
            
            .manual-main {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Show Content Container */
            #manualContainer {
                display: block !important;
                visibility: visible !important;
            }
            
            /* Ensure sections are visible and formatted for print */
            .manual-section {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin-bottom: 1cm !important;
                padding: 0.5cm !important;
                background: #fff !important;
                opacity: 1 !important;
                transform: none !important;
            }
            
            .manual-section h2 {
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
            }

            /* Hide Specific Elements */
            .manual-toc, 
            .sidebar, 
            .navbar, 
            .manual-hero-actions, 
            .role-filters, 
            .search-container,
            #backToTop,
            .btn,
            .section-badge,
            .sidebar-overlay,
            header, nav, aside {
                display: none !important;
            }

            /* Adjust Hero for Print title */
            .manual-hero {
                background: #fff !important;
                color: #000 !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin-bottom: 1cm !important;
                border-bottom: 3px solid #000 !important;
                border-radius: 0 !important;
                display: block !important;
            }
            .manual-hero h1 {
                background: none !important;
                -webkit-text-fill-color: #000 !important;
                color: #000 !important;
                font-size: 24pt !important;
                margin-bottom: 0.5cm !important;
            }
            .manual-hero p {
                color: #333 !important;
                font-size: 12pt !important;
            }
            .manual-hero::before, .manual-hero::after { display: none !important; }

            /* Improve Table Print */
            .data-table {
                border: 1px solid #000 !important;
                width: 100% !important;
            }
            .data-table th {
                background: #eee !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            .data-table td {
                border: 1px solid #000 !important;
            }

            /* Typography */
            a { text-decoration: none !important; color: #000 !important; }
            .text-primary-500 { color: #000 !important; }
            
            /* Diagrams */
            .mermaid { background: #fff !important; }
        }
    </style>
</head>
<body class="<?php echo $isPreview ? '' : 'has-sidebar'; ?>">
    <?php if (!$isPreview): ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <?php endif; ?>
    
    <main class="main-wrapper">
        <div class="main-content">
            <div class="manual-layout">
                <!-- Sidebar ToC -->
                <aside class="manual-toc animate__animated animate__fadeInLeft">
                    <div class="search-container">
                        <svg width="18" height="18" class="search-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                        </svg>
                        <input type="text" id="manualSearch" class="search-input" placeholder="ค้นหาคู่มือ...">
                    </div>
                    
                    <div class="manual-toc-title">สารบัญ (Table of Contents)</div>
                    <div id="tocContainer">
                        <div class="toc-category-group" data-toc-category="overview">
                            <div class="toc-category-title">ภาพรวมและสถาปัตยกรรม</div>
                            <ul class="manual-toc-list">
                                <li data-role="all" class="toc-item"><a href="#manual-role-map" class="toc-link">แผนที่บทบาทผู้ใช้</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-project-structure" class="toc-link">โครงสร้างโปรเจค</a></li>
                                <li data-role="all" class="toc-item"><a href="#manual-flow" class="toc-link">Flow การใช้งาน</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-database" class="toc-link">โครงสร้างฐานข้อมูล</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-api" class="toc-link">API Endpoints</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-formulas" class="toc-link">สูตรการคำนวณ</a></li>
                                <li data-role="all" class="toc-item"><a href="#manual-pillars" class="toc-link">4 Pillars & ระดับคะแนน</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-tech" class="toc-link">เทคโนโลยีที่ใช้</a></li>
                            </ul>
                        </div>
                        <div class="toc-category-group" data-toc-category="usage">
                            <div class="toc-category-title">วิธีการใช้งานอย่างละเอียด</div>
                            <ul class="manual-toc-list">
                                <li data-role="all" class="toc-item"><a href="#manual-personas" class="toc-link">ตัวละคร (Personas)</a></li>
                                <li data-role="all" class="toc-item"><a href="#manual-status" class="toc-link">สถานะการประเมิน</a></li>
                                <li data-role="company" class="toc-item"><a href="#manual-steps-company" class="toc-link">ขั้นตอน: ผู้ประกอบการ</a></li>
                                <li data-role="auditor" class="toc-item"><a href="#manual-steps-auditor" class="toc-link">ขั้นตอน: กรรมการ</a></li>
                                <li data-role="admin" class="toc-item"><a href="#manual-steps-admin" class="toc-link">ขั้นตอน: Admin</a></li>
                                <li data-role="ceo" class="toc-item"><a href="#manual-steps-ceo" class="toc-link">ขั้นตอน: ผู้บริหาร (CEO)</a></li>
                                <li data-role="all" class="toc-item"><a href="#manual-examples" class="toc-link">ตัวอย่างการใช้งาน</a></li>
                            </ul>
                        </div>
                        <?php 
                        $categories = ['company' => 'ส่วนของผู้ประกอบการ', 'auditor' => 'ส่วนของคณะกรรมการ', 'ceo' => 'ส่วนของผู้บริหาร', 'admin' => 'ส่วนของผู้ดูแลระบบ', 'account' => 'การจัดการบัญชี'];
                        foreach ($categories as $catKey => $catName): 
                            $hasItems = false;
                            foreach ($allManualItems as $item) {
                                $itemCat = $item['category'] ?? $item['role'];
                                if ($itemCat === $catKey) { $hasItems = true; break; }
                            }
                            if ($hasItems):
                        ?>
                        <div class="toc-category-group" data-toc-category="<?php echo $catKey; ?>">
                            <div class="toc-category-title"><?php echo $catName; ?></div>
                            <ul class="manual-toc-list">
                                <?php foreach ($allManualItems as $item): 
                                    $itemCat = $item['category'] ?? $item['role'];
                                    if ($itemCat !== $catKey) continue;
                                ?>
                                    <li data-role="<?php echo $item['role']; ?>" class="toc-item">
                                        <a href="#manual-item-<?php echo $item['id']; ?>" class="toc-link"><?php echo htmlspecialchars($item['title']); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                </aside>

                <div class="manual-main">
                    <!-- Hero -->
                    <section class="manual-hero animate__animated animate__fadeInDown">
                        <h1 class="animate__animated animate__pulse">📚 Complete User Manual</h1>
                        <p>คู่มือการใช้งานระบบ HICM V2025 ฉบับสมบูรณ์</p>
                        <div class="manual-hero-actions">
                            <button id="btnPrintManual" class="btn btn-print rounded-pill px-4 py-2" onclick="HICM_PDF.print()">
                                <svg width="18" height="18" class="me-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                พิมพ์
                            </button>
                            <button id="btnPrint" class="btn btn-pdf rounded-pill px-4 py-2">
                                <svg width="18" height="18" class="me-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/>
                                </svg>
                                ดาวน์โหลด PDF
                            </button>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="manual-edit.php?tab=role_manual" class="btn btn-primary rounded-pill px-4 py-2" style="background: var(--primary-500); border:none;">
                                    <svg width="18" height="18" class="me-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                    </svg>
                                    จัดการเนื้อหา
                                </a>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Role Filters -->
                    <div class="role-filters animate__animated animate__fadeInUp animate__delay-1s">
                        <?php if ($isAdmin): ?>
                        <div class="filter-chip active" data-filter="all">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                            ทั้งหมด
                        </div>
                        <div class="filter-chip" data-filter="company">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l8-4 8 4v14M8 21v-9a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v9"/></svg>
                            ผู้ประกอบการ
                        </div>
                        <div class="filter-chip" data-filter="auditor">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            กรรมการ
                        </div>
                        <div class="filter-chip" data-filter="ceo">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            ผู้บริหาร (CEO)
                        </div>
                        <div class="filter-chip" data-filter="admin">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            ผู้ดูแลระบบ
                        </div>
                        <?php else: ?>
                        <!-- Non-admin: show only their role label -->
                        <div class="filter-chip active" data-filter="<?php echo htmlspecialchars($filterRole); ?>" style="cursor:default;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                            <?php
                            $roleLabels = ['company' => 'ผู้ประกอบการ', 'auditor' => 'กรรมการ', 'ceo' => 'ผู้บริหาร (CEO)', 'admin' => 'ผู้ดูแลระบบ'];
                            echo 'คู่มือสำหรับ: ' . ($roleLabels[$filterRole] ?? $filterRole);
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content Sections -->
                    <div id="manualContainer">
                        <!-- 1. Role Map -->
                        <?php if (isset($staticManualOverrides['manual-role-map'])): ?>
                            <?php echo renderStaticManualSection('manual-role-map', $staticManualOverrides['manual-role-map'], 'all', 'overview', 'Role Map ผู้ประกอบการ กรรมการ ผู้ดูแลระบบ'); ?>
                        <?php else: ?>
                        <section id="manual-role-map" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-role-map" data-role="all" data-category="overview" data-content="Role Map ผู้ประกอบการ กรรมการ ผู้ดูแลระบบ">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                แผนที่บทบาทผู้ใช้ (Role Map)
                                <span class="section-badge">Overview</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-role-map')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="tip-box info">
                                <div class="icon">💡</div>
                                <div class="content">
                                    <div class="title">บทบาท (Role) คืออะไร?</div>
                                    <div class="text">บทบาท หมายถึง ประเภทของผู้ใช้ในระบบ แต่ละบทบาทมีหน้าที่และสิทธิ์การเข้าถึงแตกต่างกัน ซึ่งช่วยให้ระบบปลอดภัยและมีประสิทธิภาพ</div>
                                </div>
                            </div>
                            <div class="role-map">
                                <div class="role-card admin">
                                    <h4>ผู้ดูแลระบบ (Admin)</h4>
                                    <ul>
                                        <li>จัดการผู้ใช้และสิทธิ์การเข้าถึง</li>
                                        <li>ตั้งค่าตัวชี้วัดและเกณฑ์คะแนน</li>
                                        <li>จัดการรอบการประเมิน (Periods)</li>
                                        <li>แก้ไขคู่มือและเอกสารดาวน์โหลด</li>
                                        <li>ดูรายงานภาพรวมและส่งออกข้อมูล</li>
                                    </ul>
                                </div>
                                <div class="role-card auditor">
                                    <h4>ผู้ประเมิน (Auditor)</h4>
                                    <ul>
                                        <li>ตรวจสอบหลักฐานและให้คะแนน</li>
                                        <li>เขียนความเห็นและข้อเสนอแนะ</li>
                                        <li>ใช้ระบบบันทึกอัตโนมัติ (Auto-save)</li>
                                        <li>ดูผลการประเมินวิเคราะห์ Pillar</li>
                                    </ul>
                                </div>
                                <div class="role-card company">
                                    <h4>สถานประกอบการ (Company)</h4>
                                    <ul>
                                        <li>กรอกแบบประเมินตนเอง (Self-Assessment)</li>
                                        <li>แนบหลักฐานประกอบการประเมิน</li>
                                        <li>ติดตามพัฒนาการผ่าน Milestones</li>
                                        <li>วิเคราะห์ผลผ่าน Radar Chart</li>
                                    </ul>
                                </div>
                                <div class="role-card company" style="border-color: #6d28d9;">
                                    <h4>ผู้บริหาร (CEO)</h4>
                                    <ul>
                                        <li>ดู Dashboard สรุปภาพรวม</li>
                                        <li>ดูรายงานสรุปและ Leaderboard</li>
                                        <li>วิเคราะห์คะแนนรวมแต่ละ Pillar</li>
                                        <li>ดูข้อมูลสถานประกอบการ</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="tip-box warning" style="margin-top: 2rem;">
                                <div class="icon">⚠️</div>
                                <div class="content">
                                    <div class="title">ข้อสำคัญเกี่ยวกับสิทธิ์</div>
                                    <div class="text">ผู้ใช้แต่ละคนจะเห็นเฉพาะข้อมูลที่เกี่ยวข้องกับบทบาทของตนเท่านั้น ดังนั้น หากไม่เห็นข้อมูลบางส่วน อาจเป็นเพราะสิทธิ์ของคุณ ติดต่อผู้ดูแลระบบได้</div>
                                </div>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- Quick Start Guide -->
                        <section id="manual-quick-start" class="manual-section reveal-on-scroll" data-section data-role="all" data-category="overview" data-content="Quick Start Guide ศูนย์การใช้งาน">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                เริ่มต้นใช้งานอย่างรวดเร็ว (Quick Start)
                                <span class="section-badge">Guide</span>
                            </h2>
                            <div class="guide-section">
                                <h3 style="margin-top: 0; color: #1e293b; font-size: 1.25rem;">3 ขั้นตอนสำคัญ:</h3>
                                <div class="step-list">
                                    <li>
                                        <span class="step-title">1. ล็อกอินเข้าสู่ระบบ</span>
                                        <span class="step-desc">ใช้ชื่อผู้ใช้และรหัสผ่านของคุณ หากยังไม่มีบัญชี ติดต่อผู้ดูแลระบบขอสร้างบัญชี</span>
                                    </li>
                                    <li>
                                        <span class="step-title">2. เลือกหน้าข้อมูลตามบทบาท</span>
                                        <span class="step-desc">หลังล็อกอินแล้ว ระบบจะแสดงเมนูตามบทบาทของคุณ เช่น "แบบประเมิน" "ประเมินให้คะแนน" หรือ "รายงาน"</span>
                                    </li>
                                    <li>
                                        <span class="step-title">3. ทำตามขั้นตอนในคู่มือ</span>
                                        <span class="step-desc">อ่านคำแนะนำในหน้าจอ ถ้ามีปัญหาให้ดูคำถามที่พบบ่อย (FAQ) ในหน้านี้</span>
                                    </li>
                                </div>
                            </div>
                        </section>

                        <!-- Feature Highlights -->
                        <section id="manual-features" class="manual-section reveal-on-scroll" data-section data-role="all" data-category="overview" data-content="คุณสมบัติหลักของระบบ">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                คุณสมบัติหลักของระบบ
                                <span class="section-badge">Features</span>
                            </h2>
                            <div class="feature-cards">
                                <div class="feature-card">
                                    <div class="icon">📊</div>
                                    <div class="title">แบบประเมินอัจฉริยะ</div>
                                    <div class="desc">กรอกแบบประเมินตนเองด้วยเกณฑ์ 5 ระดับ และแนบหลักฐานประกอบอย่างง่าย</div>
                                </div>
                                <div class="feature-card">
                                    <div class="icon">📁</div>
                                    <div class="title">จัดเก็บไฟล์ปลอดภัย</div>
                                    <div class="desc">อัปโหลดรูปภาพ PDF เอกสาร ได้ถึง 10MB โดยปลอดภัยและจัดระเบียบ</div>
                                </div>
                                <div class="feature-card">
                                    <div class="icon">📈</div>
                                    <div class="title">วิเคราะห์ผลแบบภาพ</div>
                                    <div class="desc">ดูผลการประเมินผ่าน Radar Chart, Bar Chart, Leaderboard ฯลฯ</div>
                                </div>
                                <div class="feature-card">
                                    <div class="icon">🎯</div>
                                    <div class="title">ติดตามพัฒนาการ</div>
                                    <div class="desc">บันทึก Milestone ให้เหตุผล หรือข้อคิดเห็นในการพัฒนา</div>
                                </div>
                                <div class="feature-card">
                                    <div class="icon">💬</div>
                                    <div class="title">รับข้อเสนอแนะ</div>
                                    <div class="desc">ผู้ประเมินสามารถเขียนความเห็นและข้อแนะนำให้แต่ละตัวชี้วัด</div>
                                </div>
                                <div class="feature-card">
                                    <div class="icon">📥</div>
                                    <div class="title">ส่งออกข้อมูล</div>
                                    <div class="desc">ส่งออกรายงานเป็น Excel, PDF เพื่อการวิเคราะห์เพิ่มเติม</div>
                                </div>
                            </div>
                        </section>

                        <!-- 2. Project Structure -->
                        <?php if (isset($staticManualOverrides['manual-project-structure'])): ?>
                            <?php echo renderStaticManualSection('manual-project-structure', $staticManualOverrides['manual-project-structure'], 'admin', 'overview', 'โครงสร้างโปรเจค โฟลเดอร์ api config database includes pages scripts'); ?>
                        <?php else: ?>
                        <section id="manual-project-structure" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-project-structure" data-role="admin" data-category="overview" data-content="โครงสร้างโปรเจค โฟลเดอร์ api config database includes pages scripts">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                โครงสร้างโปรเจค (Project Structure)
                                <span class="section-badge">Architecture</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-project-structure')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="project-tree">
<pre>hicm-v2025/
├── <span class="folder">api/</span>              <span class="count">← 16 endpoints (AJAX Requests)</span>
│   ├── upload.php, download.php, get_attachments.php, get_company.php
│   ├── save-assessment.php, save-auditor-score.php, submit-auditor-evaluation.php
│   ├── save-news.php, delete-news.php, notifications.php
│   ├── export-excel.php, fetch_export_data.php
│   ├── download-manual-ref.php, manual-ref-files.php, delete-file.php
├── <span class="folder">config/</span>           <span class="count">← Configuration</span>
│   ├── config.php (Constants), database.php (PDO Connection)
├── <span class="folder">database/</span>        <span class="count">← SQL Scripts</span>
│   ├── schema.sql, migrations/, insert_indicators.sql
├── <span class="folder">includes/</span>        <span class="count">← Shared Logic & UI Components</span>
│   ├── navbar.php, sidebar.php, email.php, notification.php, news.php
│   ├── export.php, export_helpers.php
├── <span class="folder">pages/</span>           <span class="count">← 36+ Views (Main Logic)</span>
│   ├── Auth: login, logout, register, change-password
│   ├── Assessment: assessment-form, assessment-view, assessment-result, assessments, my-assessments
│   ├── Auditor: auditor-assignments, auditor-evaluate, auditor-expertise
│   ├── Admin: users, indicators, periods, settings, reports, export
│   ├── Company: company-profile, company-locations, milestones
│   ├── Manual: manual.php, manual-edit.php, user-manual.php
├── <span class="folder">assets/</span>          <span class="count">← Static Resources</span>
│   ├── css/style.css, js/main.js, js/chart.js
│   └── uploads/ (Stored Files)
└── <span class="file">index.php</span>          <span class="count">← Entry Point</span></pre>
                            </div>
                            <p class="manual-content-rendered" style="margin-top:1.5rem;">โครงสร้างแยกตามหน้าที่ชัดเจน: <strong>API</strong> สำหรับการประมวลผลเบื้องหลัง, <strong>Pages</strong> สำหรับหน้าจอแสดงผล, และ <strong>Includes</strong> สำหรับส่วนประกอบที่ใช้ซ้ำ</p>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 3. User Flow Diagram -->
                        <?php if (isset($staticManualOverrides['manual-flow'])): ?>
                            <?php echo renderStaticManualSection('manual-flow', $staticManualOverrides['manual-flow'], 'all', 'overview', 'Flow การใช้งาน User Flow diagram'); ?>
                        <?php else: ?>
                        <section id="manual-flow" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-flow" data-role="all" data-category="overview" data-content="Flow การใช้งาน User Flow diagram">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                                Flow การใช้งาน (User Flow)
                                <span class="section-badge">Diagram</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-flow')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="flow-diagram">
                                <pre class="mermaid" style="background:transparent;">
flowchart TB
    subgraph Auth[การยืนยันตัวตน]
        Login[Login] --> Role{Role?}
    end
    Role -->|Admin| A1[จัดการผู้ใช้]
    Role -->|Auditor| B1[ดูรายการมอบหมาย]
    Role -->|Company| C1[กรอกแบบประเมิน]
    Role -->|CEO| D1[ดูรายงานสรุป]
    A1 --> A2[ตั้งค่าตัวชี้วัด] --> A3[จัดการรอบประเมิน] --> A4[รายงาน/Export]
    B1 --> B2[ประเมิน/ให้คะแนน] --> B3[ตรวจหลักฐาน] --> B4[ส่งผลประเมิน]
    C1 --> C2[แนบหลักฐาน] --> C3[ส่งประเมิน] --> C4[ดูผล/Milestone]
    D1 --> D2[Leaderboard]
                                </pre>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 4. Database Schema -->
                        <?php if (isset($staticManualOverrides['manual-database'])): ?>
                            <?php echo renderStaticManualSection('manual-database', $staticManualOverrides['manual-database'], 'admin', 'overview', 'โครงสร้างฐานข้อมูล Database Schema tables'); ?>
                        <?php else: ?>
                        <section id="manual-database" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-database" data-role="admin" data-category="overview" data-content="โครงสร้างฐานข้อมูล Database Schema tables">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                โครงสร้างฐานข้อมูล (Database Schema)
                                <span class="section-badge">MySQL</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-database')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <table class="data-table">
                                <thead><tr><th>ตาราง</th><th>หน้าที่</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>users</strong></td><td>ผู้ใช้งาน (admin, auditor, company, ceo)</td></tr>
                                    <tr><td><strong>companies</strong></td><td>ข้อมูลสถานประกอบการ</td></tr>
                                    <tr><td><strong>assessment_periods</strong></td><td>รอบการประเมิน</td></tr>
                                    <tr><td><strong>pillars</strong></td><td>4 Pillars HICM</td></tr>
                                    <tr><td><strong>indicators</strong></td><td>ตัวชี้วัด 60 ข้อ</td></tr>
                                    <tr><td><strong>assessments</strong></td><td>การประเมินแต่ละบริษัท/รอบ</td></tr>
                                    <tr><td><strong>assessment_scores</strong></td><td>คะแนนรายตัวชี้วัด (self + auditor)</td></tr>
                                    <tr><td><strong>attachments</strong></td><td>ไฟล์แนบหลักฐาน</td></tr>
                                    <tr><td><strong>assessment_logs</strong></td><td>ประวัติการทำงาน</td></tr>
                                    <tr><td><strong>notifications</strong></td><td>การแจ้งเตือน</td></tr>
                                </tbody>
                            </table>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 5. API Endpoints -->
                        <?php if (isset($staticManualOverrides['manual-api'])): ?>
                            <?php echo renderStaticManualSection('manual-api', $staticManualOverrides['manual-api'], 'admin', 'overview', 'API Endpoints upload download save assessment'); ?>
                        <?php else: ?>
                        <section id="manual-api" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-api" data-role="admin" data-category="overview" data-content="API Endpoints upload download save assessment">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                                API Endpoints
                                <span class="section-badge">REST</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-api')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <table class="data-table">
                                <thead><tr><th>Endpoint</th><th>หน้าที่</th></tr></thead>
                                <tbody>
                                    <tr><td>upload.php</td><td>อัปโหลดไฟล์หลักฐาน</td></tr>
                                    <tr><td>download.php</td><td>ดาวน์โหลดไฟล์แนบ</td></tr>
                                    <tr><td>get_attachments.php</td><td>ดึงรายการไฟล์แนบ</td></tr>
                                    <tr><td>save-assessment.php</td><td>บันทึกการประเมินตนเอง</td></tr>
                                    <tr><td>save-auditor-score.php</td><td>บันทึกคะแนนกรรมการ</td></tr>
                                    <tr><td>submit-auditor-evaluation.php</td><td>ส่งผลการประเมินกรรมการ</td></tr>
                                    <tr><td>export-excel.php</td><td>ส่งออก Excel</td></tr>
                                </tbody>
                            </table>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 6. Scoring Formula -->
                        <?php if (isset($staticManualOverrides['manual-formulas'])): ?>
                            <?php echo renderStaticManualSection('manual-formulas', $staticManualOverrides['manual-formulas'], 'admin', 'overview', 'formulas สูตรการคำนวณ คะแนน Pillar H1 I2 C3 M4'); ?>
                        <?php else: ?>
                        <section id="manual-formulas" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-formulas" data-role="admin" data-category="overview" data-content="formulas สูตรการคำนวณ คะแนน Pillar H1 I2 C3 M4">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                                สูตรการคำนวณคะแนน (Scoring Mechanics)
                                <span class="section-badge">Logic</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-formulas')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="manual-content-rendered">
                                <h3>1. คะแนนรายเสาหลัก (Pillar Score)</h3>
                                <p>คำนวณจากค่าเฉลี่ยของตัวชี้วัดที่ประเมิน (ไม่รวม N/A) คูณด้วยน้ำหนักของเสาหลักนั้นๆ</p>
                                <div style="background:#f1f5f9; padding:1.5rem; border-radius:0.75rem; border-left:4px solid var(--manual-primary); font-family:monospace; margin:1rem 0;">
                                    Pillar Score = (Σ Indicator Scores / Count of Evaluated) × Pillar Weight
                                </div>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 7. 4 Pillars & Score Levels -->
                        <?php if (isset($staticManualOverrides['manual-pillars'])): ?>
                            <?php echo renderStaticManualSection('manual-pillars', $staticManualOverrides['manual-pillars'], 'all', 'overview', '4 Pillars H1 I2 C3 M4 โครงสร้างน้ำหนัก ระดับคะแนน HICM'); ?>
                        <?php else: ?>
                        <section id="manual-pillars" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-pillars" data-role="all" data-category="overview" data-content="4 Pillars H1 I2 C3 M4 โครงสร้างน้ำหนัก ระดับคะแนน HICM">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                4 Pillars & ระดับคะแนน
                                <span class="section-badge">Scoring</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-pillars')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <h3 class="text-lg font-bold mb-3">โครงสร้าง 4 Pillars</h3>
                            <table class="data-table mb-4">
                                <thead><tr><th>Pillar</th><th>ชื่อ</th><th>จำนวนตัวชี้วัด</th><th>น้ำหนัก (คะแนน)</th></tr></thead>
                                <tbody>
                                    <?php foreach (PILLARS as $code => $p): ?>
                                    <tr><td><strong><?php echo $code; ?></strong></td><td><?php echo htmlspecialchars($p['name_th']); ?></td><td><?php echo $p['indicators_count']; ?></td><td><?php echo $p['weight']; ?></td></tr>
                                    <?php endforeach; ?>
                                    <tr style="font-weight:700; background:#f8fafc;"><td colspan="2">รวม</td><td>60</td><td>1,000</td></tr>
                                </tbody>
                            </table>
                            <h3 class="text-lg font-bold mb-3 mt-4">ระดับการรับรอง HICM</h3>
                            <table class="data-table">
                                <thead><tr><th>ระดับ</th><th>คะแนน</th><th>ชื่อ</th></tr></thead>
                                <tbody>
                                    <?php foreach (HICM_LEVELS as $hl): ?>
                                    <tr>
                                        <td><strong>Level <?php echo $hl['level']; ?></strong></td>
                                        <td><?php echo $hl['min']; ?>–<?php echo $hl['max']; ?></td>
                                        <td><?php echo htmlspecialchars($hl['name']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 8. Personas -->
                        <?php if (isset($staticManualOverrides['manual-personas'])): ?>
                            <?php echo renderStaticManualSection('manual-personas', $staticManualOverrides['manual-personas'], 'all', 'usage', 'ตัวละคร Persona Admin Auditor Company CEO โปรไฟล์'); ?>
                        <?php else: ?>
                        <section id="manual-personas" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-personas" data-role="all" data-category="usage" data-content="ตัวละคร Persona Admin Auditor Company CEO โปรไฟล์">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                                ตัวละคร (Personas)
                                <span class="section-badge">User Profiles</span>
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-personas')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="persona-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem;">
                                <div class="persona-card" style="padding:1.5rem; border:1px solid #e2e8f0; border-radius:1rem; background:#fff;">
                                    <div class="persona-header" style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
                                        <div style="width:48px; height:48px; background:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold;">A</div>
                                        <div><div style="font-weight:bold;">Admin</div><div style="font-size:0.85rem; color:#64748b;">ผู้ดูแลระบบ</div></div>
                                    </div>
                                    <p style="font-size:0.9rem; color:#475569;">จัดการระบบ ตั้งค่าตัวชี้วัด และดูแลผู้ใช้</p>
                                </div>
                                <div class="persona-card" style="padding:1.5rem; border:1px solid #e2e8f0; border-radius:1rem; background:#fff;">
                                    <div class="persona-header" style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
                                        <div style="width:48px; height:48px; background:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold;">B</div>
                                        <div><div style="font-weight:bold;">Auditor</div><div style="font-size:0.85rem; color:#64748b;">กรรมการ</div></div>
                                    </div>
                                    <p style="font-size:0.9rem; color:#475569;">ตรวจประเมิน ให้คะแนน และให้ข้อเสนอแนะ</p>
                                </div>
                                <div class="persona-card" style="padding:1.5rem; border:1px solid #e2e8f0; border-radius:1rem; background:#fff;">
                                    <div class="persona-header" style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
                                        <div style="width:48px; height:48px; background:#f59e0b; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold;">C</div>
                                        <div><div style="font-weight:bold;">Company</div><div style="font-size:0.85rem; color:#64748b;">สถานประกอบการ</div></div>
                                    </div>
                                    <p style="font-size:0.9rem; color:#475569;">ประเมินตนเอง แนบหลักฐาน และติดตามผล</p>
                                </div>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 9. Status Flow -->
                        <?php if (isset($staticManualOverrides['manual-status'])): ?>
                            <?php echo renderStaticManualSection('manual-status', $staticManualOverrides['manual-status'], 'all', 'usage', 'สถานะการประเมิน draft submitted under_review evaluated completed'); ?>
                        <?php else: ?>
                        <section id="manual-status" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-status" data-role="all" data-category="usage" data-content="สถานะการประเมิน draft submitted under_review evaluated completed">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                สถานะการประเมิน (Status Flow)
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-status')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <div class="flow-diagram">
                                <pre class="mermaid" style="background:transparent;">
stateDiagram-v2
    [*] --> draft: สร้างประเมิน
    draft --> submitted: Company ส่ง
    submitted --> under_review: Admin เปิด
    under_review --> evaluated: Auditor ให้คะแนน
    evaluated --> completed: Admin อนุมัติ
                                </pre>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 10. Steps: Company -->
                        <?php if (isset($staticManualOverrides['manual-steps-company'])): ?>
                            <?php echo renderStaticManualSection('manual-steps-company', $staticManualOverrides['manual-steps-company'], 'company', 'usage', 'ขั้นตอนผู้ประกอบการ Company กรอกแบบประเมิน แนบหลักฐาน'); ?>
                        <?php else: ?>
                        <section id="manual-steps-company" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-steps-company" data-role="company" data-category="usage" data-content="ขั้นตอนผู้ประกอบการ Company กรอกแบบประเมิน แนบหลักฐาน">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
                                ขั้นตอนการใช้งาน: ผู้ประกอบการ
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-steps-company')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <ol class="step-list">
                                <li>
                                    <span class="step-title">ลงทะเบียน/เข้าสู่ระบบ</span>
                                    <span class="step-desc">เข้าสู่ระบบด้วยบัญชีที่ลงทะเบียนไว้</span>
                                </li>
                                <li>
                                    <span class="step-title">กรอกข้อมูลบริษัท</span>
                                    <span class="step-desc">อัปเดตข้อมูลใน Company Profile ให้เป็นปัจจุบัน</span>
                                </li>
                                <li>
                                    <span class="step-title">ทำแบบประเมิน</span>
                                    <span class="step-desc">เลือกรอบการประเมิน และกรอกคะแนนทั้ง 60 ตัวชี้วัด</span>
                                </li>
                                <li>
                                    <span class="step-title">แนบหลักฐาน</span>
                                    <span class="step-desc">อัปโหลดไฟล์ (PDF/JPG/XLSX) เพื่อยืนยันการดำเนินงาน</span>
                                </li>
                                <li>
                                    <span class="step-title">ส่งประเมิน</span>
                                    <span class="step-desc">กดปุ่ม "ส่งประเมิน" เพื่อส่งให้กรรมการตรวจ (ไม่สามารถแก้ไขได้หลังจากส่ง)</span>
                                </li>
                            </ol>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 11. Steps: Auditor -->
                        <?php if (isset($staticManualOverrides['manual-steps-auditor'])): ?>
                            <?php echo renderStaticManualSection('manual-steps-auditor', $staticManualOverrides['manual-steps-auditor'], 'auditor', 'usage', 'ขั้นตอนกรรมการ Auditor ประเมิน ให้คะแนน ตรวจหลักฐาน'); ?>
                        <?php else: ?>
                        <section id="manual-steps-auditor" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-steps-auditor" data-role="auditor" data-category="usage" data-content="ขั้นตอนกรรมการ Auditor ประเมิน ให้คะแนน ตรวจหลักฐาน">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                ขั้นตอนการใช้งาน: กรรมการ
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-steps-auditor')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <ol class="step-list">
                                <li>
                                    <span class="step-title">เลือกรายการประเมิน</span>
                                    <span class="step-desc">เลือกบริษัทที่ได้รับมอบหมายจากหน้ารายการ</span>
                                </li>
                                <li>
                                    <span class="step-title">ตรวจสอบหลักฐาน</span>
                                    <span class="step-desc">ดูคะแนน Self-Assessment และไฟล์แนบของบริษัท</span>
                                </li>
                                <li>
                                    <span class="step-title">ให้คะแนนและข้อเสนอแนะ</span>
                                    <span class="step-desc">กรอกคะแนน Auditor และระบุข้อเสนอแนะเพื่อการพัฒนา</span>
                                </li>
                                <li>
                                    <span class="step-title">ยืนยันผลการประเมิน</span>
                                    <span class="step-desc">เมื่อครบถ้วน กดส่งผลการประเมินเพื่อแจ้ง Admin</span>
                                </li>
                            </ol>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 12. Steps: Admin -->
                        <?php if (isset($staticManualOverrides['manual-steps-admin'])): ?>
                            <?php echo renderStaticManualSection('manual-steps-admin', $staticManualOverrides['manual-steps-admin'], 'admin', 'usage', 'ขั้นตอน Admin ผู้ดูแลระบบ จัดการผู้ใช้ ตั้งค่า'); ?>
                        <?php else: ?>
                        <section id="manual-steps-admin" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-steps-admin" data-role="admin" data-category="usage" data-content="ขั้นตอน Admin ผู้ดูแลระบบ จัดการผู้ใช้ ตั้งค่า">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                ขั้นตอนการใช้งาน: ผู้ดูแลระบบ
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-steps-admin')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <ol class="step-list">
                                <li>
                                    <span class="step-title">จัดการ Master Data</span>
                                    <span class="step-desc">ตั้งค่า Users, Indicators และ Periods</span>
                                </li>
                                <li>
                                    <span class="step-title">Monitor ภาพรวม</span>
                                    <span class="step-desc">ติดตามสถานะการส่งงานของ Company และ Auditor</span>
                                </li>
                                <li>
                                    <span class="step-title">อนุมัติและออกรายงาน</span>
                                    <span class="step-desc">ตรวจสอบความถูกต้องขั้นสุดท้าย และ Export รายงานสรุป</span>
                                </li>
                            </ol>
                        
                            </div></section>
                        <?php endif; ?>

                        <!-- 13. Steps: CEO -->
                        <?php if (isset($staticManualOverrides['manual-steps-ceo'])): ?>
                            <?php echo renderStaticManualSection('manual-steps-ceo', $staticManualOverrides['manual-steps-ceo'], 'ceo', 'usage', 'ขั้นตอน CEO ผู้บริหาร ดูรายงาน Leaderboard สรุปผล'); ?>
                        <?php else: ?>
                        <section id="manual-steps-ceo" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-steps-ceo" data-role="ceo" data-category="usage" data-content="ขั้นตอน CEO ผู้บริหาร ดูรายงาน Leaderboard สรุปผล">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                ขั้นตอนการใช้งาน: ผู้บริหาร (CEO)
                            
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-steps-ceo')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <?php endif; ?></h2>
                            <div class="static-editable-content">
                            <ol class="step-list">
                                <li>
                                    <span class="step-title">เข้าสู่ระบบ</span>
                                    <span class="step-desc">ล็อกอินด้วยบัญชีผู้บริหาร ระบบจะแสดง Dashboard สรุปภาพรวมโดยอัตโนมัติ</span>
                                </li>
                                <li>
                                    <span class="step-title">ดู Dashboard ภาพรวม</span>
                                    <span class="step-desc">ดูสรุปจำนวนสถานประกอบการ คะแนนเฉลี่ย และสถานะการประเมินรอบปัจจุบัน</span>
                                </li>
                                <li>
                                    <span class="step-title">ตรวจสอบ Leaderboard</span>
                                    <span class="step-desc">ดูอันดับสถานประกอบการเรียงตามคะแนน พร้อมระดับ HICM Level</span>
                                </li>
                                <li>
                                    <span class="step-title">ดูรายงานสรุปผล</span>
                                    <span class="step-desc">วิเคราะห์ข้อมูลคะแนนรวมแต่ละ Pillar ผ่านกราฟและตาราง</span>
                                </li>
                            </ol>
                            <div class="tip-box info" style="margin-top: 2rem;">
                                <div class="icon">💡</div>
                                <div class="content">
                                    <div class="title">สำหรับผู้บริหาร</div>
                                    <div class="text">ผู้บริหาร (CEO) สามารถเข้าถึงข้อมูลรายงานสรุปได้ทั้งหมด แต่ไม่สามารถแก้ไขข้อมูลการประเมิน หากต้องการปรับเปลี่ยนใดๆ กรุณาติดต่อผู้ดูแลระบบ</div>
                                </div>
                            </div>
                        
                            </div></section>
                        <?php endif; ?>


                        <?php if (isset($staticManualOverrides['manual-tech'])): ?>
                            <?php echo renderStaticManualSection('manual-tech', $staticManualOverrides['manual-tech'], 'admin', 'overview', 'เทคโนโลยีที่ใช้ Technology Stack PHP SQL JavaScript Mermaid PDF Export'); ?>
                        <?php else: ?>
                        <section id="manual-tech" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-tech" data-role="admin" data-category="overview" data-content="เทคโนโลยีที่ใช้ Technology Stack PHP SQL JavaScript Mermaid PDF Export">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>
                                เทคโนโลยีที่ใช้
                                <span class="section-badge">Tech</span>
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-tech')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php endif; ?>
                            </h2>
                            <div class="static-editable-content">
                                <div class="tip-box info">
                                    <div class="icon">🧩</div>
                                    <div class="content">
                                        <div class="title">Technology Stack</div>
                                        <div class="text">หัวข้อนี้สามารถแก้ไขรายละเอียดเทคโนโลยีที่ใช้ในระบบได้จากปุ่มแก้ไข เช่น PHP, SQL Database, JavaScript, Mermaid Diagram และ PDF Export</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if (isset($staticManualOverrides['manual-examples'])): ?>
                            <?php echo renderStaticManualSection('manual-examples', $staticManualOverrides['manual-examples'], 'all', 'usage', 'ตัวอย่างการใช้งาน Use Cases Examples Scenario'); ?>
                        <?php else: ?>
                        <section id="manual-examples" class="manual-section reveal-on-scroll static-manual-section" data-section data-static-key="manual-examples" data-role="all" data-category="usage" data-content="ตัวอย่างการใช้งาน Use Cases Examples Scenario">
                            <h2>
                                <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.67 0 3.24.46 4.58 1.25"/></svg>
                                ตัวอย่างการใช้งาน
                                <span class="section-badge">Examples</span>
                                <?php if ($isAdmin): ?>
                                <button class="btn-edit-section" onclick="openEditModal('static:manual-examples')" title="แก้ไขเนื้อหา">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php endif; ?>
                            </h2>
                            <div class="static-editable-content">
                                <ol class="step-list">
                                    <li>
                                        <span class="step-title">ตัวอย่าง Workflow</span>
                                        <span class="step-desc">Admin สามารถเพิ่มหรือปรับตัวอย่างการใช้งานตามกระบวนการจริงของระบบได้</span>
                                    </li>
                                    <li>
                                        <span class="step-title">ตัวอย่างการประเมิน</span>
                                        <span class="step-desc">เพิ่มตัวอย่างสถานการณ์ของ Company, Auditor และ CEO เพื่อให้ผู้ใช้เข้าใจขั้นตอนมากขึ้น</span>
                                    </li>
                                </ol>
                            </div>
                        </section>
                        <?php endif; ?>


                        <!-- Dynamic Items -->
                        <?php foreach ($allManualItems as $item): ?>
                            <section id="manual-item-<?php echo $item['id']; ?>" 
                                     class="manual-section reveal-on-scroll" 
                                     data-section 
                                     data-role="<?php echo $item['role']; ?>"
                                     data-category="<?php echo $item['category'] ?? ''; ?>"
                                     data-content="<?php echo htmlspecialchars($item['title'] . ' ' . $item['content']); ?>">
                                <h2>
                                    <svg width="32" height="32" class="text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                    <span class="section-badge"><?php echo $item['role'] ?: 'ทั่วไป'; ?></span>
                                    <?php if ($isAdmin): ?>
                                    <button class="btn-edit-section" onclick="openEditModal(<?php echo $item['id']; ?>)" title="แก้ไขเนื้อหา">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </h2>
                                <div class="manual-content-rendered" data-markdown id="content-<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['content']); ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <!-- No Results -->
                        <div id="noResults" class="no-results" style="display:none; text-align:center; padding:4rem;">
                            <svg width="64" height="64" style="margin:0 auto; color:#cbd5e1;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path d="M21 21l-4.35-4.35M19 11a8 8 0 11-16 0 8 8 0 0116 0zM10 11l2 2m0-2l-2 2"/>
                            </svg>
                            <h3 style="margin-top:1rem; font-size:1.25rem; font-weight:700; color:#475569;">ไม่พบหัวข้อที่ค้นหา</h3>
                            <p style="color:#94a3b8;">ลองใช้คำค้นหาอื่น หรือเปลี่ยนตัวกรอง</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($isAdmin): ?>
    <!-- Admin Edit Modal -->
    <div id="editModalOverlay" class="edit-modal-overlay">
        <div class="edit-modal">
            <div class="edit-modal-header">
                <h3 id="editModalTitle">แก้ไขเนื้อหา</h3>
                <button class="btn-close-modal" onclick="closeEditModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="edit-modal-body">
                <input type="hidden" id="editItemId" value="">
                <div class="edit-form-group">
                    <label>ชื่อหัวข้อ</label>
                    <input type="text" id="editTitle" placeholder="ชื่อหัวข้อของคู่มือ">
                </div>
                <div class="edit-form-group">
                    <label>บทบาทที่เห็น</label>
                    <select id="editRole">
                        <option value="">ทุก Role (ทั่วไป)</option>
                        <option value="company">ผู้ประกอบการ</option>
                        <option value="auditor">กรรมการ</option>
                        <option value="ceo">ผู้บริหาร (CEO)</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="edit-form-group">
                    <label>หมวดหมู่</label>
                    <select id="editCategory">
                        <option value="overview">ภาพรวม</option>
                        <option value="company">ส่วนของผู้ประกอบการ</option>
                        <option value="auditor">ส่วนของคณะกรรมการ</option>
                        <option value="ceo">ส่วนของผู้บริหาร</option>
                        <option value="admin">ส่วนของผู้ดูแลระบบ</option>
                        <option value="account">การจัดการบัญชี</option>
                    </select>
                </div>
                <div class="edit-form-group">
                    <label>ลำดับการแสดง</label>
                    <input type="number" id="editOrder" placeholder="0" min="0">
                </div>
                <div class="edit-form-group">
                    <label>เนื้อหา (รองรับ Markdown)</label>
                    <textarea id="editContent" placeholder="### หัวข้อย่อย&#10;&#10;เนื้อหา...&#10;&#10;![คำอธิบายรูป](URL รูปภาพ)"></textarea>
                    <div class="markdown-hint">
                        รองรับ Markdown: <code>### หัวข้อ</code> <code>**ตัวหนา**</code> <code>*ตัวเอียง*</code> <code>- รายการ</code> <code>![alt](url)</code> สำหรับรูปภาพ
                    </div>
                </div>
                <div class="edit-form-group">
                    <label>อัปโหลดรูปภาพ (แทรกลิงก์ในเนื้อหาอัตโนมัติ)</label>
                    <div class="img-upload-zone" id="imgUploadZone">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto;">
                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p style="margin:0.5rem 0 0; font-size:0.9rem;">คลิกหรือลากไฟล์รูปภาพมาวางที่นี่</p>
                        <input type="file" id="imgFileInput" accept="image/*" multiple hidden>
                    </div>
                    <div class="img-preview-list" id="imgPreviewList"></div>
                </div>
            </div>
            <div class="edit-modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
                <button class="btn-save" onclick="saveManualItem()" id="btnSaveItem">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:0.25rem;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>
                    บันทึก
                </button>
            </div>
        </div>
    </div>

    <!-- Admin Floating Add Button -->
    <button id="btnAddManualItem" onclick="openEditModal(0)" title="เพิ่มหัวข้อใหม่" style="
        position: fixed; bottom: 5.5rem; right: 2rem; z-index: 50;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: #fff;
        width: 3.5rem; height: 3.5rem; border-radius: 50%; border: none;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; box-shadow: 0 10px 25px -5px rgba(59,130,246,0.4);
        transition: all 0.3s; font-size: 1.5rem;
    ">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
    </button>
    <?php endif; ?>
    
    <button id="backToTop" title="กลับขึ้นด้านบน">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        // Pass PHP data to JS
        const USER_ROLE = '<?php echo $userRole; ?>';
        const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        const BASE_URL = '<?php echo $baseUrl; ?>';
        
        // Manual items data for admin editing
        const manualItemsData = <?php echo json_encode(array_map(function($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'content' => $item['content'],
                'role' => $item['role'],
                'category' => $item['category'] ?? '',
                'display_order' => $item['display_order']
            ];
        }, $allManualItems), JSON_UNESCAPED_UNICODE); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            // 1. Initialize Mermaid
            if (typeof mermaid !== 'undefined') {
                mermaid.initialize({ startOnLoad: true, theme: 'neutral', securityLevel: 'loose' });
            }

            // 2. Render Markdown
            document.querySelectorAll('[data-markdown]').forEach(el => {
                const raw = el.textContent.trim();
                el.innerHTML = marked.parse(raw);
            });

            // 3. Search & Filters
            const searchInput = document.getElementById('manualSearch');
            const filterChips = document.querySelectorAll('.filter-chip');
            const sections = document.querySelectorAll('[data-section]');
            const tocGroups = document.querySelectorAll('.toc-category-group');
            const noResults = document.getElementById('noResults');
            
            // For non-admin users, auto-set to their role
            let currentFilter = IS_ADMIN ? 'all' : USER_ROLE;
            let currentSearch = '';

            function updateDisplay() {
                let visibleCount = 0;
                
                sections.forEach(section => {
                    const role = section.getAttribute('data-role');
                    const content = section.getAttribute('data-content').toLowerCase();
                    const title = section.querySelector('h2') ? section.querySelector('h2').textContent.toLowerCase() : '';
                    
                    // Role matching: show if filter is 'all', or section is 'all', or section matches filter
                    const roleMatch = (currentFilter === 'all' || role === currentFilter || role === 'all' || role === '');
                    const searchMatch = (content.includes(currentSearch.toLowerCase()) || title.includes(currentSearch.toLowerCase()));
                    
                    if (roleMatch && searchMatch) {
                        section.style.display = 'block';
                        setTimeout(() => section.classList.add('is-visible'), 50);
                        visibleCount++;
                    } else {
                        section.style.display = 'none';
                    }
                });

                // Update ToC visibility
                tocGroups.forEach(group => {
                    let groupHasVisibleItems = false;
                    const items = group.querySelectorAll('.toc-item');
                    items.forEach(item => {
                        const link = item.querySelector('a');
                        const sectionId = link.getAttribute('href').substring(1);
                        const section = document.getElementById(sectionId);
                        const itemRole = item.getAttribute('data-role');
                        
                        // ToC item visible if: its section is visible OR (no section but role matches)
                        let show = false;
                        if (section && section.style.display !== 'none') {
                            show = true;
                        } else if (!section) {
                            // ToC item without corresponding section — check role
                            show = (currentFilter === 'all' || itemRole === currentFilter || itemRole === 'all');
                        }
                        
                        item.style.display = show ? 'block' : 'none';
                        if (show) groupHasVisibleItems = true;
                    });
                    group.style.display = groupHasVisibleItems ? 'block' : 'none';
                });

                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            // Initial display based on user role
            updateDisplay();

            searchInput.addEventListener('input', (e) => {
                currentSearch = e.target.value;
                updateDisplay();
            });

            if (IS_ADMIN) {
                filterChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        filterChips.forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');
                        currentFilter = chip.getAttribute('data-filter');
                        updateDisplay();
                    });
                });
            }

            // 4. Scroll Animations (Intersection Observer)
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        const id = entry.target.getAttribute('id');
                        document.querySelectorAll('.manual-toc-list a').forEach(a => {
                            a.classList.remove('active');
                            if(a.getAttribute('href') === '#' + id) a.classList.add('active');
                        });
                    }
                });
            }, { threshold: 0.1, rootMargin: "-10% 0px -40% 0px" });

            document.querySelectorAll('.reveal-on-scroll').forEach(section => {
                observer.observe(section);
            });

            // 5. Back to Top
            const backToTopBtn = document.getElementById('backToTop');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    backToTopBtn.classList.add('visible');
                } else {
                    backToTopBtn.classList.remove('visible');
                }
            });
            backToTopBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            // 6. Print Handler — downloads as PDF
            document.getElementById('btnPrint').addEventListener('click', () => {
                HICM_PDF.download('.main-content', 'HICM_User_Manual.pdf');
            });

            // 7. Admin Image Upload
            if (IS_ADMIN) {
                const uploadZone = document.getElementById('imgUploadZone');
                const fileInput = document.getElementById('imgFileInput');
                const previewList = document.getElementById('imgPreviewList');

                uploadZone.addEventListener('click', () => fileInput.click());
                uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
                uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
                uploadZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadZone.classList.remove('dragover');
                    handleImageFiles(e.dataTransfer.files);
                });
                fileInput.addEventListener('change', () => handleImageFiles(fileInput.files));
            }
        });

        // ===== Admin Edit Functions =====
        function handleImageFiles(files) {
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('type', 'manual_image');

                fetch(BASE_URL + '/api/upload.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.file_path) {
                        // Add preview
                        const previewList = document.getElementById('imgPreviewList');
                        const item = document.createElement('div');
                        item.className = 'img-preview-item';
                        item.innerHTML = `
                            <img src="${BASE_URL}/${data.file_path}" alt="preview">
                            <button class="remove-img" onclick="this.parentElement.remove()">✕</button>
                        `;
                        previewList.appendChild(item);
                        
                        // Insert markdown image at cursor in textarea
                        const textarea = document.getElementById('editContent');
                        const imgMarkdown = `\n![${file.name}](${BASE_URL}/${data.file_path})\n`;
                        const pos = textarea.selectionStart || textarea.value.length;
                        textarea.value = textarea.value.substring(0, pos) + imgMarkdown + textarea.value.substring(pos);
                        textarea.focus();
                    } else {
                        alert('อัปโหลดรูปภาพล้มเหลว: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => alert('อัปโหลดรูปภาพล้มเหลว: ' + err.message));
            });
        }

        function openEditModal(itemId) {
            const overlay = document.getElementById('editModalOverlay');
            const titleEl = document.getElementById('editModalTitle');
            const idInput = document.getElementById('editItemId');
            const titleInput = document.getElementById('editTitle');
            const roleSelect = document.getElementById('editRole');
            const categorySelect = document.getElementById('editCategory');
            const orderInput = document.getElementById('editOrder');
            const contentArea = document.getElementById('editContent');
            document.getElementById('imgPreviewList').innerHTML = '';

            // Static built-in sections: manual-role-map, manual-flow, manual-database, etc.
            // These sections are edited with the same modal and saved as DB overrides.
            if (typeof itemId === 'string' && itemId.startsWith('static:')) {
                const sectionId = itemId.replace('static:', '');
                const section = document.getElementById(sectionId);
                if (!section) return;

                const titleNode = section.querySelector('.static-title-text');
                const contentNode = section.querySelector('.static-editable-content');
                let fallbackTitle = section.querySelector('h2') ? section.querySelector('h2').innerText : sectionId;
                fallbackTitle = fallbackTitle.replace(/Overview|Scoring|User Profiles|Tech|Examples|แก้ไขเนื้อหา/gi, '').trim();

                titleEl.textContent = 'แก้ไขหัวข้อหลัก';
                idInput.value = itemId;
                titleInput.value = (titleNode ? titleNode.textContent : fallbackTitle).trim();
                roleSelect.value = section.getAttribute('data-role') || '';
                categorySelect.value = section.getAttribute('data-category') || 'overview';
                orderInput.value = section.getAttribute('data-display-order') || '0';
                contentArea.value = contentNode ? contentNode.innerHTML.trim() : '';
                overlay.classList.add('active');
                return;
            }

            if (itemId === 0) {
                // New item
                titleEl.textContent = 'เพิ่มหัวข้อใหม่';
                idInput.value = '0';
                titleInput.value = '';
                roleSelect.value = '';
                categorySelect.value = 'overview';
                orderInput.value = '50';
                contentArea.value = '';
            } else {
                // Edit existing dynamic item
                titleEl.textContent = 'แก้ไขเนื้อหา';
                const item = manualItemsData.find(i => i.id == itemId);
                if (!item) return;
                idInput.value = item.id;
                titleInput.value = item.title;
                roleSelect.value = item.role || '';
                categorySelect.value = item.category || 'overview';
                orderInput.value = item.display_order || 0;
                contentArea.value = item.content;
            }

            overlay.classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModalOverlay').classList.remove('active');
        }

        function saveManualItem() {
            const btn = document.getElementById('btnSaveItem');
            const id = document.getElementById('editItemId').value;
            const title = document.getElementById('editTitle').value.trim();
            const role = document.getElementById('editRole').value;
            const category = document.getElementById('editCategory').value;
            const order = document.getElementById('editOrder').value;
            const content = document.getElementById('editContent').value.trim();

            if (!title) { alert('กรุณาระบุชื่อหัวข้อ'); return; }
            if (!content) { alert('กรุณาระบุเนื้อหา'); return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';

            const formData = new FormData();

            // Save static built-in sections to this page as DB overrides.
            if (id.startsWith('static:')) {
                formData.append('manual_ajax', 'static_save');
                formData.append('section_id', id.replace('static:', ''));
                formData.append('title', title);
                formData.append('role', role);
                formData.append('display_category', category);
                formData.append('display_order', order);
                formData.append('content', content);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeEditModal();
                        location.reload();
                    } else {
                        alert('บันทึกล้มเหลว: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:0.25rem;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg> บันทึก';
                });
                return;
            }

            formData.append('action', id === '0' ? 'create' : 'update');
            formData.append('id', id);
            formData.append('title', title);
            formData.append('role', role);
            formData.append('category', category);
            formData.append('display_order', order);
            formData.append('content', content);

            fetch(BASE_URL + '/api/save-manual.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    // Reload to reflect changes
                    location.reload();
                } else {
                    alert('บันทึกล้มเหลว: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err.message))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:0.25rem;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg> บันทึก';
            });
        }

        // Close modal on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.id === 'editModalOverlay') closeEditModal();
        });
        // Close modal on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>
</body>
</html>
