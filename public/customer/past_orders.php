<?php
<<<<<<< HEAD
<<<<<<< HEAD
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();

// Assuming your auth system stores the logged-in customer's ID here:
$customerId = 4; 
=======
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
session_start();
require __DIR__ . '/../src/demo_orders.php';

$allOrders = demo_orders();
<<<<<<< HEAD
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b

// ---------------------------------------------------------
// 1. Capture Filter Inputs
// ---------------------------------------------------------
$filterSearch    = $_GET['search'] ?? '';
$filterStatus    = $_GET['status'] ?? '';
$filterIsotope   = $_GET['isotope'] ?? '';
$filterDateStart = $_GET['date_start'] ?? '';
$filterDateEnd   = $_GET['date_end'] ?? '';
$page            = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage    = 10;

<<<<<<< HEAD
<<<<<<< HEAD
$hasAdvancedFilters = ($filterStatus !== '' || $filterIsotope !== '' || $filterDateStart !== '' || $filterDateEnd !== '');

// Get active isotopes for the dropdown directly from your new table
$stmtIso = $pdo->query("SELECT isotope_name FROM isotopes WHERE active = 1 ORDER BY isotope_name");
$uniqueIsotopes = $stmtIso->fetchAll(PDO::FETCH_COLUMN);

// ---------------------------------------------------------
// 2. Build the SQL Database Query dynamically
// ---------------------------------------------------------
$whereSql = "WHERE o.customer_id = :customer_id";
$params = [':customer_id' => $customerId];

if ($filterStatus !== '') {
    $whereSql .= " AND o.status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterIsotope !== '') {
    // Look at the compounds table for the isotope instead of orders
    $whereSql .= " AND c.isotope_name = :isotope";
    $params[':isotope'] = $filterIsotope;
}
if ($filterDateStart !== '') {
    $whereSql .= " AND DATE(o.created_at) >= :date_start";
    $params[':date_start'] = $filterDateStart;
}
if ($filterDateEnd !== '') {
    $whereSql .= " AND DATE(o.created_at) <= :date_end";
    $params[':date_end'] = $filterDateEnd;
}
if ($filterSearch !== '') {
    $whereSql .= " AND (o.order_id LIKE :search OR c.name LIKE :search)";
    $params[':search'] = "%{$filterSearch}%";
}

// ---------------------------------------------------------
// 3. Execute Pagination & Fetch Logic
// ---------------------------------------------------------
// Count total items
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o 
    LEFT JOIN compounds c ON o.compound_id = c.compound_id 
    $whereSql
");
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();

// Calculate pagination
$totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
$page = min($page, $totalPages); 
$offset = ($page - 1) * $itemsPerPage;

// Fetch the actual records 
$query = "
    SELECT 
        o.order_id, 
        o.status, 
        o.delivery_time, 
        c.name AS compound_name,
        c.isotope_name AS isotope
    FROM orders o
    LEFT JOIN compounds c ON o.compound_id = c.compound_id
    $whereSql 
    ORDER BY o.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind standard params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
// LIMIT and OFFSET must be bound as integers
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$paginatedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
=======
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
// Determine if the advanced drawer should be open on load
$hasAdvancedFilters = ($filterStatus !== '' || $filterIsotope !== '' || $filterDateStart !== '' || $filterDateEnd !== '');

$uniqueIsotopes = array_unique(array_column($allOrders, 'isotope'));
sort($uniqueIsotopes);

// ---------------------------------------------------------
// 2. Apply Filters
// ---------------------------------------------------------
$filteredOrders = array_filter($allOrders, function($o) use ($filterSearch, $filterStatus, $filterIsotope, $filterDateStart, $filterDateEnd) {
    // 1. Search (ID or Compound)
    $matchesSearch = $filterSearch === '' || 
                     stripos($o['id'], $filterSearch) !== false || 
                     stripos($o['compound'], $filterSearch) !== false;
    
    // 2. Status & Isotope
    $matchesStatus = $filterStatus === '' || $o['status'] === $filterStatus;
    $matchesIsotope = $filterIsotope === '' || strcasecmp($o['isotope'], $filterIsotope) === 0;

    // 3. Date Range Logic
    $matchesDate = true;
    // Fallback through your available date keys to find the timestamp
    $orderDateRaw = $o['placed_at'] ?? $o['requested'] ?? $o['b_datetime'] ?? null;
    
    if ($orderDateRaw) {
        // Extract just the YYYY-MM-DD part for a clean comparison
        $dateOnly = substr($orderDateRaw, 0, 10);
        
        if ($filterDateStart !== '' && $dateOnly < $filterDateStart) {
            $matchesDate = false;
        }
        if ($filterDateEnd !== '' && $dateOnly > $filterDateEnd) {
            $matchesDate = false;
        }
    }

    return $matchesSearch && $matchesStatus && $matchesIsotope && $matchesDate;
});

// ---------------------------------------------------------
// 3. Pagination Logic
// ---------------------------------------------------------
$totalItems = count($filteredOrders);
$totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
$page = min($page, $totalPages); 

$offset = ($page - 1) * $itemsPerPage;
$paginatedOrders = array_slice($filteredOrders, $offset, $itemsPerPage);
<<<<<<< HEAD
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b

function buildUrl($pageUpdate) {
    $params = $_GET;
    $params['page'] = $pageUpdate;
    return '?' . http_build_query($params);
}
?>

<<<<<<< HEAD
<<<<<<< HEAD
=======

>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======

>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
<!DOCTYPE html>
<html lang="en">

<head>
<<<<<<< HEAD
<<<<<<< HEAD
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
=======
    <?php $pageTitle = 'Past Orders'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
    <?php $pageTitle = 'Past Orders'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
</head>

