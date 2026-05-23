<?php
/**
 * Script to assign random demo avatars to companies and auditors
 * Avatars: avatar1.png - avatar6.png
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // Avatar options
    $avatars = [
        'avatar1.png',
        'avatar2.png',
        'avatar3.png',
        'avatar4.png',
        'avatar5.png',
        'avatar6.png'
    ];
    
    echo "=== Random Avatar Assignment Script ===\n\n";
    
    // Get all companies and auditors
    $stmt = $db->prepare("SELECT id, username, name, role, avatar FROM users WHERE role IN ('company', 'auditor') ORDER BY role, id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users (companies + auditors)\n\n";
    
    // Prepare update statement
    $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    
    $updatedCount = 0;
    $companyCount = 0;
    $auditorCount = 0;
    
    foreach ($users as $user) {
        // Randomly select an avatar
        $randomAvatar = $avatars[array_rand($avatars)];
        
        // Update user
        $updateStmt->execute([$randomAvatar, $user['id']]);
        $updatedCount++;
        
        if ($user['role'] === 'company') {
            $companyCount++;
        } else {
            $auditorCount++;
        }
        
        echo sprintf(
            "[%s] ID: %d | %s | %s -> %s\n",
            strtoupper($user['role']),
            $user['id'],
            mb_substr($user['name'], 0, 30, 'UTF-8'),
            $user['avatar'] ?: '(none)',
            $randomAvatar
        );
    }
    
    echo "\n=== Summary ===\n";
    echo "Total updated: $updatedCount users\n";
    echo "- Companies: $companyCount\n";
    echo "- Auditors: $auditorCount\n";
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
