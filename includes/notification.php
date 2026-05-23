<?php
/**
 * HICM V2025 Assessment System - Notification Functions
 * ระบบแจ้งเตือนครบถ้วนสำหรับทุก role
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ===== Notification Types =====
define('NOTIF_TYPE_INFO', 'info');
define('NOTIF_TYPE_SUCCESS', 'success');
define('NOTIF_TYPE_WARNING', 'warning');
define('NOTIF_TYPE_ERROR', 'error');

// ===== Notification Categories =====
define('NOTIF_CAT_ASSESSMENT', 'assessment');
define('NOTIF_CAT_PERIOD', 'period');
define('NOTIF_CAT_SYSTEM', 'system');
define('NOTIF_CAT_ANNOUNCEMENT', 'announcement');

/**
 * Create a new notification
 */
function createNotification($userId, $title, $message, $link = null, $type = 'info', $category = 'system') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, type, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $title, $message, $link, $type]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for multiple users
 */
function createNotificationForUsers($userIds, $title, $message, $link = null, $type = 'info') {
    $count = 0;
    foreach ($userIds as $userId) {
        if (createNotification($userId, $title, $message, $link, $type)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Create notification for users by role
 */
function notifyUsersByRole($role, $title, $message, $link = null, $type = 'info') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE role = ? AND is_active = TRUE");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return createNotificationForUsers($users, $title, $message, $link, $type);
    } catch (Exception $e) {
        error_log("Error notifying users by role: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get unread notifications for a user
 */
function getUnreadNotifications($userId, $limit = 10) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, title, message, link, type, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 */
function getUnreadCount($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error counting notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead($notificationId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE notifications SET is_read = TRUE, read_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$notificationId]);
        return true;
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE notifications SET is_read = TRUE, read_at = NOW()
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete old notifications (cleanup)
 */
function deleteOldNotifications($daysOld = 30) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = TRUE
        ");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error deleting old notifications: " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// COMPANY NOTIFICATIONS - แจ้งเตือนสำหรับบริษัท
// ============================================================

/**
 * แจ้งบริษัทเมื่อเปิดรับสมัครรอบประเมินใหม่
 */
function notifyCompaniesNewPeriod($periodId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get period info
        $stmt = $db->prepare("SELECT name, year, start_date, end_date, submission_deadline FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) return 0;
        
        $title = "📢 เปิดรับสมัครประเมิน " . $period['name'];
        $message = "รอบประเมินปี " . $period['year'] . " เปิดรับสมัครแล้ว! กรุณาดำเนินการประเมินตนเองภายในวันที่ " . 
                   date('d/m/Y', strtotime($period['submission_deadline'] ?? $period['end_date']));
        $link = getBaseUrl() . "/pages/assessment-form.php";
        
        return notifyUsersByRole('company', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying companies new period: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้งบริษัทเมื่อใกล้ครบกำหนดส่งประเมิน
 */
function notifyCompaniesDeadlineReminder($periodId, $daysRemaining) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get period info
        $stmt = $db->prepare("SELECT name, submission_deadline FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) return 0;
        
        // Get companies that haven't submitted yet
        $stmt = $db->prepare("
            SELECT c.user_id 
            FROM companies c
            LEFT JOIN assessments a ON c.id = a.company_id AND a.period_id = ?
            WHERE c.is_active = TRUE AND (a.id IS NULL OR a.status = 'draft')
        ");
        $stmt->execute([$periodId]);
        $companyUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($companyUsers)) return 0;
        
        $title = "⏰ เหลืออีก " . $daysRemaining . " วัน ก่อนปิดรับประเมิน";
        $message = "รอบ " . $period['name'] . " จะปิดรับประเมินในวันที่ " . 
                   date('d/m/Y', strtotime($period['submission_deadline'])) . " กรุณาดำเนินการให้เสร็จสิ้น";
        $link = getBaseUrl() . "/pages/assessment-form.php";
        
        return createNotificationForUsers($companyUsers, $title, $message, $link, NOTIF_TYPE_WARNING);
    } catch (Exception $e) {
        error_log("Error notifying deadline reminder: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้งบริษัทเมื่อขยายเวลาประเมิน
 */
function notifyCompaniesExtendedDeadline($periodId, $newDeadline) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT name FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) return 0;
        
        $title = "📅 ขยายเวลาประเมิน " . $period['name'];
        $message = "ได้รับการขยายเวลาส่งประเมินถึงวันที่ " . date('d/m/Y', strtotime($newDeadline));
        $link = getBaseUrl() . "/pages/assessment-form.php";
        
        return notifyUsersByRole('company', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying extended deadline: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้งบริษัทเมื่อประเมินเสร็จสิ้น (ผลประเมินออกแล้ว)
 */
function notifyCompanyAssessmentCompleted($assessmentId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT a.final_score, a.hicm_level, c.user_id, c.company_name, p.name as period_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) return false;
        
        $levelNames = [1 => 'เริ่มต้น', 2 => 'กำลังพัฒนา', 3 => 'พัฒนาดี', 4 => 'เป็นเลิศ', 5 => 'ระดับโลก'];
        $levelName = $levelNames[$data['hicm_level']] ?? 'ไม่ระบุ';
        
        $title = "🎉 ผลการประเมิน " . $data['period_name'] . " ออกแล้ว!";
        $message = "คะแนนรวม: " . number_format($data['final_score'], 0) . "/1,000 คะแนน (Level " . $data['hicm_level'] . " - " . $levelName . ")";
        $link = getBaseUrl() . "/pages/assessment-result.php?id=" . $assessmentId;
        
        return createNotification($data['user_id'], $title, $message, $link, NOTIF_TYPE_SUCCESS);
    } catch (Exception $e) {
        error_log("Error notifying assessment completed: " . $e->getMessage());
        return false;
    }
}

/**
 * แจ้งบริษัทเมื่อกรรมการมีความเห็นเพิ่มเติม
 */
function notifyCompanyAuditorComment($assessmentId, $auditorName, $comment) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT c.user_id, c.company_name, p.name as period_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) return false;
        
        $title = "💬 มีความเห็นจากกรรมการ";
        $message = "กรรมการ " . $auditorName . " ได้ให้ความเห็นเพิ่มเติมในรอบ " . $data['period_name'];
        $link = getBaseUrl() . "/pages/assessment-result.php?id=" . $assessmentId;
        
        return createNotification($data['user_id'], $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying auditor comment: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// AUDITOR NOTIFICATIONS - แจ้งเตือนสำหรับกรรมการ
// ============================================================

/**
 * แจ้งกรรมการเมื่อบริษัทส่งประเมิน
 */
function notifyAssignedAuditors($assessmentId, $action = 'submitted') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get assessment and company info
        $stmt = $db->prepare("
            SELECT a.id, a.status, c.company_name, p.name as period_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) return false;
        
        // Get assigned auditors
        $stmt = $db->prepare("
            SELECT u.id, u.name
            FROM assessment_evaluators ae
            JOIN users u ON ae.user_id = u.id
            WHERE ae.assessment_id = ?
        ");
        $stmt->execute([$assessmentId]);
        $auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($auditors)) {
            // If no assigned auditors, notify all auditors
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'auditor' AND is_active = TRUE");
            $stmt->execute();
            $auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (empty($auditors)) return false;
        
        $actionText = match($action) {
            'submitted' => 'ส่งแบบประเมินแล้ว',
            'resubmitted' => 'แก้ไขและส่งแบบประเมินใหม่',
            default => 'อัปเดตแบบประเมิน'
        };
        $icon = match($action) {
            'submitted' => '📋',
            'resubmitted' => '🔄',
            default => '🔄'
        };
        $title = $icon . " " . $assessment['company_name'] . " " . $actionText;
        $message = "บริษัท " . $assessment['company_name'] . " " . $actionText . " รอบ " . $assessment['period_name'];
        $message .= $action === 'resubmitted' 
            ? " กรุณาตรวจประเมินใหม่อีกครั้ง" 
            : " กรุณาดำเนินการตรวจประเมิน";
        $link = getBaseUrl() . "/pages/auditor-evaluate.php?id=" . $assessmentId;
        
        $auditorIds = array_column($auditors, 'id');
        return createNotificationForUsers($auditorIds, $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying auditors: " . $e->getMessage());
        return false;
    }
}

/**
 * แจ้งกรรมการเมื่อได้รับมอบหมายตรวจประเมิน
 */
function notifyAuditorAssigned($auditorId, $assessmentId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT c.company_name, p.name as period_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) return false;
        
        $title = "📝 มอบหมายตรวจประเมินใหม่";
        $message = "ท่านได้รับมอบหมายให้ตรวจประเมิน " . $data['company_name'] . " รอบ " . $data['period_name'];
        $link = getBaseUrl() . "/pages/auditor-evaluate.php?id=" . $assessmentId;
        
        return createNotification($auditorId, $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying auditor assigned: " . $e->getMessage());
        return false;
    }
}

/**
 * แจ้งกรรมการเมื่อใกล้ครบกำหนดตรวจ
 */
function notifyAuditorsEvaluationDeadline($periodId, $daysRemaining) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT name, evaluation_end_date FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) return 0;
        
        // Get auditors with pending evaluations
        $stmt = $db->prepare("
            SELECT DISTINCT ae.user_id
            FROM assessment_evaluators ae
            JOIN assessments a ON ae.assessment_id = a.id
            WHERE a.period_id = ? AND a.status IN ('submitted', 'under_review')
        ");
        $stmt->execute([$periodId]);
        $auditorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($auditorIds)) return 0;
        
        $title = "⏰ เหลืออีก " . $daysRemaining . " วัน ก่อนปิดการตรวจประเมิน";
        $message = "รอบ " . $period['name'] . " จะปิดการตรวจประเมินในวันที่ " . 
                   date('d/m/Y', strtotime($period['evaluation_end_date'])) . " กรุณาดำเนินการให้เสร็จสิ้น";
        $link = getBaseUrl() . "/pages/my-assessments.php";
        
        return createNotificationForUsers($auditorIds, $title, $message, $link, NOTIF_TYPE_WARNING);
    } catch (Exception $e) {
        error_log("Error notifying evaluation deadline: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้งกรรมการเมื่อมีบริษัทใหม่รอตรวจ
 */
function notifyAuditorsPendingAssessments() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get count of pending assessments
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM assessments 
            WHERE status = 'submitted'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'] ?? 0;
        
        if ($count == 0) return 0;
        
        $title = "📊 มี " . $count . " บริษัทรอตรวจประเมิน";
        $message = "มีแบบประเมินรอการตรวจสอบ " . $count . " รายการ";
        $link = getBaseUrl() . "/pages/assessments.php?status=submitted";
        
        return notifyUsersByRole('auditor', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying pending assessments: " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// ADMIN NOTIFICATIONS - แจ้งเตือนสำหรับผู้ดูแลระบบ
// ============================================================

/**
 * แจ้ง Admin เมื่อมีบริษัทลงทะเบียนใหม่
 */
function notifyAdminsNewCompany($companyId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT company_name FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) return 0;
        
        $title = "🏢 บริษัทใหม่ลงทะเบียน";
        $message = $company['company_name'] . " ได้ลงทะเบียนเข้าสู่ระบบ";
        $link = getBaseUrl() . "/pages/companies.php";
        
        return notifyUsersByRole('admin', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying new company: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้ง Admin เมื่อมีปัญหาระบบ
 */
function notifyAdminsSystemIssue($issue) {
    $title = "⚠️ แจ้งเตือนระบบ";
    $message = $issue;
    $link = getBaseUrl() . "/pages/settings.php";
    
    return notifyUsersByRole('admin', $title, $message, $link, NOTIF_TYPE_ERROR);
}

/**
 * แจ้ง Admin สรุปรายวัน
 */
function notifyAdminsDailySummary() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get today's stats
        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM assessments WHERE DATE(created_at) = CURDATE()) as new_assessments,
                (SELECT COUNT(*) FROM assessments WHERE DATE(submitted_at) = CURDATE()) as submitted_today,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND role = 'company') as new_companies
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats['new_assessments'] == 0 && $stats['submitted_today'] == 0 && $stats['new_companies'] == 0) {
            return 0;
        }
        
        $title = "📈 สรุปประจำวัน " . date('d/m/Y');
        $message = "วันนี้: " . $stats['new_companies'] . " บริษัทใหม่, " . 
                   $stats['submitted_today'] . " ส่งประเมิน";
        $link = getBaseUrl() . "/pages/dashboard.php";
        
        return notifyUsersByRole('admin', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error creating daily summary: " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// CEO NOTIFICATIONS - แจ้งเตือนสำหรับ CEO
// ============================================================

/**
 * แจ้ง CEO เมื่อมีผลประเมินใหม่
 */
function notifyCEOsNewResults($periodId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT name FROM assessment_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) return 0;
        
        $title = "📊 ผลการประเมิน " . $period['name'] . " ออกแล้ว";
        $message = "ผลการประเมินรอบ " . $period['name'] . " พร้อมให้ตรวจสอบแล้ว";
        $link = getBaseUrl() . "/pages/reports.php";
        
        return notifyUsersByRole('ceo', $title, $message, $link, NOTIF_TYPE_SUCCESS);
    } catch (Exception $e) {
        error_log("Error notifying CEO new results: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้ง CEO สรุปสถิติประจำสัปดาห์
 */
function notifyCEOsWeeklySummary() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM assessments WHERE status = 'completed') as completed,
                (SELECT AVG(final_score) FROM assessments WHERE status = 'completed') as avg_score,
                (SELECT COUNT(*) FROM companies WHERE is_active = TRUE) as total_companies
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $title = "📈 สรุปสถิติประจำสัปดาห์";
        $message = "บริษัทที่ประเมินเสร็จ: " . $stats['completed'] . " ราย, คะแนนเฉลี่ย: " . 
                   number_format($stats['avg_score'] ?? 0, 0) . "/1,000";
        $link = getBaseUrl() . "/pages/reports.php";
        
        return notifyUsersByRole('ceo', $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error creating CEO weekly summary: " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// ANNOUNCEMENT NOTIFICATIONS - แจ้งเตือนประกาศ
// ============================================================

/**
 * แจ้งเตือนประกาศใหม่ให้ทุกคน
 */
function notifyAllUsersAnnouncement($announcementId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Try announcements table first, fallback to news table
        $stmt = $db->prepare("SELECT title, content FROM announcements WHERE id = ?");
        $stmt->execute([$announcementId]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$announcement) {
            // Fallback to news table
            $stmt = $db->prepare("SELECT title, content FROM news WHERE id = ?");
            $stmt->execute([$announcementId]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$announcement) return 0;
        
        // Get all active users
        $stmt = $db->prepare("SELECT id FROM users WHERE is_active = TRUE");
        $stmt->execute();
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "📣 ประกาศ: " . mb_substr($announcement['title'], 0, 50, 'UTF-8');
        $message = mb_substr(strip_tags($announcement['content']), 0, 100, 'UTF-8') . '...';
        $link = getBaseUrl() . "/pages/announcements.php";
        
        return createNotificationForUsers($userIds, $title, $message, $link, NOTIF_TYPE_INFO);
    } catch (Exception $e) {
        error_log("Error notifying announcement: " . $e->getMessage());
        return 0;
    }
}

/**
 * แจ้งเตือนประกาศให้ role เฉพาะ
 */
function notifyRoleAnnouncement($role, $newsTitle, $newsContent) {
    $title = "📣 ประกาศ: " . mb_substr($newsTitle, 0, 50, 'UTF-8');
    $message = mb_substr(strip_tags($newsContent), 0, 100, 'UTF-8') . '...';
    $link = getBaseUrl() . "/pages/announcements.php";
    
    return notifyUsersByRole($role, $title, $message, $link, NOTIF_TYPE_INFO);
}

// ============================================================
// REAL-TIME NOTIFICATIONS - ตรวจสอบและสร้างแจ้งเตือนอัตโนมัติ
// ============================================================

/**
 * ตรวจสอบและสร้างแจ้งเตือนที่จำเป็นสำหรับ user
 * เรียกใช้ทุกครั้งที่ load หน้า
 */
function checkAndCreateNotifications($userId, $userRole) {
    try {
        $db = Database::getInstance()->getConnection();
        $notifications = [];
        
        switch ($userRole) {
            case 'company':
                $notifications = checkCompanyNotifications($db, $userId);
                break;
            case 'auditor':
                $notifications = checkAuditorNotifications($db, $userId);
                break;
            case 'admin':
                $notifications = checkAdminNotifications($db, $userId);
                break;
            case 'ceo':
                $notifications = checkCEONotifications($db, $userId);
                break;
        }
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Error checking notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * ตรวจสอบแจ้งเตือนสำหรับบริษัท
 */
function checkCompanyNotifications($db, $userId) {
    $notifications = [];
    
    // Get company info
    $stmt = $db->prepare("SELECT id FROM companies WHERE user_id = ?");
    $stmt->execute([$userId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) return $notifications;
    
    // Check for active period that needs assessment
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.submission_deadline, 
               DATEDIFF(p.submission_deadline, CURDATE()) as days_remaining,
               a.id as assessment_id, a.status
        FROM assessment_periods p
        LEFT JOIN assessments a ON a.period_id = p.id AND a.company_id = ?
        WHERE p.status = 'open' AND p.submission_deadline >= CURDATE()
        ORDER BY p.submission_deadline ASC
        LIMIT 1
    ");
    $stmt->execute([$company['id']]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($period) {
        // Check deadline reminder (7 days, 3 days, 1 day)
        if (!$period['assessment_id'] || $period['status'] == 'draft') {
            if ($period['days_remaining'] <= 7 && $period['days_remaining'] > 0) {
                $urgency = $period['days_remaining'] <= 1 ? NOTIF_TYPE_ERROR : 
                          ($period['days_remaining'] <= 3 ? NOTIF_TYPE_WARNING : NOTIF_TYPE_INFO);
                
                // Check if already notified today
                $notifKey = 'deadline_' . $period['id'] . '_' . date('Y-m-d');
                $stmt = $db->prepare("
                    SELECT id FROM notifications 
                    WHERE user_id = ? AND title LIKE ? AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$userId, '%เหลืออีก%วัน%']);
                
                if (!$stmt->fetch()) {
                    $notifications[] = [
                        'type' => 'deadline_reminder',
                        'days' => $period['days_remaining'],
                        'period' => $period['name']
                    ];
                }
            }
        }
    }
    
    return $notifications;
}

/**
 * ตรวจสอบแจ้งเตือนสำหรับกรรมการ
 */
function checkAuditorNotifications($db, $userId) {
    $notifications = [];
    
    // Check for pending assessments assigned to this auditor
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM assessment_evaluators ae
        JOIN assessments a ON ae.assessment_id = a.id
        WHERE ae.user_id = ? AND a.status IN ('submitted', 'under_review')
    ");
    $stmt->execute([$userId]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending['count'] > 0) {
        $notifications[] = [
            'type' => 'pending_evaluations',
            'count' => $pending['count']
        ];
    }
    
    return $notifications;
}

/**
 * ตรวจสอบแจ้งเตือนสำหรับ Admin
 */
function checkAdminNotifications($db, $userId) {
    $notifications = [];
    
    // Check for new companies pending approval (if applicable)
    // Check for system issues
    // etc.
    
    return $notifications;
}

/**
 * ตรวจสอบแจ้งเตือนสำหรับ CEO
 */
function checkCEONotifications($db, $userId) {
    $notifications = [];
    
    // Check for new completed assessments
    // Check for reports ready
    // etc.
    
    return $notifications;
}

?>
