<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.lab_id, i.name AS institute_name, l.lab_name
     FROM customers c
     JOIN labs l ON l.lab_id = c.lab_id
     JOIN institutes i ON i.institute_id = l.institute_id
     WHERE c.user_id = ?'
);
$stmt->execute([$myUserId]);
$myInfo = $stmt->fetch();
$labId = (int) $myInfo['lab_id'];

// Read the previous visit's timestamp before overwriting it with now,
// so "updated since you looked" compares against last time, not this
// page load. No prior value (first-ever visit) means there's nothing
// to compare against yet, so default to now -> nothing reads as new.
// Sourced from MySQL's NOW() rather than PHP's date(), since the two
// can run in different timezones and created_at/last_modified_at are
// DB-generated timestamps.
$now = $pdo->query('SELECT NOW()')->fetchColumn();
$lastViewedOrders = $_SESSION['last_viewed_orders'] ?? $now;
$_SESSION['last_viewed_orders'] = $now;

// "Updated" means either the order's own status/field change, or a new
// public comment landing on it -- whichever is more recent. Orders with
// no comments fall back to last_modified_at via the COALESCE.
$updatedSinceExpr = 'GREATEST(o.last_modified_at, COALESCE((SELECT MAX(opc.created_at) FROM order_public_comments opc WHERE opc.order_id = o.order_id), o.last_modified_at))';

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o
     WHERE o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)
       AND o.status = 'pending'"
);
$stmt->execute([$labId]);
$statPending = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o
     WHERE o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)
       AND o.status = 'accepted'"
);
$stmt->execute([$labId]);
$statAccepted = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o
     WHERE o.customer_id = ?
       AND o.status = 'pending'
       AND o.created_at < (NOW() - INTERVAL 48 HOUR)"
);
$stmt->execute([$myUserId]);
$statNeedsAttention = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o
     WHERE o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)
       AND {$updatedSinceExpr} > ?"
);
$stmt->execute([$labId, $lastViewedOrders]);
$statUpdated = (int) $stmt->fetchColumn();

// ------------------------------------------------------------------
// Filter options (lab-wide, unfiltered by the current query params --
// these populate the dropdowns and must stay stable as filters/pages
// change).
// ------------------------------------------------------------------

$stmt = $pdo->prepare(
    'SELECT DISTINCT iso.isotope_name
     FROM orders o
     JOIN isotopes iso ON iso.isotope_id = o.isotope_id
     WHERE o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)
     ORDER BY iso.isotope_name'
);
$stmt->execute([$labId]);
$isotopeOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare(
    'SELECT DISTINCT cust.user_id, cust.first_name, cust.last_name, u.username
     FROM orders o
     JOIN customers cust ON cust.user_id = o.customer_id
     JOIN users u ON u.user_id = o.customer_id
     WHERE o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)
       AND o.customer_id != ?
     ORDER BY cust.last_name, cust.first_name'
);
$stmt->execute([$labId, $myUserId]);
$placedByOptions = $stmt->fetchAll();
foreach ($placedByOptions as &$placedByOption) {
    $placedByOption['display_name'] = customer_display_name(
        $placedByOption['first_name'],
        $placedByOption['last_name'],
        $placedByOption['username']
    );
}
unset($placedByOption);

// ------------------------------------------------------------------
// Read + validate filters from the query string.
// ------------------------------------------------------------------

function get_string_param(string $key): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : '';
}

