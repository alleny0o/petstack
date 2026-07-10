<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

/**
 * Single-use temp password: doesn't need to satisfy the full strength
 * policy (validate_password_strength()) since it's never kept -- the
 * account is forced to change it on first login. Same helper as
 * registrations.php / customer_detail.php; not shared out to
 * src/helpers.php for three call sites.
 */
function generate_temp_password(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
}

/**
 * The Administration category exists solely as a cosmetic placeholder
 * for staff.category_id on admin accounts (NOT NULL constraint, but
 * admins bypass category restrictions in the app) -- it's never a real
 * work category, so it's excluded from the staff category edit dropdown.
 */
function administration_category_id(PDO $pdo): int
{
    return (int) $pdo->query("SELECT category_id FROM categories WHERE category_name = 'Administration'")->fetchColumn();
}

function fetch_account(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, u.active, u.created_at,
                cat.category_id, cat.category_name,
                (a.user_id IS NOT NULL) AS is_admin
         FROM staff s
         JOIN users u ON u.user_id = s.user_id
         JOIN categories cat ON cat.category_id = s.category_id
         LEFT JOIN admins a ON a.user_id = s.user_id
         WHERE s.user_id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

$userId = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
$account = $userId > 0 ? fetch_account($pdo, $userId) : null;
$isSelf = $account !== null && $userId === (int) $_SESSION['user_id'];

$flash = null;
$categoryError = '';
$tempPasswordReveal = null;

if ($account !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'edit_category' && !$account['is_admin']) {
        $newCategoryId = trim((string) ($_POST['category_id'] ?? ''));

        if ($newCategoryId === '' || !ctype_digit($newCategoryId)) {
            $categoryError = 'Select a category.';
        } elseif ((int) $newCategoryId === administration_category_id($pdo)) {
            $categoryError = 'Administration is reserved for admin accounts.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ?');
            $stmt->execute([$newCategoryId]);
            if (!$stmt->fetchColumn()) {
                $categoryError = 'Select a valid category.';
            }
        }

        if ($categoryError === '') {
            $pdo->prepare('UPDATE staff SET category_id = ? WHERE user_id = ?')
                ->execute([(int) $newCategoryId, $userId]);
            $account = fetch_account($pdo, $userId);
            $flash = ['type' => 'success', 'message' => 'Category updated.'];
        }
    } elseif ($action === 'toggle_active') {
        if ($isSelf && $account['active']) {
            $flash = ['type' => 'error', 'message' => 'You cannot deactivate your own account.'];
        } else {
            $newActive = $account['active'] ? 0 : 1;

            if ($newActive === 0 && $account['is_admin']) {
                // Last-admin protection: never allow a deactivation that
                // would leave zero active admins. Checked inside a
                // transaction with FOR UPDATE so two admins deactivating
                // each other concurrently can't both slip past the count.
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM admins a
                     JOIN users u ON u.user_id = a.user_id
                     WHERE u.active = 1 AND u.user_id <> ?
                     FOR UPDATE'
                );
                $stmt->execute([$userId]);
                $otherActiveAdmins = (int) $stmt->fetchColumn();

                if ($otherActiveAdmins === 0) {
                    $pdo->rollBack();
                    $flash = ['type' => 'error', 'message' => 'Cannot deactivate the last active admin account.'];
                } else {
                    $pdo->prepare('UPDATE users SET active = 0 WHERE user_id = ?')->execute([$userId]);
                    $pdo->commit();
                    $account = fetch_account($pdo, $userId);
                    $flash = ['type' => 'success', 'message' => 'Account deactivated. They have been signed out and can no longer log in.'];
                }
            } else {
                $pdo->prepare('UPDATE users SET active = ? WHERE user_id = ?')->execute([$newActive, $userId]);
                $account = fetch_account($pdo, $userId);
                $flash = [
                    'type' => 'success',
                    'message' => $newActive
                        ? 'Account reactivated.'
                        : 'Account deactivated. They have been signed out and can no longer log in.',
                ];
            }
        }
    } elseif ($action === 'reset_password') {
        if ($isSelf) {
            // Resetting your own password while logged in has no
            // legitimate use (use Change Password instead) and is a
            // lockout foot-gun for the sole admin -- the session's
            // must_change_password flag stays stale, so nothing visibly
            // changes while your real password is already gone.
            $flash = ['type' => 'error', 'message' => 'You cannot reset your own password here. Use Change Password instead.'];
        } else {
            $tempPassword = generate_temp_password();
            $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Archive the outgoing hash so the pre-reset password still
            // counts toward the last-5 reuse check on the forced change.
            // The temp itself needs no history row: is_password_reused()
            // already rejects it via the current users.password_hash.
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
            $stmt->execute([$userId]);
            $outgoingHash = (string) $stmt->fetchColumn();

            $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?')
                ->execute([$tempHash, $userId]);

            record_password_history($pdo, $userId, $outgoingHash);

            $tempPasswordReveal = $tempPassword;
        }
    }
}

