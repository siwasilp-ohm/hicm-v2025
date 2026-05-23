<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, username, avatar FROM users WHERE avatar IS NOT NULL AND avatar != ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Files in DB:\n";
    foreach ($users as $u) {
        echo "ID: " . $u['id'] . " | " . $u['username'] . " | " . $u['avatar'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