function is_valid_date_string(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

$allowedStatuses = ['pending', 'accepted', 'completed', 'canceled'];
$statusFilter = get_string_param('status');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$isotopeFilter = get_string_param('isotope');
if ($isotopeFilter !== '' && !in_array($isotopeFilter, $isotopeOptions, true)) {
    $isotopeFilter = '';
}

$placedByRaw = get_string_param('placed_by');
if ($placedByRaw === 'you') {
    $placedByCustomerId = $myUserId;
} elseif ($placedByRaw !== '' && ctype_digit($placedByRaw)) {
    $placedByCustomerId = (int) $placedByRaw;
} else {
    $placedByRaw = '';
    $placedByCustomerId = null;
}

$dateFrom = get_string_param('date_from');
if (!is_valid_date_string($dateFrom)) {
    $dateFrom = '';
}
$dateTo = get_string_param('date_to');
if (!is_valid_date_string($dateTo)) {
    $dateTo = '';
}

$pageRaw = get_string_param('page');
$page = ($pageRaw !== '' && ctype_digit($pageRaw)) ? (int) $pageRaw : 1;
if ($page < 1) {
    $page = 1;
}
$perPage = 25;

$filtersActive = ($statusFilter !== '' || $isotopeFilter !== '' || $placedByCustomerId !== null
    || $dateFrom !== '' || $dateTo !== '');

// ------------------------------------------------------------------
// Build the WHERE clause shared by the count query and the page query.
// "Needed" is order_type_a_details.requested_datetime for Type A, or
// order_type_b_details.eob_datetime for Type B eob-mode -- beam-mode
// Type B orders have neither, so a date filter naturally excludes them.
// ------------------------------------------------------------------

$where = ['o.customer_id IN (SELECT user_id FROM customers WHERE lab_id = ?)'];
$params = [$labId];

if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($isotopeFilter !== '') {
    $where[] = 'iso.isotope_name = ?';
    $params[] = $isotopeFilter;
}
if ($placedByCustomerId !== null) {
    $where[] = 'o.customer_id = ?';
    $params[] = $placedByCustomerId;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(COALESCE(a.requested_datetime, b.eob_datetime)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(COALESCE(a.requested_datetime, b.eob_datetime)) <= ?';
    $params[] = $dateTo;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM orders o
     JOIN isotopes iso ON iso.isotope_id = o.isotope_id
     LEFT JOIN order_type_a_details a ON a.order_id = o.order_id
     LEFT JOIN order_type_b_details b ON b.order_id = o.order_id
     WHERE {$whereSql}"
);
$stmt->execute($params);
$totalCount = (int) $stmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT
        o.order_id, o.customer_id, o.status, o.created_at, o.last_modified_at,
        cm.name AS compound_name, cm.order_type,
        iso.isotope_name,
        cust.first_name, cust.last_name, u.username,
        a.requested_datetime,
        b.mode, b.eob_datetime,
        {$updatedSinceExpr} AS last_activity_at
     FROM orders o
     JOIN compounds cm ON cm.compound_id = o.compound_id
     JOIN isotopes iso ON iso.isotope_id = o.isotope_id
     JOIN customers cust ON cust.user_id = o.customer_id
     JOIN users u ON u.user_id = o.customer_id
     LEFT JOIN order_type_a_details a ON a.order_id = o.order_id
     LEFT JOIN order_type_b_details b ON b.order_id = o.order_id
     WHERE {$whereSql}
     ORDER BY o.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Current filters, reused to build pagination links that preserve them.
$currentFilters = [
    'status' => $statusFilter,
    'isotope' => $isotopeFilter,
    'placed_by' => $placedByRaw,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];

function build_page_url(array $filters, int $page): string
{
    $params = array_filter($filters, function ($v) {
        return $v !== null && $v !== '';
    });
    if ($page > 1) {
        $params['page'] = $page;
    }
    $query = http_build_query($params);
    return $query !== '' ? '?' . $query : '';
}

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <span class="page-header__meta"><?= e($myInfo['institute_name']) ?> &middot; <?= e($myInfo['lab_name']) ?></span>
                </div>
                <div class="page-header__actions">
                    <a href="new_order.php" class="btn btn--primary">+ New order</a>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <span class="stat-card__value"><?= $statPending ?></span>
                    <span class="stat-card__label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value"><?= $statAccepted ?></span>
                    <span class="stat-card__label">Accepted</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value"><?= $statNeedsAttention ?></span>
                    <span class="stat-card__label">Needs your attention</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value"><?= $statUpdated ?></span>
                    <span class="stat-card__label">Updated since you looked</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Orders</span>
                    <form method="get" class="table-card-controls" id="filter-form">
                        <select name="status">
                            <option value="">All statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="canceled" <?= $statusFilter === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                        </select>
                        <select name="isotope">
                            <option value="">All isotopes</option>
                            <?php foreach ($isotopeOptions as $isotopeName): ?>
                                <option value="<?= e($isotopeName) ?>" <?= $isotopeFilter === $isotopeName ? 'selected' : '' ?>><?= e($isotopeName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="placed_by">
                            <option value="">All placed by</option>
                            <option value="you" <?= $placedByRaw === 'you' ? 'selected' : '' ?>>You</option>
                            <?php foreach ($placedByOptions as $opt): ?>
                                <option value="<?= (int) $opt['user_id'] ?>" <?= $placedByRaw === (string) $opt['user_id'] ? 'selected' : '' ?>><?= e($opt['display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" title="Needed from">
                        <input type="date" name="date_to" value="<?= e($dateTo) ?>" title="Needed to">
                        <button type="submit" class="btn btn--secondary btn--sm">Apply filters</button>
                        <?php if ($filtersActive): ?>
                            <a href="dashboard.php" class="btn btn--secondary btn--sm">Clear filters</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!$orders && !$filtersActive): ?>
                    <div class="table-empty">
                        No orders yet for your lab.
                        <div><a href="new_order.php" class="btn btn--primary btn--sm">+ New order</a></div>
                    </div>
                <?php elseif (!$orders && $filtersActive): ?>
                    <div class="table-empty">
                        No orders match these filters.
                        <div><a href="dashboard.php" class="btn btn--secondary btn--sm">Clear filters</a></div>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Compound</th>
                                    <th>Isotope</th>
                                    <th>Type</th>
                                    <th>Needed</th>
                                    <th>Placed by</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    if ($order['order_type'] === 'A') {
                                        $needed = $order['requested_datetime'];
                                    } elseif ($order['mode'] === 'eob') {
                                        $needed = $order['eob_datetime'];
                                    } else {
                                        $needed = null;
                                    }
                                    $isOwn = (int) $order['customer_id'] === $myUserId;
                                    $isUpdated = $order['last_activity_at'] > $lastViewedOrders;
                                    $placedByName = customer_display_name($order['first_name'], $order['last_name'], $order['username']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($isUpdated): ?><span class="updated-dot" title="Updated since you last looked"></span> <?php endif; ?>
                                            #<?= (int) $order['order_id'] ?>
                                        </td>
                                        <td><?= e($order['compound_name']) ?></td>
                                        <td><?= e($order['isotope_name']) ?></td>
                                        <td><?= e($order['order_type']) ?></td>
                                        <td><?= $needed !== null ? e(date('M j, Y g:ia', strtotime($needed))) : '&mdash;' ?></td>
                                        <td><?= $isOwn ? '<strong>You</strong>' : e($placedByName) ?></td>
                                        <td><span class="badge badge--<?= e($order['status']) ?>"><?= e($order['status']) ?></span></td>
                                        <td><a href="order_detail.php?id=<?= (int) $order['order_id'] ?>" class="table-action">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= e(build_page_url($currentFilters, $page - 1)) ?>" class="btn btn--secondary btn--sm">Previous</a>
                        <?php else: ?>
                            <span class="btn btn--secondary btn--sm" aria-disabled="true">Previous</span>
                        <?php endif; ?>
                        <span class="table-pagination__status">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= e(build_page_url($currentFilters, $page + 1)) ?>" class="btn btn--secondary btn--sm">Next</a>
                        <?php else: ?>
                            <span class="btn btn--secondary btn--sm" aria-disabled="true">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
