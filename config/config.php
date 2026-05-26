<?php
/**
 * HICM V2025 Assessment System - Main Configuration
 */

// Session Configuration
session_start();

// Time Zone
date_default_timezone_set('Asia/Bangkok');

// Application Settings
define('APP_NAME', 'HICM V2025 Assessment System');
define('APP_VERSION', '1.0.0');
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/hicm-v2025', '/'));
define('APP_UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('APP_MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Allowed File Types for Upload
define('ALLOWED_FILE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
]);

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_AUDITOR', 'auditor');
define('ROLE_COMPANY', 'company');

// Assessment Scoring Levels
define('SCORE_LEVELS', [
    0 => ['label' => 'ไม่มีการดำเนินงาน', 'description' => 'ไม่มีนโยบาย ไม่มีการดำเนินการใดๆ หรือไม่มีหลักฐาน'],
    0.25 => ['label' => 'เริ่มดำเนินการเบื้องต้น', 'description' => 'มีการเริ่มต้นดำเนินการ มีนโยบายหรือแผนเบื้องต้น แต่ยังไม่มีหลักฐานชัดเจน'],
    0.5 => ['label' => 'มีการดำเนินงานบางส่วนพร้อมหลักฐาน', 'description' => 'มีการดำเนินงานอย่างจริงจัง มีหลักฐานประกอบ แต่ยังไม่ครอบคลุมหรือไม่สม่ำเสมอ'],
    0.75 => ['label' => 'ดำเนินงานครอบคลุมและมีการติดตาม', 'description' => 'มีการดำเนินงานอย่างครอบคลุม มีระบบติดตามประเมินผล มีการปรับปรุงตามผลการติดตาม'],
    1.0 => ['label' => 'ดำเนินงานครบถ้วนและยั่งยืน', 'description' => 'มีการดำเนินงานครบถ้วนตามเกณฑ์ มีระบบประเมินผลและพัฒนาอย่างต่อเนื่อง มีผลลัพธ์ที่ดีและยั่งยืน']
]);

// HICM Recognition Levels
define('HICM_LEVELS', [
    ['min' => 0, 'max' => 599, 'level' => 1, 'name' => 'เริ่มต้น', 'name_en' => 'Emerging', 'description' => 'องค์กรเริ่มให้ความสำคัญกับสุขภาวะ มีพื้นฐานบางส่วน'],
    ['min' => 600, 'max' => 699, 'level' => 2, 'name' => 'กำลังพัฒนา', 'name_en' => 'Developing', 'description' => 'องค์กรมีระบบพื้นฐานครบ กำลังพัฒนาให้ครอบคลุม'],
    ['min' => 700, 'max' => 799, 'level' => 3, 'name' => 'พัฒนาดี', 'name_en' => 'Performing', 'description' => 'องค์กรมีระบบที่ดี มีการดำเนินงานอย่างมีประสิทธิภาพ'],
    ['min' => 800, 'max' => 899, 'level' => 4, 'name' => 'เป็นเลิศ', 'name_en' => 'Excellence', 'description' => 'องค์กรมีมาตรฐานสูง เป็นแบบอย่างที่ดี'],
    ['min' => 900, 'max' => 1000, 'level' => 5, 'name' => 'ระดับโลก', 'name_en' => 'World-Class', 'description' => 'องค์กรมีมาตรฐานระดับสากล เป็นต้นแบบแห่งการเรียนรู้']
]);

// Pillar Configuration
define('PILLARS', [
    'H1' => [
        'code' => 'H1',
        'name_th' => 'การส่งเสริมสุขภาพ',
        'name_en' => 'Health Promotion',
        'weight' => 300,
        'indicators_count' => 15,
        'color' => '#10B981',
        'icon' => 'heart-pulse'
    ],
    'I2' => [
        'code' => 'I2',
        'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',
        'name_en' => 'Industrial Safety & Environment',
        'weight' => 300,
        'indicators_count' => 15,
        'color' => '#3B82F6',
        'icon' => 'hard-hat'
    ],
    'C3' => [
        'code' => 'C3',
        'name_th' => 'การมีส่วนร่วมกับชุมชน',
        'name_en' => 'Community Engagement',
        'weight' => 200,
        'indicators_count' => 15,
        'color' => '#F59E0B',
        'icon' => 'users'
    ],
    'M4' => [
        'code' => 'M4',
        'name_th' => 'การบริหารจัดการและความยั่งยืน',
        'name_en' => 'Management & Sustainability',
        'weight' => 200,
        'indicators_count' => 15,
        'color' => '#8B5CF6',
        'icon' => 'chart-bar'
    ]
]);

// Auditor Expertise List - ประเภทอุตสาหกรรม (Industry Categories)
define('AUDITOR_EXPERTISE', [
    'อาหารและเกษตร (Food & Beverage, Agricultural)',
    'ยานยนต์และโลหะ (Automotive, Metal, Machinery)',
    'อิเล็กทรอนิกส์ (Electronics, Electrical, Semiconductor)',
    'เคมีและพลาสติก (Chemical, Plastic, Petrochemical)',
    'เครื่องนุ่งห่มและสิ่งทอ (Textile, Garment, Footwear)',
    'ก่อสร้างและวัสดุ (Cement, Building Materials, Wood)',
    'โลจิสติกส์และบริการ (Warehouse, Logistics, Packaging)',
    'อื่นๆ (Others)'
]);

