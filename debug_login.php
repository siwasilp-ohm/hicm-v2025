<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$username = 'admin1';
$password = '123';

echo "<h2>Debugging Login for User: $username</h2>";

$db = getDB();
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p style='color:green'>User found.</p>";
        echo "Stored Hash: " . $user['password_hash'] . "<br>";
        
        if (password_verify($password, $user['password_hash'])) {
            echo "<p style='color:green'><strong>Password '123' MATCHES the stored hash.</strong></p>";
        } else {
            echo "<p style='color:red'><strong>Password '123' DOES NOT match.</strong></p>";
            echo "Expected Hash for '123': " . password_hash('123', PASSWORD_DEFAULT) . "<br>";
        }
    } else {
        echo "<p style='color:red'>User '$username' not found!</p>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
