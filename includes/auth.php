<?php
/**
 * Authentication, role checks, and rate-limited login.
 *
 * Design decisions:
 *  - Only students can self-register. Lecturer/admin accounts are created
 *    by an admin (built in a later module) - this stops a random visitor
 *    from signing up as "lecturer" or "admin".
 *  - Failed logins are logged per email AND per IP, and both are checked,
 *    so an attacker can't dodge the lockout by cycling IPs against one
 *    account, or spraying passwords across many accounts from one IP.
 */

declare(strict_types=1);

const MAX_LOGIN_ATTEMPTS    = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('/login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
    return $user;
}

function too_many_failed_attempts(string $email, string $ip): bool
{
    $threshold = (new DateTime())
        ->modify('-' . LOGIN_LOCKOUT_MINUTES . ' minutes')
        ->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE (email = :email OR ip_address = :ip)
           AND success = 0
           AND attempted_at > :threshold'
    );
    $stmt->execute(['email' => $email, 'ip' => $ip, 'threshold' => $threshold]);

    return (int) $stmt->fetch()['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(string $email, string $ip, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, :success)'
    );
    $stmt->execute(['email' => $email, 'ip' => $ip, 'success' => $success ? 1 : 0]);
}

/** Returns the session user array on success, or null on failure/lockout. */
function attempt_login(string $email, string $password): ?array
{
    $ip = client_ip();

    if ($email === '' || $password === '') {
        return null;
    }

    if (too_many_failed_attempts($email, $ip)) {
        return null;
    }

    $stmt = db()->prepare("SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($email, $ip, false);
        return null;
    }

    record_login_attempt($email, $ip, true);

    // Regenerate the session ID on privilege change to prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'            => (int) $user['id'],
        'full_name'     => $user['full_name'],
        'email'         => $user['email'],
        'role'          => $user['role'],
        'department_id' => $user['department_id'] !== null ? (int) $user['department_id'] : null,
        'level'         => $user['level'],
    ];

    audit_log((int) $user['id'], 'login', 'User logged in');

    return $_SESSION['user'];
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        audit_log($user['id'], 'logout', 'User logged out');
    }
    $_SESSION = [];
    session_destroy();
}

function audit_log(?int $userId, string $action, string $details = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (:uid, :action, :details, :ip)'
    );
    $stmt->execute([
        'uid'     => $userId,
        'action'  => $action,
        'details' => $details,
        'ip'      => client_ip(),
    ]);
}

/** @return array{success: bool, errors: string[]} */
function register_student(array $data): array
{
    $errors = [];

    $fullName = trim((string) ($data['full_name'] ?? ''));
    $email    = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $confirm  = (string) ($data['confirm_password'] ?? '');
    $deptId   = (int) ($data['department_id'] ?? 0);
    $level    = trim((string) ($data['level'] ?? ''));
    $regNo    = trim((string) ($data['identifier'] ?? ''));

    if ($fullName === '' || mb_strlen($fullName) < 2) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($deptId <= 0) {
        $errors[] = 'Select your department.';
    }
    if (!in_array($level, ['100', '200', '300', '400', '500'], true)) {
        $errors[] = 'Select a valid level.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $pdo = db();

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        return ['success' => false, 'errors' => ['An account with that email already exists.']];
    }

    $deptCheck = $pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
    $deptCheck->execute(['id' => $deptId]);
    if (!$deptCheck->fetch()) {
        return ['success' => false, 'errors' => ['Select a valid department.']];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role, department_id, level, identifier)
         VALUES (:name, :email, :hash, 'student', :dept, :level, :identifier)"
    );
    $stmt->execute([
        'name'       => $fullName,
        'email'      => $email,
        'hash'       => password_hash($password, PASSWORD_DEFAULT),
        'dept'       => $deptId,
        'level'      => $level,
        'identifier' => $regNo !== '' ? $regNo : null,
    ]);

    audit_log((int) $pdo->lastInsertId(), 'register', 'Self-registered as student');

    return ['success' => true, 'errors' => []];
}
