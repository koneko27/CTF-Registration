php
<?php
require_once __DIR__ . '/api/db.php';

$pdo = get_pdo();

// Check if admin exists
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = $stmt->fetchColumn();

if ($adminCount > 0) {
    echo "Admin account already exists!\n";
    $stmt = $pdo->query("SELECT username, email FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($admins as $admin) {
        echo "  - Username: {$admin['username']}, Email: {$admin['email']}\n";
    }
    exit(0);
}

// Create default admin
$adminPassword = 'Admin@CTF2024!';
$passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (full_name, email, username, password_hash, role, email_verified, created_at, updated_at)
    VALUES (:full_name, :email, :username, :password_hash, 'admin', TRUE, NOW(), NOW())
");

$stmt->execute([
    ':full_name' => 'Admin User',
    ':email' => 'admin@ctf.local',
    ':username' => 'admin',
    ':password_hash' => $passwordHash
]);

echo "✅ Default admin account created!\n\n";
echo "=================================\n";
echo "Admin Credentials:\n";
echo "=================================\n";
echo "Username: admin\n";
echo "Email: admin@ctf.local\n";
echo "Password: Admin@CTF2024!\n";
echo "=================================\n";
echo "\nLogin at: http://localhost:9000/#signin\n";
?>
