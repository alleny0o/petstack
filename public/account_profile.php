<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/auth.php';
// Customer self-service profile editing was removed (profile changes are
// admin-only now, via admin/customer_detail.php) -- staff/admin keep
// self-editing through their own sidebar modal, so 'customer' is
// deliberately absent here, not just missing a UI trigger.
require_role(['staff', 'admin']);

/**
 * Same-origin path only: exactly one leading '/', never '//...'
 * (protocol-relative, which browsers treat as a scheme-relative URL to
 * another host) and no scheme before it. Used so the sidebar's profile
 * modal can bounce the user back to whatever page it was opened from
 * without trusting client-supplied input as a raw redirect target.
 */
function local_redirect_target(string $candidate, string $fallback): string
{
    if ($candidate === '' || $candidate[0] !== '/' || strpos($candidate, '//') === 0 || strpos($candidate, '/\\') === 0) {
        return $fallback;
    }
    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $target = local_redirect_target((string) ($_POST['redirect_to'] ?? ''), dashboard_path_for_role($_SESSION['role']));
    $sep = strpos($target, '?') !== false ? '&' : '?';

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($firstName === '' || $lastName === ''
        || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
        redirect($target . $sep . 'profile_error=1');
    }
    if ($phone !== '' && (!preg_match('/^[0-9()+.\-\s]+$/', $phone) || !preg_match('/[0-9]/', $phone) || mb_strlen($phone) > 20)) {
        redirect($target . $sep . 'profile_error=1');
    }

    get_db()->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?')
        ->execute([$firstName, $lastName, $phone !== '' ? $phone : null, (int) $_SESSION['user_id']]);

    redirect($target . $sep . 'profile_updated=1');
}

redirect(dashboard_path_for_role($_SESSION['role']));
