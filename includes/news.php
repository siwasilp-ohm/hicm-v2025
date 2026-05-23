<?php
/**
 * HICM V2025 Assessment System - News Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get all announcements
 * 
 * @param int $limit Number of records to return (default: 0 = all)
 * @param string $status Filter by status (active, inactive, all)
 * @return array
 */
function getAnnouncements($limit = 0, $status = 'all') {
    $db = getDB();
    
    $sql = "SELECT a.*, u.name as author_name 
            FROM announcements a 
            LEFT JOIN users u ON a.created_by = u.id 
            WHERE 1=1";
    
    $params = [];
    
    if ($status !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get announcement by ID
 * 
 * @param int $id
 * @return array|false
 */
function getAnnouncementById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Create new announcement
 * 
 * @param string $title
 * @param string $content
 * @param string $status
 * @param int $createdBy
 * @param bool $notifyUsers Whether to notify users (default: true)
 * @return int|false Last insert ID or false
 */
function createAnnouncement($title, $content, $status, $createdBy, $notifyUsers = true) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO announcements (title, content, status, created_by) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $content, $status, $createdBy])) {
        $announcementId = $db->lastInsertId();
        
        // Notify all users if announcement is active
        if ($notifyUsers && $status === 'active') {
            require_once __DIR__ . '/notification.php';
            notifyAllUsersAnnouncement($announcementId);
        }
        
        return $announcementId;
    }
    return false;
}

/**
 * Update announcement
 * 
 * @param int $id
 * @param string $title
 * @param string $content
 * @param string $status
 * @return bool
 */
function updateAnnouncement($id, $title, $content, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE announcements SET title = ?, content = ?, status = ? WHERE id = ?");
    return $stmt->execute([$title, $content, $status, $id]);
}

/**
 * Delete announcement
 * 
 * @param int $id
 * @return bool
 */
function deleteAnnouncement($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
    return $stmt->execute([$id]);
}
?>
