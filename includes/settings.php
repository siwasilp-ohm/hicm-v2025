<?php
/**
 * Settings Management Logic
 */

require_once __DIR__ . '/../config/database.php';

function getAllSettings() {
    $db = getDB();
    $stmt = $db->getConnection()->query("SELECT * FROM settings ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getSetting($key) {
    $db = getDB();
    $stmt = $db->getConnection()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function updateSetting($key, $value) {
    $db = getDB();
    try {
        $stmt = $db->getConnection()->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateMultipleSettings($settings) {
    $db = getDB();
    try {
        $db->beginTransaction();
        
        $stmt = $db->getConnection()->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($settings as $key => $value) {
            // Handle boolean checkbox values
            if ($value === 'on') $value = 'true';
            
            $stmt->execute([$value, $key]);
        }
        
        // Handle unchecked checkboxes (if logic requires knowing what's unchecked)
        // For now, we assume passed settings array contains all intended updates.
        
        $db->commit();
        logActivity($_SESSION['user_id'], 'update_settings', 'อัปเดตการตั้งค่าระบบ');
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>