// Dropdown excludes Administration -- reserved for the cosmetic admin
// case, never a user-picked value for a staff account. Includes the
// account's current category even if it were somehow Administration
// already, so the form never silently drops the current value.
$categories = $pdo->query(
    "SELECT category_id, category_name FROM categories WHERE category_name != 'Administration' ORDER BY category_name"
)->fetchAll();

$pageTitle = $account !== null ? $account['username'] : 'Account not found';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            <?php if ($account === null): ?>
                <?php http_response_code(404); ?>
                <div class="page-header">
                    <h1>Account not found</h1>
                </div>
                <div class="card">
                    <p class="muted">This account doesn't exist.</p>
                    <a href="/admin/accounts.php" class="btn btn--secondary">Back to Accounts</a>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div>
                        <a href="/admin/accounts.php" class="page-header__back mb-4">&larr; Back to Accounts</a>
                        <span class="badge badge--<?= $account['active'] ? 'active' : 'inactive' ?> page-header__status"><?= $account['active'] ? 'Active' : 'Inactive' ?></span>
                        <span class="badge badge--role-<?= $account['is_admin'] ? 'admin' : 'staff' ?> page-header__status"><?= $account['is_admin'] ? 'Admin' : 'Staff' ?></span>
                        <h1><?= e($account['username']) ?></h1>
                    </div>
                </div>

                <?php if ($flash && $flash['type'] === 'success'): ?>
                    <?= toast_flash('success', $flash['message']) ?>
                <?php elseif ($flash): ?>
                    <div class="alert alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($tempPasswordReveal !== null): ?>
                    <div class="temp-password-banner">
                        <div class="temp-password-banner__heading">Temporary password generated</div>
                        <div>Give this to <?= e($account['username']) ?> via NIH email &mdash; it will not be shown again.</div>
                        <div class="temp-password-banner__row">
                            <span class="temp-password-banner__password" id="temp-password-value"><?= e($tempPasswordReveal) ?></span>
                            <button type="button" class="btn btn--secondary btn--sm" data-copy-target="#temp-password-value">Copy</button>
                        </div>
                        <div class="temp-password-banner__warning">Save this now. Leaving or refreshing this page will not bring it back.</div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <span class="card__title">Account</span>
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Email (username)</span>
                            <span class="detail-list__value"><?= e($account['username']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Role</span>
                            <span class="detail-list__value"><?= $account['is_admin'] ? 'Admin' : 'Staff' ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Category</span>
                            <span class="detail-list__value"><?= e($account['category_name']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Created</span>
                            <span class="detail-list__value"><?= e(date('M j, Y g:i A', strtotime($account['created_at']))) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Account status</span>
                            <span class="detail-list__value"><?= $account['active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$account['is_admin']): ?>
                    <div class="card">
                        <span class="card__title">Category</span>
                        <form method="post" action="/admin/account_detail.php?id=<?= (int) $userId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="edit_category">

                            <div class="field mb-0<?= $categoryError !== '' ? ' field--invalid' : '' ?>">
                                <label for="category_id">Category <span class="required-mark">*</span></label>
                                <select id="category_id" name="category_id">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $account['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($categoryError !== ''): ?>
                                    <span class="field-error"><?= e($categoryError) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-section">
                                <button type="submit" class="btn btn--primary">Save Category</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <span class="card__title">Account Actions</span>
                    <div class="flex gap-3">
                        <?php if ($isSelf && $account['active']): ?>
                            <button type="button" class="btn btn--danger" disabled title="You cannot deactivate your own account.">Deactivate Account</button>
                        <?php elseif ($account['active']): ?>
                            <form method="post" action="/admin/account_detail.php?id=<?= (int) $userId ?>"
                                  data-confirm="Deactivate <?= e($account['username']) ?>? They will be signed out immediately and unable to log in."
                                  data-confirm-title="Deactivate account"
                                  data-confirm-verb="Deactivate"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn btn--danger">Deactivate Account</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/admin/account_detail.php?id=<?= (int) $userId ?>"
                                  data-confirm="Reactivate <?= e($account['username']) ?>? They will be able to log in again."
                                  data-confirm-title="Reactivate account"
                                  data-confirm-verb="Reactivate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn btn--secondary">Reactivate Account</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isSelf): ?>
                            <button type="button" class="btn btn--secondary" disabled title="You cannot reset your own password here. Use Change Password instead.">Reset Password</button>
                        <?php else: ?>
                            <form method="post" action="/admin/account_detail.php?id=<?= (int) $userId ?>"
                                  data-confirm="Generate a new temporary password for <?= e($account['username']) ?>? Their current password will stop working immediately."
                                  data-confirm-title="Reset password"
                                  data-confirm-verb="Reset password"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" class="btn btn--secondary">Reset Password</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($isSelf): ?>
                        <p class="field-hint mt-2 mb-0">This is your own account &mdash; deactivation is blocked, and password changes go through <a href="/change_password.php">Change Password</a>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
