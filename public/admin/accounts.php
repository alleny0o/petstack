<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

const ACCOUNTS_PAGE_SIZE = 20;

$q = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? '';
$categoryId = $_GET['category_id'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$where = [];
$params = [];

if ($q !== '') {
    // Escape LIKE wildcards in the search term itself, same convention
    // as customers.php -- matches either the staff member's name or
    // their username (email).
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\')";
    $params[] = '%' . $escaped . '%';
    $params[] = '%' . $escaped . '%';
}
if ($role === 'staff') {
    $where[] = 'a.user_id IS NULL';
} elseif ($role === 'admin') {
    $where[] = 'a.user_id IS NOT NULL';
}
if ($categoryId !== '' && ctype_digit((string) $categoryId)) {
    $where[] = 'cat.category_id = ?';
    $params[] = (int) $categoryId;
}
if ($status === 'active') {
    $where[] = 'u.active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'u.active = 0';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM staff s
     JOIN users u ON u.user_id = s.user_id
     JOIN categories cat ON cat.category_id = s.category_id
     LEFT JOIN admins a ON a.user_id = s.user_id
     $whereSql"
);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / ACCOUNTS_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * ACCOUNTS_PAGE_SIZE;

// LIMIT/OFFSET are interpolated directly rather than bound: both are
// fully server-computed ints at this point (page size is a constant,
// offset is derived from a clamped, ctype_digit-checked page number),
// same convention as customers.php.
$listStmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.active,
            s.first_name, s.last_name,
            cat.category_id, cat.category_name,
            (a.user_id IS NOT NULL) AS is_admin
     FROM staff s
     JOIN users u ON u.user_id = s.user_id
     JOIN categories cat ON cat.category_id = s.category_id
     LEFT JOIN admins a ON a.user_id = s.user_id
     $whereSql
     ORDER BY u.username
     LIMIT $offset, " . ACCOUNTS_PAGE_SIZE
);
$listStmt->execute($params);
$accounts = $listStmt->fetchAll();

// Filter dropdown includes every category (including the cosmetic
// Administration one) -- this is a search context, and filtering the
// list down to admins by category is a legitimate use even though
// Administration isn't a choice on the create/edit forms.
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values -- used for pagination links
 * so Prev/Next carry the active filters forward.
 */
function accounts_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + ACCOUNTS_PAGE_SIZE, $totalCount);

$pageTitle = 'Accounts';
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
            <div class="page-header">
                <h1>Accounts</h1>
                <div class="page-header__actions">
                    <a href="/admin/account_create.php" class="btn btn--primary">New Account</a>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Staff &amp; Admins</span>
                    <form method="get" class="table-card-controls">
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search email&hellip;">

                        <select name="role">
                            <option value="">All roles</option>
                            <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff only</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin only</option>
                        </select>

                        <select name="category_id">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>" <?= (string) $category['category_id'] === (string) $categoryId ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status">
                            <option value="">Active &amp; inactive</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
                        </select>

                        <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
                    </form>
                </div>

                <?php if (!$accounts): ?>
                    <?php $hasFilters = $q !== '' || $role !== '' || $categoryId !== '' || $status !== ''; ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="10" r="7"></circle>
                                <line x1="21" y1="21" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="empty-state__title"><?= $hasFilters ? 'No accounts match these filters' : 'No staff or admin accounts yet' ?></div>
                        <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Create the first staff or admin account to get started.' ?></p>
                        <div class="empty-state__action">
                            <?php if ($hasFilters): ?>
                                <a href="/admin/accounts.php" class="btn btn--secondary btn--sm">Clear filters</a>
                            <?php else: ?>
                                <a href="/admin/account_create.php" class="btn btn--primary btn--sm">New Account</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td><?= e($acc['first_name'] . ' ' . $acc['last_name']) ?></td>
                                        <td><?= e($acc['username']) ?></td>
                                        <td><span class="badge badge--role-<?= $acc['is_admin'] ? 'admin' : 'staff' ?>"><?= $acc['is_admin'] ? 'Admin' : 'Staff' ?></span></td>
                                        <td><?= e($acc['category_name']) ?></td>
                                        <td><span class="badge badge--<?= $acc['active'] ? 'active' : 'inactive' ?>"><?= $acc['active'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td><a href="/admin/account_detail.php?id=<?= (int) $acc['user_id'] ?>" class="table-action">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-pagination">
                        <span class="table-pagination__status">Showing <?= $rangeStart ?>&ndash;<?= $rangeEnd ?> of <?= $totalCount ?></span>
                        <div class="table-pagination__controls">
                            <span class="table-pagination__status">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page <= 1): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(accounts_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                            <?php endif; ?>
                            <?php if ($page >= $totalPages): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(accounts_query(['page' => $page + 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Next page">&rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
