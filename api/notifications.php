<?php
/**
 * HICM V2025 - Notifications API
 * API สำหรับระบบแจ้งเตือน
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notification.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $id = $data['id'] ?? 0;
            if ($id) {
                markNotificationAsRead($id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid ID']);
            }
            break;
            
        case 'mark_all_read':
            markAllNotificationsAsRead($user['id']);
            echo json_encode(['success' => true]);
            break;

        case 'dismiss':
            $id = $data['id'] ?? 0;
            if ($id) {
                $result = dismissNotification($id, $user['id']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['error' => 'Invalid ID']);
            }
            break;

        case 'clear_all':
            $count = clearAllNotificationsForUser($user['id']);
            echo json_encode(['success' => true, 'deleted' => $count]);
            break;

        case 'admin_clear_user':
            if (hasRole('admin')) {
                $targetUserId = intval($data['user_id'] ?? 0);
                if ($targetUserId) {
                    $count = clearAllNotificationsForUser($targetUserId);
                    echo json_encode(['success' => true, 'deleted' => $count]);
                } else {
                    echo json_encode(['error' => 'User ID required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
        
        // Admin/System actions
        case 'notify_period_open':
            if (hasRole('admin')) {
                $periodId = $data['period_id'] ?? 0;
                if ($periodId) {
                    $count = notifyCompaniesNewPeriod($periodId);
                    echo json_encode(['success' => true, 'notified' => $count]);
                } else {
                    echo json_encode(['error' => 'Period ID required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
            
        case 'notify_deadline_reminder':
            if (hasRole('admin')) {
                $periodId = $data['period_id'] ?? 0;
                $days = $data['days'] ?? 7;
                if ($periodId) {
                    $count = notifyCompaniesDeadlineReminder($periodId, $days);
                    echo json_encode(['success' => true, 'notified' => $count]);
                } else {
                    echo json_encode(['error' => 'Period ID required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
            
        case 'notify_extended_deadline':
            if (hasRole('admin')) {
                $periodId = $data['period_id'] ?? 0;
                $newDeadline = $data['new_deadline'] ?? '';
                if ($periodId && $newDeadline) {
                    $count = notifyCompaniesExtendedDeadline($periodId, $newDeadline);
                    echo json_encode(['success' => true, 'notified' => $count]);
                } else {
                    echo json_encode(['error' => 'Period ID and new deadline required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
            
        case 'notify_announcement':
            if (hasRole('admin')) {
                $newsId = $data['news_id'] ?? 0;
                $targetRole = $data['target_role'] ?? 'all';
                
                if ($newsId) {
                    if ($targetRole === 'all') {
                        $count = notifyAllUsersAnnouncement($newsId);
                    } else {
                        $db = Database::getInstance()->getConnection();
                        $stmt = $db->prepare("SELECT title, content FROM news WHERE id = ?");
                        $stmt->execute([$newsId]);
                        $news = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($news) {
                            $count = notifyRoleAnnouncement($targetRole, $news['title'], $news['content']);
                        } else {
                            $count = 0;
                        }
                    }
                    echo json_encode(['success' => true, 'notified' => $count]);
                } else {
                    echo json_encode(['error' => 'News ID required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
            
        case 'create_custom':
            if (hasRole('admin')) {
                $targetRole = $data['target_role'] ?? '';
                $targetUserId = $data['target_user_id'] ?? 0;
                $title = $data['title'] ?? '';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? null;
                $type = $data['type'] ?? 'info';
                
                if (empty($title)) {
                    echo json_encode(['error' => 'Title required']);
                    break;
                }
                
                if ($targetUserId) {
                    $result = createNotification($targetUserId, $title, $message, $link, $type);
                    echo json_encode(['success' => (bool)$result, 'notification_id' => $result]);
                } elseif ($targetRole) {
                    $count = notifyUsersByRole($targetRole, $title, $message, $link, $type);
                    echo json_encode(['success' => true, 'notified' => $count]);
                } else {
                    echo json_encode(['error' => 'Target role or user ID required']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $notifications = getUnreadNotifications($user['id'], $limit);
            $unreadCount = getUnreadCount($user['id']);
            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            break;
            
        case 'count':
            $unreadCount = getUnreadCount($user['id']);
            echo json_encode(['unread_count' => $unreadCount]);
            break;
            
        case 'check':
            // Check for new notifications that should be created
            $newNotifs = checkAndCreateNotifications($user['id'], $user['role']);
            echo json_encode(['pending_alerts' => $newNotifs]);
            break;
            
        default:
            $notifications = getUnreadNotifications($user['id'], 20);
            $unreadCount = getUnreadCount($user['id']);
            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete old read notifications (cleanup)
    if (hasRole('admin')) {
        $days = $_GET['days'] ?? 30;
        $deleted = deleteOldNotifications($days);
        echo json_encode(['success' => true, 'deleted' => $deleted]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
