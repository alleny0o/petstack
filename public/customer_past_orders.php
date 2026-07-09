<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

// Fetch all orders
$allOrders = demo_orders();

$filterSearch  = $_GET['search'] ?? '';
$filterStatus  = $_GET['status'] ?? '';
$filterIsotope = $_GET['isotope'] ?? '';
$page          = max(1, intval($_GET['page'] ?? 1)); // Default to page 1
$itemsPerPage  = 12; // Number of items to show per page

// Extract unique isotopes for the dropdown filter dynamically
$uniqueIsotopes = array_unique(array_column($allOrders, 'isotope'));
sort($uniqueIsotopes);

$filteredOrders = array_filter($allOrders, function($o) use ($filterSearch, $filterStatus, $filterIsotope) {
    // Check Search (matches ID or Compound)
    $matchesSearch = $filterSearch === '' || 
                     stripos($o['id'], $filterSearch) !== false || 
                     stripos($o['compound'], $filterSearch) !== false;
    
    // Check Status
    $matchesStatus = $filterStatus === '' || $o['status'] === $filterStatus;
    
    // Check Isotope
    $matchesIsotope = $filterIsotope === '' || strcasecmp($o['isotope'], $filterIsotope) === 0;

    return $matchesSearch && $matchesStatus && $matchesIsotope;
});

$totalItems = count($filteredOrders);
$totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;

// Ensure current page doesn't exceed total pages if filters narrow down the results
$page = min($page, $totalPages); 

$offset = ($page - 1) * $itemsPerPage;
// Extract just the items for the current page
$paginatedOrders = array_slice($filteredOrders, $offset, $itemsPerPage);

// Helper function to build pagination links while preserving filter parameters
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
                <div class="table-card-header">
                    <span class="table-card-title">All Orders (<?= $totalItems ?>)</span>
                    
                    <form method="GET" action="customer_past_orders.php" class="filter-form" id="filter-form">
                        <input type="hidden" name="page" value="1"> 
                        
                        <select name="isotope" id="filter-isotope" onchange="this.form.submit()">
                            <option value="">All Isotopes</option>
                            <?php foreach ($uniqueIsotopes as $iso): ?>
                                <option value="<?= htmlspecialchars($iso) ?>" <?= $filterIsotope === $iso ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($iso) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" id="filter-status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="accepted" <?= $filterStatus === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="canceled" <?= $filterStatus === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                        </select>
                        
                        <input type="text" name="search" id="filter-search" 
                               placeholder="Order # or compound…" 
                               value="<?= htmlspecialchars($filterSearch) ?>">
                        
                        <button type="submit" class="btn btn--primary">Filter</button>
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

</html>