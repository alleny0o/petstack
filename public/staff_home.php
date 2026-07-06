<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';
require __DIR__ . '/../src/partials/ui.php';

/**
 * Staff order queue.
 *
 * TODO(db): require_role('staff', 'admin'), and scope the queue to
 * compounds in the staff member's assigned categories only.
 */
$orders = demo_orders();
$queue  = array_filter($orders, fn($o) => in_array($o['status'], ['pending', 'accepted'], true));

$pendingCount   = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$acceptedCount  = count(array_filter($orders, fn($o) => $o['status'] === 'accepted'));
$completedMonth = count(array_filter(
    $orders,
    fn($o) => $o['status'] === 'completed' && strpos($o['placed_at'], date('Y-m')) === 0
));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Order Queue'; $roleCss = 'staff';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_staff.php'; ?>

        <main class="app-main">

            <header class="page-header">
                <div>
                    <span class="page-header__eyebrow">Staff</span>
                    <h1>Order Queue</h1>
                </div>
            </header>

            <div class="dashboard-grid">
                <div class="stat-card stat-card--pending">
                    <span class="stat-card__value tabular"><?= $pendingCount ?></span>
                    <span class="stat-card__label">Pending</span>
                </div>
                <div class="stat-card stat-card--accepted">
                    <span class="stat-card__value tabular"><?= $acceptedCount ?></span>
                    <span class="stat-card__label">In Progress</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $completedMonth ?></span>
                    <span class="stat-card__label">Completed this month</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Queue</span>
                    <div class="table-card-controls">
                        <select id="filter-status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                        </select>
                        <input type="text" id="filter-search" placeholder="Order # or compound…">
                    </div>
                </div>
                <div class="table-scroll">
                <table class="table" id="orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Compound</th>
                            <th>Isotope</th>
                            <th>Type</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue as $o): ?>
                            <tr data-status="<?= $o['status'] ?>">
                                <td class="muted tabular"><?= $o['id'] ?></td>
                                <td><?= htmlspecialchars($o['compound']) ?></td>
                                <td><?= ui_nuclide($o['isotope']) ?></td>
                                <td class="muted"><?= $o['type'] ?></td>
                                <td class="muted tabular"><?= htmlspecialchars($o['requested'] ?? $o['b_datetime'] ?? '—') ?></td>
                                <td><span class="badge badge--<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                <td><a href="staff_order_detail.php?id=<?= $o['id'] ?>" class="table-action">Process →</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>
<script>
    // Same client-side filtering as customer_home.php.
    const statusFilter = document.getElementById('filter-status');
    const searchFilter = document.getElementById('filter-search');
    const orderRows = document.querySelectorAll('#orders-table tbody tr');

    function applyFilters() {
        const status = statusFilter.value;
        const query = searchFilter.value.trim().toLowerCase();

        orderRows.forEach(row => {
            const matchesStatus = !status || row.dataset.status === status;
            const id = row.cells[0].textContent.toLowerCase();
            const compound = row.cells[1].textContent.toLowerCase();
            const matchesQuery = !query || id.includes(query) || compound.includes(query);
            row.hidden = !(matchesStatus && matchesQuery);
        });
    }

    statusFilter.addEventListener('change', applyFilters);
    searchFilter.addEventListener('input', applyFilters);
</script>

</html>
