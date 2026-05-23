<?php
/**
 * Setup Authentication
 * - Adds remember_token column to users table
 * - Resets all passwords to '123'
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>Setting up Authentication...</h1>";

$db = getDB();

// 1. Add remember_token column
try {
    echo "<p>Checking users table structure...</p>";
    $stmt = $db->getConnection()->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $db->getConnection()->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL AFTER is_active");
        echo "<p style='color: green'>Added 'remember_token' column to users table.</p>";
    } else {
        echo "<p style='color: blue'>'remember_token' column already exists.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>Error updating table: " . $e->getMessage() . "</p>";
}

// 2. Reset passwords
try {
    echo "<p>Resetting passwords...</p>";
    
    // Hash for "123"
    $hash = password_hash('123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ?");
    $stmt->execute([$hash]);
    
    echo "<p style='color: green'>Reset all user passwords to '123'.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red'>Error resetting passwords: " . $e->getMessage() . "</p>";
}

// 3. Ensure all demo users exist and are active
try {
    echo "<p>Checking for demo users...</p>";
    $passwordHash = password_hash('123', PASSWORD_DEFAULT);
    
    $demoUsers = [
        ['username' => 'admin1', 'role' => 'admin', 'name' => 'แอดมิน หนึ่ง'],
        ['username' => 'admin2', 'role' => 'admin', 'name' => 'แอดมิน สอง'],
        ['username' => 'aud1', 'role' => 'auditor', 'name' => 'กรรมการ หนึ่ง'],
        ['username' => 'aud2', 'role' => 'auditor', 'name' => 'กรรมการ สอง'],
        ['username' => 'aud3', 'role' => 'auditor', 'name' => 'กรรมการ สาม'],
        ['username' => 'com1', 'role' => 'company', 'name' => 'บริษัท 1'],
        ['username' => 'com2', 'role' => 'company', 'name' => 'บริษัท 2'],
        ['username' => 'com3', 'role' => 'company', 'name' => 'บริษัท 3'],
        ['username' => 'com4', 'role' => 'company', 'name' => 'บริษัท 4'],
        ['username' => 'com5', 'role' => 'company', 'name' => 'บริษัท 5'],
        ['username' => 'ceo1', 'role' => 'ceo', 'name' => 'ซีอีโอ หนึ่ง'],
        ['username' => 'ceo2', 'role' => 'ceo', 'name' => 'ซีอีโอ สอง']
    ];

    foreach ($demoUsers as $u) {
        $stmt = $db->getConnection()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$u['username']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $stmt = $db->getConnection()->prepare("
                INSERT INTO users (username, email, password_hash, name, role, is_active) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $email = $u['username'] . '@example.com';
            $stmt->execute([$u['username'], $email, $passwordHash, $u['name'], $u['role']]);
            echo "<p style='color: green'>Created user '{$u['username']}'.</p>";
        } else {
            // Update password and active status
            $stmt = $db->getConnection()->prepare("UPDATE users SET is_active = 1, password_hash = ? WHERE username = ?");
            $stmt->execute([$passwordHash, $u['username']]);
            echo "<p style='color: blue'>Updated user '{$u['username']}' (Active + Password Reset).</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red'>Error handling demo users: " . $e->getMessage() . "</p>";
}

// 4. Verify users
try {
    echo "<p>Verifying users...</p>";
    $stmt = $db->getConnection()->query("SELECT id, username, role, is_active FROM users ORDER BY role, username");
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Username</th><th>Role</th><th>Active</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['role']}</td><td>{$u['is_active']}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red'>Error listing users: " . $e->getMessage() . "</p>";
}

echo "<h2>Setup Completed!</h2>";
echo "<p><a href='index.php'>Go to Home</a></p>";
?>
