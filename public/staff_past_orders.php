<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';
require __DIR__ . '/../src/partials/ui.php';

/**
 * Staff past orders — completed and canceled orders, all labs.
 * TODO(db): require_role('staff', 'admin'), paginate once real data
 * exists, scope to the staff member's categories.
 */
$past = array_filter(demo_orders(), fn($o) => in_array($o['status'], ['completed', 'canceled'], true));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Past Orders'; $roleCss = 'staff';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_staff.php'; ?>

        <main class="app-main">

            <header class="page-header">
                <div>
                    <span class="page-header__eyebrow">Staff</span>
                    <h1>Past Orders</h1>
                </div>
            </header>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Completed &amp; Canceled</span>
                    <div class="table-card-controls">
                        <select id="filter-status">
                            <option value="">All Statuses</option>
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
                            <th>Type</th>
                            <th>Placed</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $o): ?>
                            <tr data-status="<?= $o['status'] ?>">
                                <td class="muted tabular"><?= $o['id'] ?></td>
                                <td><?= htmlspecialchars($o['compound']) ?></td>
                                <td><?= ui_nuclide($o['isotope']) ?></td>
                                <td class="muted"><?= $o['type'] ?></td>
                                <td class="muted tabular"><?= htmlspecialchars($o['placed_at']) ?></td>
                                <td><span class="badge badge--<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                <td><a href="staff_order_detail.php?id=<?= $o['id'] ?>" class="table-action">View →</a></td>
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
