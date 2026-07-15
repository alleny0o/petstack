<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();

// Assuming your auth system stores the logged-in customer's ID here:
$customerId = $_SESSION['user_id'] ?? 1; 

// ---------------------------------------------------------
// 1. Capture Filter Inputs
// ---------------------------------------------------------
$filterSearch    = $_GET['search'] ?? '';
$filterStatus    = $_GET['status'] ?? '';
$filterNuclide   = $_GET['nuclide'] ?? '';
$filterDateStart = $_GET['date_start'] ?? '';
$filterDateEnd   = $_GET['date_end'] ?? '';
$page            = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage    = 10;

$hasAdvancedFilters = ($filterStatus !== '' || $filterNuclide !== '' || $filterDateStart !== '' || $filterDateEnd !== '');

// Get active nuclides for the dropdown directly from your new table
$stmtNuclide = $pdo->query("SELECT nuclide_name FROM nuclides WHERE active = 1 ORDER BY nuclide_name");
$uniqueNuclides = $stmtNuclide->fetchAll(PDO::FETCH_COLUMN);

// ---------------------------------------------------------
// 2. Build the SQL Database Query dynamically
// ---------------------------------------------------------
$whereSql = "WHERE o.customer_id = :customer_id";
$params = [':customer_id' => $customerId];

if ($filterStatus !== '') {
    $whereSql .= " AND o.status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterNuclide !== '') {
    // Look at the products table for the nuclide instead of orders
    $whereSql .= " AND p.nuclide_name = :nuclide";
    $params[':nuclide'] = $filterNuclide;
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
    // Check if search matches order_id OR the product name
    $whereSql .= " AND (o.order_id LIKE :search OR p.name LIKE :search)";
    $params[':search'] = "%{$filterSearch}%";
}

// ---------------------------------------------------------
// 3. Execute Pagination & Fetch Logic
// ---------------------------------------------------------
// Count total items
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o 
    LEFT JOIN products p ON o.product_id = p.product_id 
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
        p.name AS product_name,
        p.nuclide_name AS nuclide
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.product_id
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

function buildUrl($pageUpdate) {
    $params = $_GET;
    $params['page'] = $pageUpdate;
    return '?' . http_build_query($params);
}
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

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">Order History</h1>
                    <span class="text-sm muted">[INST] &middot; [LAB]</span>
                </div>
            </div>

            <div class="table-card" style="margin-top: var(--sp-6);">
                <div class="table-card-header" style="flex-direction: column; align-items: stretch; gap: 10px;">
                    <span class="table-card-title mb-0">All Orders (<?= $totalItems ?>)</span>
                    
                    <form method="GET" action="customer_past_orders.php" id="filter-form">
                        <input type="hidden" name="page" value="1"> 
                        
                        <div class="search-bar-top">
                            <input type="text" name="search" placeholder="Search Order # or product…" value="<?= htmlspecialchars($filterSearch) ?>">
                            
                            <button type="submit" class="btn btn--primary">Search</button>

                            <button type="button" class="btn btn--secondary" id="toggle-advanced-search">
                                Advanced
                            </button>
                            
                            <?php if ($filterSearch !== '' || $hasAdvancedFilters): ?>
                                <a href="customer_past_orders.php" class="btn btn--secondary">Clear</a>
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
                                <label for="filter-nuclide">Nuclide</label>
                                <select name="nuclide" id="filter-nuclide" onchange="this.form.submit()">
                                    <option value="">All Nuclides</option>
                                    <?php foreach ($uniqueNuclides as $nuc): ?>
                                        <option value="<?= htmlspecialchars($nuc) ?>" <?= $filterNuclide === $nuc ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nuc) ?>
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
                                    <option value="ready for pickup" <?= $filterStatus === 'ready for pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                                    <option value="returned" <?= $filterStatus === 'returned' ? 'selected' : '' ?>>Returned</option>
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
                                <th>Product</th>
                                <th>Nuclide</th>
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
                                    <?php 
                                        $displayStatus = ucwords(htmlspecialchars($o['status']));
                                        $cssStatusClass = str_replace(' ', '-', htmlspecialchars($o['status']));
                                    ?>
                                    <tr>
                                        <td class="muted tabular"><?= htmlspecialchars($o['order_id']) ?></td>
                                        <td><?= htmlspecialchars($o['product_name'] ?? 'Unknown') ?></td>
                                        <td class="muted"><?= htmlspecialchars($o['nuclide'] ?? 'Unknown') ?></td>
                                        <td class="muted tabular"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($o['delivery_time']))) ?></td>
                                        <td><span class="badge badge--<?= $cssStatusClass ?>"><?= $displayStatus ?></span></td>
                                        <td><a href="order_detail.php?id=<?= $o['order_id'] ?>" class="table-action">View →</a></td>
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

<script src="/assets/js/script.js" defer></script>
<script>
    // Toggle the advanced filter drawer open/closed
    document.getElementById('toggle-advanced-search').addEventListener('click', function() {
        document.getElementById('advanced-filters').classList.toggle('is-open');
    });
</script>

</html>