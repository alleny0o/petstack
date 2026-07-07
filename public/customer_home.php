<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

// TODO(db): scope to the logged-in customer's lab + compute "Updates"
// from the audit log once auth and MariaDB exist.
$orders = demo_orders();

$pendingCount  = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$acceptedCount = count(array_filter($orders, fn($o) => $o['status'] === 'accepted'));
$monthCount    = count(array_filter($orders, fn($o) => str_starts_with($o['placed_at'], date('Y-m'))));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Home'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">Home</h1>
                    <span class="text-sm muted">[INST] &middot; [LAB]</span>
                </div>
                <a href="order_form.php" class="btn btn--primary">+ New Order</a>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $pendingCount ?></span>
                    <span class="stat-card__label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $acceptedCount ?></span>
                    <span class="stat-card__label">Accepted</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">Ready for Pickup</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $monthCount ?></span>
                    <span class="stat-card__label">Delivered</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Active Orders</span>
                    <div class="table-card-controls">
                        <select id="filter-status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
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
                            <th>Requested</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr data-status="<?= $o['status'] ?>">
                                <td class="muted tabular"><?= $o['id'] ?></td>
                                <td><?= htmlspecialchars($o['compound']) ?></td>
                                <td class="muted"><?= htmlspecialchars($o['isotope']) ?></td>
                                <td class="muted tabular"><?= htmlspecialchars($o['requested'] ?? $o['b_datetime'] ?? '—') ?></td>
                                <td><span class="badge badge--<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="table-action">View →</a></td>
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
    // Client-side table filtering — status dropdown + text search over
    // order # and compound. Stays useful post-DB (or gets replaced by
    // server-side queries if the table ever paginates).
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
