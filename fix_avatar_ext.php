<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fix avatar1 -> avatar1.png
    $stmt = $db->prepare("UPDATE users SET avatar = CONCAT(avatar, '.png') WHERE avatar NOT LIKE '%.png' AND avatar NOT LIKE '%.jpg' AND avatar != ''");
    $stmt->execute();
    
    echo "Updated " . $stmt->rowCount() . " records.\n";
    
    // Verify
    $stmt = $db->query("SELECT id, username, avatar FROM users WHERE avatar IS NOT NULL AND avatar != ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current Avatars:\n";
    foreach ($users as $u) {
        echo $u['username'] . ": " . $u['avatar'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