<body>

    <div class="app-shell">
<<<<<<< HEAD
<<<<<<< HEAD
        <?php include __DIR__ . '/../../src/partials/layout_customer.php'; ?>
=======
        <?php include '../src/partials/layout_customer.php'; ?>
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
        <?php include '../src/partials/layout_customer.php'; ?>
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b

        <main class="app-main">

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">Order History</h1>
                    <span class="text-sm muted">[INST] &middot; [LAB]</span>
                </div>
            </div>

            <div class="table-card" style="margin-top: var(--sp-6);">
                <div class="table-card-header" style="flex-direction: column; align-items: stretch; gap: 10px;">
                    <span class="table-card-title mb-0">All Orders (<?= $totalItems ?>)</span>
                    
<<<<<<< HEAD
<<<<<<< HEAD
                    <form method="GET" action="past_orders.php" id="filter-form">
=======
                    <form method="GET" action="customer_past_orders.php" id="filter-form">
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
                    <form method="GET" action="customer_past_orders.php" id="filter-form">
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
                        <input type="hidden" name="page" value="1"> 
                        
                        <div class="search-bar-top">
                            <input type="text" name="search" placeholder="Search Order # or compound…" value="<?= htmlspecialchars($filterSearch) ?>">
                            
                            <button type="submit" class="btn btn--primary">Search</button>

                            <button type="button" class="btn btn--secondary" id="toggle-advanced-search">
                                Advanced
                            </button>
                            
                            <?php if ($filterSearch !== '' || $hasAdvancedFilters): ?>
<<<<<<< HEAD
                                <a href="past_orders.php" class="btn btn--secondary">Clear</a>
=======
                                <a href="customer_past_orders.php" class="btn btn--secondary">Clear</a>
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
                            <?php endif; ?>
                        </div>

                        <div id="advanced-filters" class="advanced-filters <?= $hasAdvancedFilters ? 'is-open' : '' ?>">
                            
                            <div class="filter-group">
                                <label for="filter-date-start">From Date</label>
                                <input type="date" name="date_start" id="filter-date-start" value="<?= htmlspecialchars($filterDateStart) ?>" onchange="this.form.submit()">
                            </div>

                            <div class="filter-group">
                                <label for="filter-date-end">To Date</label>
                                <input type="date" name="date_end" id="filter-date-end" value="<?= htmlspecialchars($filterDateEnd) ?>" onchange="this.form.submit()">
                            </div>

                            <div class="filter-group">
                                <label for="filter-isotope">Isotope</label>
                                <select name="isotope" id="filter-isotope" onchange="this.form.submit()">
                                    <option value="">All Isotopes</option>
                                    <?php foreach ($uniqueIsotopes as $iso): ?>
                                        <option value="<?= htmlspecialchars($iso) ?>" <?= $filterIsotope === $iso ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($iso) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter-status">Status</label>
                                <select name="status" id="filter-status" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="accepted" <?= $filterStatus === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="canceled" <?= $filterStatus === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="table-scroll">
                    <table class="table" id="orders-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Compound</th>
                                <th>Isotope</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paginatedOrders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;" class="muted">No orders match your filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paginatedOrders as $o): ?>
                                    <tr>
<<<<<<< HEAD
<<<<<<< HEAD
                                        <td class="muted tabular"><?= htmlspecialchars($o['order_id']) ?></td>
                                        <td><?= htmlspecialchars($o['compound_name'] ?? 'Unknown') ?></td>
                                        <td class="muted"><?= htmlspecialchars($o['isotope'] ?? 'Unknown') ?></td>
                                        <td class="muted tabular"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($o['delivery_time']))) ?></td>
                                        <td><span class="badge badge--<?= htmlspecialchars($o['status']) ?>"><?= ucfirst(htmlspecialchars($o['status'])) ?></span></td>
                                        <td><a href="order_detail.php?id=<?= $o['order_id'] ?>" class="table-action">View →</a></td>
=======
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
                                        <td class="muted tabular"><?= htmlspecialchars($o['id']) ?></td>
                                        <td><?= htmlspecialchars($o['compound']) ?></td>
                                        <td class="muted"><?= htmlspecialchars($o['isotope']) ?></td>
                                        <td class="muted tabular"><?= htmlspecialchars($o['requested'] ?? $o['b_datetime'] ?? '—') ?></td>
                                        <td><span class="badge badge--<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                        <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="table-action">View →</a></td>
<<<<<<< HEAD
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="past-orders-pagination-controls">
                    <div style="display: flex; gap: 8px;">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl($page - 1) ?>" class="btn btn--secondary" title="Previous Page">← Prev</a>
                        <?php else: ?>
                            <button class="btn btn--disabled" disabled>← Prev</button>
                        <?php endif; ?>
                    </div>
                    
                    <span class="text-sm muted">Page <?= $page ?> of <?= $totalPages ?></span>
                    
                    <div style="display: flex; gap: 8px;">
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= buildUrl($page + 1) ?>" class="btn btn--secondary" title="Next Page">Next →</a>
                        <?php else: ?>
                            <button class="btn btn--disabled" disabled>Next →</button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>

</body>

<<<<<<< HEAD
<<<<<<< HEAD
<script src="/assets/js/script.js" defer></script>
=======
<script src="assets/js/script.js" defer></script>
>>>>>>> f1a9c500e83897e6bbb28035eec951eae49bc042
=======
<script src="assets/js/script.js" defer></script>
>>>>>>> 1b7dc665cd30e229661305c70df77e57d53e758b
<script>
    // Toggle the advanced filter drawer open/closed
    document.getElementById('toggle-advanced-search').addEventListener('click', function() {
        document.getElementById('advanced-filters').classList.toggle('is-open');
    });
</script>

</html>