// Helper Functions
function getBaseUrl() {
    return APP_URL;
}

function getUploadPath() {
    return APP_UPLOAD_PATH;
}

function getUploadUrl() {
    return APP_URL . '/assets/uploads/';
}

define('AVATAR_PRESETS', ['avatar1.png','avatar2.png','avatar3.png','avatar4.png','avatar5.png','avatar6.png','avatar7.png']);

function getAvatarUrl($filename) {
    if (empty($filename) || $filename === 'default') return '';
    if (in_array($filename, AVATAR_PRESETS)) {
        return getBaseUrl() . '/assets/icon/' . $filename;
    }
    return getUploadUrl() . 'avatars/' . $filename;
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function formatDateTime($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function getScoreLabel($score) {
    $levels = SCORE_LEVELS;
    return isset($levels[$score]) ? $levels[$score]['label'] : 'ไม่ระบุ';
}

function getScoreDescription($score) {
    $levels = SCORE_LEVELS;
    return isset($levels[$score]) ? $levels[$score]['description'] : '';
}

function getHICMLevel($totalScore) {
    foreach (HICM_LEVELS as $level) {
        if ($totalScore >= $level['min'] && $totalScore <= $level['max']) {
            return $level;
        }
    }
    return HICM_LEVELS[0];
}

function calculateWeightedScore($rawScore, $pillarCode) {
    $pillars = PILLARS;
    if (!isset($pillars[$pillarCode])) {
        return 0;
    }
    
    $pillar = $pillars[$pillarCode];
    $maxRawScore = $pillar['indicators_count'] * 20; // 15 indicators x 20 points each
    $weightedScore = ($rawScore / $maxRawScore) * $pillar['weight'];
    
    return round($weightedScore, 2);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'company_id' => $_SESSION['company_id'] ?? null,
            'avatar' => $_SESSION['user_avatar'] ?? null
        ];
    }
    return null;
}

function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect(getBaseUrl() . '/pages/login.php');
    }
}

function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        $_SESSION['error'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
        redirect(getBaseUrl() . '/pages/dashboard.php');
    }
}

function showAlert($message, $type = 'info') {
    $types = ['success' => 'bg-green-100 text-green-800 border-green-300',
              'error' => 'bg-red-100 text-red-800 border-red-300',
              'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
              'info' => 'bg-blue-100 text-blue-800 border-blue-300'];
    
    $class = isset($types[$type]) ? $types[$type] : $types['info'];
    
    return '<div class="' . $class . ' border px-4 py-3 rounded-lg mb-4 animate-fade-in" role="alert">
        <span class="block sm:inline">' . htmlspecialchars($message) . '</span>
    </div>';
}

function getFlashMessage() {
    $output = '';
    foreach (['success', 'error', 'info', 'warning'] as $type) {
        $key = '_flash_' . $type;
        if (isset($_SESSION[$key])) {
            $msg = $_SESSION[$key];
            unset($_SESSION[$key]);
            $output .= showAlert($msg, $type);
        }
    }
    // Backward compatibility: check old keys
    if (isset($_SESSION['success'])) {
        $output .= showAlert($_SESSION['success'], 'success');
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        $output .= showAlert($_SESSION['error'], 'error');
        unset($_SESSION['error']);
    }
    return $output;
}

function setFlashMessage($message, $type = 'info') {
    // Use new unified key format
    $validTypes = ['success', 'error', 'info', 'warning'];
    if (!in_array($type, $validTypes)) $type = 'info';
    $_SESSION['_flash_' . $type] = $message;
}

/**
 * Auto-check period statuses on page load (throttled via session)
 * เรียกครั้งเดียวตอน config.php load — ถูก throttle ให้ทำงานไม่เกินทุก 5 นาที
 */
function _autoCheckPeriodStatuses() {
    // Only run if user is logged in (session exists)
    if (empty($_SESSION['user_id']) && empty($_SESSION['user'])) return;
    
    // Throttle: check at most once per 5 minutes
    if (isset($_SESSION['_period_status_checked']) && (time() - $_SESSION['_period_status_checked'] < 300)) {
        return;
    }
    
    // Lazy-load periods.php only when needed
    $periodsFile = __DIR__ . '/../includes/periods.php';
    if (file_exists($periodsFile)) {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/auth.php';
        require_once $periodsFile;
        
        if (function_exists('checkAndUpdatePeriodStatuses')) {
            // Pass true because wrapper already has its own 5-min throttle
            // — avoid double-throttle that blocks auto-announce checks
            $result = checkAndUpdatePeriodStatuses(true);
            // Auto-changes are logged but not shown as flash here
            // (flash messages would be confusing on random pages)
        }
    }
}

// Run auto-check (non-blocking, throttled)
_autoCheckPeriodStatuses();
?>
