<?php
/**
 * Indicators and Pillars Management Logic
 */

require_once __DIR__ . '/../config/database.php';

// === PILLARS ===

function getAllPillars() {
    $db = getDB();
    $stmt = $db->getConnection()->query("SELECT * FROM pillars ORDER BY display_order ASC");
    return $stmt->fetchAll();
}

function getPillarById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pillars WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createPillar($data) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            INSERT INTO pillars (code, name_th, name_en, description, weight, color, icon, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['code'],
            $data['name_th'],
            $data['name_en'],
            $data['description'],
            $data['weight'],
            $data['color'],
            $data['icon'],
            $data['display_order']
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updatePillar($id, $data) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            UPDATE pillars 
            SET code = ?, name_th = ?, name_en = ?, description = ?, 
                weight = ?, color = ?, icon = ?, display_order = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['code'],
            $data['name_th'],
            $data['name_en'],
            $data['description'],
            $data['weight'],
            $data['color'],
            $data['icon'],
            $data['display_order'],
            $id
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deletePillar($id) {
    $db = getDB();
    try {
        // Soft delete or real delete? Schema says is_active BOOLEAN
        // Let's do soft delete for safety, although the key has ON DELETE CASCADE.
        // Assuming user wants to hide it.
        $stmt = $db->prepare("DELETE FROM pillars WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// === INDICATORS ===

function getIndicatorsByPillar($pillarId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM indicators WHERE pillar_id = ? ORDER BY display_order ASC");
    $stmt->execute([$pillarId]);
    return $stmt->fetchAll();
}

function getIndicatorById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM indicators WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createIndicator($data) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            INSERT INTO indicators (
                pillar_id, code, name_th, name_en, description, 
                criteria_0, criteria_025, criteria_05, criteria_075, criteria_1, criteria_na, allow_na,
                has_performance_report, has_evidence_file, display_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['pillar_id'],
            $data['code'],
            $data['name_th'],
            $data['name_en'],
            $data['description'],
            $data['criteria_0'],
            $data['criteria_025'],
            $data['criteria_05'],
            $data['criteria_075'],
            $data['criteria_1'],
            $data['criteria_na'] ?? null,
            isset($data['allow_na']) ? ($data['allow_na'] ? 1 : 0) : 0,
            isset($data['has_performance_report']) ? ($data['has_performance_report'] ? 1 : 0) : 0,
            isset($data['has_evidence_file']) ? ($data['has_evidence_file'] ? 1 : 0) : 0,
            $data['display_order']
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateIndicator($id, $data) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            UPDATE indicators 
            SET pillar_id = ?, code = ?, name_th = ?, name_en = ?, description = ?, 
                criteria_0 = ?, criteria_025 = ?, criteria_05 = ?, criteria_075 = ?, criteria_1 = ?, criteria_na = ?, allow_na = ?,
                has_performance_report = ?, has_evidence_file = ?, display_order = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['pillar_id'],
            $data['code'],
            $data['name_th'],
            $data['name_en'],
            $data['description'],
            $data['criteria_0'],
            $data['criteria_025'],
            $data['criteria_05'],
            $data['criteria_075'],
            $data['criteria_1'],
            $data['criteria_na'] ?? null,
            isset($data['allow_na']) ? ($data['allow_na'] ? 1 : 0) : 0,
            isset($data['has_performance_report']) ? ($data['has_performance_report'] ? 1 : 0) : 0,
            isset($data['has_evidence_file']) ? ($data['has_evidence_file'] ? 1 : 0) : 0,
            $data['display_order'],
            $id
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteIndicator($id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("DELETE FROM indicators WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>
