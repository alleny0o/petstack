<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

const CUSTOMERS_PAGE_SIZE = 20;

$q = trim($_GET['q'] ?? '');
$instituteId = $_GET['institute_id'] ?? '';
$labId = $_GET['lab_id'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$where = [];
$params = [];

if ($q !== '') {
    // Escape LIKE wildcards in the search term itself so a customer
    // searching for a literal "%" or "_" doesn't get wildcard behavior.
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\')";
    $params[] = '%' . $escaped . '%';
    $params[] = '%' . $escaped . '%';
}
if ($instituteId !== '' && ctype_digit((string) $instituteId)) {
    $where[] = 'l.institute_id = ?';
    $params[] = (int) $instituteId;
}
if ($labId !== '' && ctype_digit((string) $labId)) {
    $where[] = 'c.lab_id = ?';
    $params[] = (int) $labId;
}
if ($status === 'active') {
    $where[] = 'u.active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'u.active = 0';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM customers c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     $whereSql"
);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / CUSTOMERS_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * CUSTOMERS_PAGE_SIZE;

// LIMIT/OFFSET are interpolated directly rather than bound: both are
// fully server-computed ints at this point (page size is a constant,
// offset is derived from a clamped, ctype_digit-checked page number),
// same convention as PASSWORD_HISTORY_LIMIT in src/auth.php.
$listStmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.active,
            c.first_name, c.last_name,
            l.lab_name, i.name AS institute_name, p.pi_name
     FROM customers c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     LEFT JOIN institutes i ON i.institute_id = l.institute_id
     LEFT JOIN pis p ON p.pi_id = c.supervising_pi_id
     $whereSql
     ORDER BY c.last_name, c.first_name
     LIMIT $offset, " . CUSTOMERS_PAGE_SIZE
);
$listStmt->execute($params);
$customers = $listStmt->fetchAll();

// Filter dropdowns intentionally include inactive institutes/labs too --
// this is a search context, not data entry, and a customer can belong to
// a lab that's since gone inactive; hiding it would make them unfindable.
$institutes = $pdo->query('SELECT institute_id, name FROM institutes ORDER BY name')->fetchAll();
$labs = $pdo->query('SELECT lab_id, institute_id, lab_name FROM labs ORDER BY lab_name')->fetchAll();

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values -- used for pagination links
 * so Prev/Next carry the active filters forward.
 */
function customers_query(array $overrides = []): string
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
$rangeEnd = min($offset + CUSTOMERS_PAGE_SIZE, $totalCount);

$pageTitle = 'Customers';
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
                <h1>Customers</h1>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">All Customers</span>
                    <form method="get" class="table-card-controls">
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email&hellip;">

                        <select name="institute_id" id="filter_institute_id">
                            <option value="">All institutes</option>
                            <?php foreach ($institutes as $institute): ?>
                                <option value="<?= (int) $institute['institute_id'] ?>" <?= (string) $institute['institute_id'] === (string) $instituteId ? 'selected' : '' ?>><?= e($institute['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="lab_id" id="filter_lab_id">
                            <option value="">All labs</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= (int) $lab['lab_id'] ?>" data-institute-id="<?= (int) $lab['institute_id'] ?>" <?= (string) $lab['lab_id'] === (string) $labId ? 'selected' : '' ?>><?= e($lab['lab_name']) ?></option>
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

                <?php if (!$customers): ?>
                    <?php $hasFilters = $q !== '' || $instituteId !== '' || $labId !== '' || $status !== ''; ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="10" r="7"></circle>
                                <line x1="21" y1="21" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="empty-state__title"><?= $hasFilters ? 'No customers match these filters' : 'No customers yet' ?></div>
                        <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Customers appear here once their registration requests are approved.' ?></p>
                        <div class="empty-state__action">
                            <?php if ($hasFilters): ?>
                                <a href="/admin/customers.php" class="btn btn--secondary btn--sm">Clear filters</a>
                            <?php else: ?>
                                <a href="/admin/registrations.php" class="btn btn--primary btn--sm">Review Registrations</a>
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
                                    <th>Institute</th>
                                    <th>Lab</th>
                                    <th>PI</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                        <td><?= e($c['username']) ?></td>
                                        <td><?= e($c['institute_name'] ?? '—') ?></td>
                                        <td><?= e($c['lab_name'] ?? '—') ?></td>
                                        <td><?= e($c['pi_name'] ?? '—') ?></td>
                                        <td><span class="badge badge--<?= $c['active'] ? 'active' : 'inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td><a href="/admin/customer_detail.php?id=<?= (int) $c['user_id'] ?>" class="table-action">View</a></td>
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
                                <a href="?<?= e(customers_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                            <?php endif; ?>
                            <?php if ($page >= $totalPages): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(customers_query(['page' => $page + 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Next page">&rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<script>
(function () {
  var instituteSelect = document.getElementById('filter_institute_id');
  var labSelect = document.getElementById('filter_lab_id');
  if (!instituteSelect || !labSelect) return;

  var labOptions = Array.prototype.slice.call(labSelect.querySelectorAll('option[data-institute-id]'));

  function filterLabs() {
    var instituteId = instituteSelect.value;
    labOptions.forEach(function (opt) {
      var matches = !instituteId || opt.dataset.instituteId === instituteId;
      opt.hidden = !matches;
      opt.disabled = !matches;
    });
    if (labSelect.selectedOptions[0] && labSelect.selectedOptions[0].hidden) {
      labSelect.value = '';
    }
  }

  instituteSelect.addEventListener('change', filterLabs);
  filterLabs();
})();
</script>
</html>
