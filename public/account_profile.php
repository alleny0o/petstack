<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/auth.php';
require_role(['staff', 'admin', 'customer']);

/**
 * Same-origin path only: exactly one leading '/', never '//...'
 * (protocol-relative, which browsers treat as a scheme-relative URL to
 * another host) and no scheme before it. Used so the sidebar's profile
 * modal can bounce the user back to whatever page it was opened from
 * without trusting client-supplied input as a raw redirect target.
 */
function local_redirect_target(string $candidate, string $fallback): string
{
    if ($candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//')) {
        return $fallback;
    }
    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $target = local_redirect_target((string) ($_POST['redirect_to'] ?? ''), dashboard_path_for_role($_SESSION['role']));
    $sep = str_contains($target, '?') ? '&' : '?';

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');

    if ($firstName === '' || $lastName === '') {
        redirect($target . $sep . 'profile_error=1');
    }

    // Customers and staff/admins live in separate tables (see CLAUDE.md
    // Roles) -- $_SESSION['role'] is session-derived, never request input,
    // so this is a closed two-way choice, not an injection surface.
    $table = $_SESSION['role'] === 'customer' ? 'customers' : 'staff';
    get_db()->prepare("UPDATE {$table} SET first_name = ?, last_name = ? WHERE user_id = ?")
        ->execute([$firstName, $lastName, (int) $_SESSION['user_id']]);

    redirect($target . $sep . 'profile_updated=1');
}

redirect(dashboard_path_for_role($_SESSION['role']));
