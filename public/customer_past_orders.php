<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

$allOrders = demo_orders();

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

function buildUrl($pageUpdate) {
    $params = $_GET;
    $params['page'] = $pageUpdate;
    return '?' . http_build_query($params);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Past Orders'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">Order History</h1>
                    <span class="text-sm muted">[INST] &middot; [LAB]</span>
                </div>
            </div>

            <div class="table-card" style="margin-top: 20px;">
                <div class="table-card-header" style="flex-direction: column; align-items: stretch; gap: 10px;">
                    <span class="table-card-title mb-0">All Orders (<?= $totalItems ?>)</span>
                    
                    <form method="GET" action="customer_past_orders.php" id="filter-form">
                        <input type="hidden" name="page" value="1"> 
                        
                        <div class="search-bar-top">
                            <input type="text" name="search" placeholder="Search Order # or compound…" value="<?= htmlspecialchars($filterSearch) ?>">
                            
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
                                        <td class="muted tabular"><?= htmlspecialchars($o['id']) ?></td>
                                        <td><?= htmlspecialchars($o['compound']) ?></td>
                                        <td class="muted"><?= htmlspecialchars($o['isotope']) ?></td>
                                        <td class="muted tabular"><?= htmlspecialchars($o['requested'] ?? $o['b_datetime'] ?? '—') ?></td>
                                        <td><span class="badge badge--<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                        <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="table-action">View →</a></td>
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

<script src="assets/js/script.js" defer></script>
<script>
    // Toggle the advanced filter drawer open/closed
    document.getElementById('toggle-advanced-search').addEventListener('click', function() {
        document.getElementById('advanced-filters').classList.toggle('is-open');
    });
</script>

</html>