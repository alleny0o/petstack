<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

const CUSTOMERS_DEFAULT_PAGE_SIZE = 20;
const CUSTOMERS_PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

$q = trim($_GET['q'] ?? '');
$instituteId = $_GET['institute_id'] ?? '';
$labId = $_GET['lab_id'] ?? '';
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageSize = in_array((int) ($_GET['page_size'] ?? 0), CUSTOMERS_PAGE_SIZE_OPTIONS, true)
    ? (int) $_GET['page_size'] : CUSTOMERS_DEFAULT_PAGE_SIZE;

// Canonicalize so every link built via customers_query() below (tabs,
// pagination) carries the real applied values forward -- same convention
// as staff/orders.php's status/page_size canonicalization.
$_GET['status'] = $status;
$_GET['page_size'] = (string) $pageSize;

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values -- used for the status tabs
 * and pagination links so Prev/Next/Go carry the active filters forward.
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

$where = [];
$params = [];

if ($q !== '') {
    // Escape LIKE wildcards in the search term itself so a customer
    // searching for a literal "%" or "_" doesn't get wildcard behavior.
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\')";
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

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Built without the status condition -- reused for the tab counts (each
// tab's count reflects the current search/institute/lab scope, not
// global counts) and then extended with a status condition below for the
// actual list -- same pattern as staff/orders.php's $queueStatusCounts.
$countsStmt = $pdo->prepare(
    "SELECT u.active, COUNT(*) AS c
     FROM customers c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     $whereSql
     GROUP BY u.active"
);
$countsStmt->execute($params);
$statusCounts = ['active' => 0, 'inactive' => 0];
foreach ($countsStmt->fetchAll() as $row) {
    $statusCounts[$row['active'] ? 'active' : 'inactive'] = (int) $row['c'];
}
$allCount = $statusCounts['active'] + $statusCounts['inactive'];
$totalCount = $status !== '' ? $statusCounts[$status] : $allCount;

$statusTabs = [
    ['value' => '',         'label' => 'All',      'count' => $allCount],
    ['value' => 'active',   'label' => 'Active',   'count' => $statusCounts['active']],
    ['value' => 'inactive', 'label' => 'Inactive', 'count' => $statusCounts['inactive']],
];

$listWhere = $where;
$listParams = $params;
if ($status === 'active') {
    $listWhere[] = 'u.active = 1';
} elseif ($status === 'inactive') {
    $listWhere[] = 'u.active = 0';
}
$listWhereSql = $listWhere ? ('WHERE ' . implode(' AND ', $listWhere)) : '';

$totalPages = max(1, (int) ceil($totalCount / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

// LIMIT/OFFSET are interpolated directly rather than bound: both are
// fully server-computed ints at this point (page size is clamped against
// a fixed option set, offset is derived from a clamped, ctype_digit-checked
// page number), same convention as PASSWORD_HISTORY_LIMIT in src/auth.php.
$listStmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.active,
            u.first_name, u.last_name,
            l.lab_name, i.name AS institute_name, p.pi_name
     FROM customers c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     LEFT JOIN institutes i ON i.institute_id = l.institute_id
     LEFT JOIN pis p ON p.pi_id = c.supervising_pi_id
     $listWhereSql
     ORDER BY u.last_name, u.first_name
     LIMIT $offset, $pageSize"
);
$listStmt->execute($listParams);
$customers = $listStmt->fetchAll();

// Filter dropdowns intentionally include inactive institutes/labs too --
// this is a search context, not data entry, and a customer can belong to
// a lab that's since gone inactive; hiding it would make them unfindable.
$institutes = $pdo->query('SELECT institute_id, name FROM institutes ORDER BY name')->fetchAll();
$labs = $pdo->query('SELECT lab_id, institute_id, lab_name FROM labs ORDER BY lab_name')->fetchAll();

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $pageSize, $totalCount);
$hasFilters = $q !== '' || $instituteId !== '' || $labId !== '' || $status !== '';

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

            <nav class="status-tabs" aria-label="Filter by status">
                <?php foreach ($statusTabs as $tab): ?>
                    <a href="?<?= e(customers_query(['status' => $tab['value'], 'page' => 1])) ?>" class="status-tabs__link <?= $status === $tab['value'] ? 'is-active' : '' ?>">
                        <?= e($tab['label']) ?> <span class="status-tabs__count"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">All Customers</span>
                    <?php // Status is no longer a field here -- the tabs above
                          // are the status filter now, same as staff/orders.php. ?>
                    <form method="get" class="table-card-controls">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">

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

                        <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
                    </form>
                </div>

                <?php if (!$customers): ?>
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
                        <div class="table-pagination__status-group">
                            <span class="table-pagination__status">Showing <?= $rangeStart ?>&ndash;<?= $rangeEnd ?> of <?= $totalCount ?></span>
                            <form method="get" class="table-card-controls">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input type="hidden" name="institute_id" value="<?= e((string) $instituteId) ?>">
                                <input type="hidden" name="lab_id" value="<?= e((string) $labId) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input type="hidden" name="page" value="1">
                                <label for="customers-page-size" class="sr-only">Customers per page</label>
                                <select name="page_size" id="customers-page-size" onchange="this.form.submit()">
                                    <?php foreach (CUSTOMERS_PAGE_SIZE_OPTIONS as $option): ?>
                                        <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="table-pagination__controls">
                            <?php if ($page <= 1): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(customers_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                            <?php endif; ?>
                            <form method="get" class="table-card-controls table-pagination__jump">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input type="hidden" name="institute_id" value="<?= e((string) $instituteId) ?>">
                                <input type="hidden" name="lab_id" value="<?= e((string) $labId) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">
                                <label for="customers-page-jump" class="sr-only">Go to page</label>
                                <input type="number" name="page" id="customers-page-jump" min="1" max="<?= $totalPages ?>" value="<?= $page ?>">
                                <span class="table-pagination__status">of <?= $totalPages ?></span>
                                <button type="submit" class="btn btn--secondary btn--sm">Go</button>
                            </form>
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
<script src="<?= asset_url('/assets/js/script.js') ?>" defer></script>
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
