<?php
/**
 * Login, session guard, logout. Assumes session_start() has already
 * been called by the page (see CLAUDE.md page template).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const SESSION_IDLE_LIMIT_SECONDS = 15 * 60;
const FAILED_LOGIN_LOCKOUT_THRESHOLD = 5;
const LOCKOUT_DURATION_SECONDS = 15 * 60;

function attempt_login(string $username, string $password): array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'reason' => 'Invalid username or password.'];
    }

    if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        $minutesLeft = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
        $unit = $minutesLeft === 1 ? 'minute' : 'minutes';
        return ['success' => false, 'reason' => "Account temporarily locked. Try again in {$minutesLeft} {$unit}."];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $failedCount = $user['failed_login_count'] + 1;
        $lockedUntil = null;
        if ($failedCount >= FAILED_LOGIN_LOCKOUT_THRESHOLD) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION_SECONDS);
        }

        $pdo->prepare('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE user_id = ?')
            ->execute([$failedCount, $lockedUntil, $user['user_id']]);

        return ['success' => false, 'reason' => 'Invalid username or password.'];
    }

    if (!$user['active']) {
        return ['success' => false, 'reason' => 'Invalid username or password.'];
    }

    $pdo->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE user_id = ?')
        ->execute([$user['user_id']]);

    $role = determine_role($pdo, (int) $user['user_id']);
    if ($role === null) {
        return ['success' => false, 'reason' => 'Account has no assigned role.'];
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $role;
    $_SESSION['role_id'] = (int) $user['user_id']; // admins/staff/customers all key off user_id
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'reason' => null];
}

/**
 * @param string|string[] $allowedRoles One role, or several (e.g. a page
 *                                      reachable by every role such as
 *                                      change_password.php).
 */
function require_role($allowedRoles): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirect('/login.php');
    }

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT_SECONDS) {
        session_unset();
        session_destroy();
        redirect('/login.php');
    }

    if (!in_array($_SESSION['role'], (array) $allowedRoles, true)) {
        redirect(dashboard_path_for_role($_SESSION['role']));
    }

    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!empty($_SESSION['must_change_password']) && $currentPage !== 'change_password.php') {
        redirect('/change_password.php');
    }

    $_SESSION['last_activity'] = time();
}

function logout(): void
{
    session_destroy();
    redirect('/login.php');
}

function determine_role(PDO $pdo, int $userId): ?string
{
    $roleTables = ['admin' => 'admins', 'staff' => 'staff', 'customer' => 'customers'];

    foreach ($roleTables as $role => $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) {
            return $role;
        }
    }

    return null;
}

function dashboard_path_for_role(string $role): string
{
    switch ($role) {
        case 'admin':
            return '/admin/dashboard.php';
        case 'staff':
            return '/staff/dashboard.php';
        case 'customer':
            return '/customer/dashboard.php';
        default:
            return '/login.php';
    }
}
