<?php
/**
 * Run once from the command line to create the first admin account:
 *   php database/seed_admin.php
 *
 * Never hardcode a password hash in schema.sql - always generate it
 * through PHP's password_hash() so it uses your server's current
 * bcrypt cost factor.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

fwrite(STDOUT, "Admin full name: ");
$name = trim((string) fgets(STDIN));

fwrite(STDOUT, "Admin email: ");
$email = trim((string) fgets(STDIN));

fwrite(STDOUT, "Admin password (min 8 chars): ");
$password = trim((string) fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo  = db();

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, status)
     VALUES (:name, :email, :hash, "admin", "active")
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);

$stmt->execute([
    'name'  => $name,
    'email' => $email,
    'hash'  => $hash,
]);

fwrite(STDOUT, "Admin account ready for {$email}.\n");
