<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

/**
 * Admin dashboard.
 *
 * TODO(db): require_role('admin'); pull registrations from
 * customer_registration_requests and counts from real tables.
 */
$orders = demo_orders();
$registrations = demo_registrations();

$pendingRegs   = count($registrations);
$pendingOrders = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$monthCount    = count(array_filter($orders, fn($o) => strpos($o['placed_at'], date('Y-m')) === 0));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Admin Home'; $roleCss = 'admin';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_admin.php'; ?>

        <main class="app-main">

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">Admin Home</h1>
                    <span class="text-sm muted">System overview</span>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $pendingRegs ?></span>
                    <span class="stat-card__label">Pending registrations</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $pendingOrders ?></span>
                    <span class="stat-card__label">Pending orders</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value tabular"><?= $monthCount ?></span>
                    <span class="stat-card__label">Orders this month</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">Active customers</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Pending Registrations</span>
                </div>
                <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Institute</th>
                            <th>Lab</th>
                            <th>PI</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td class="muted"><?= htmlspecialchars($r['email']) ?></td>
                                <td class="muted"><?= htmlspecialchars($r['institute']) ?></td>
                                <td class="muted"><?= htmlspecialchars($r['lab']) ?></td>
                                <td class="muted"><?= htmlspecialchars($r['pi']) ?></td>
                                <td class="muted tabular"><?= htmlspecialchars($r['submitted_at']) ?></td>
                                <td><a href="admin_registrations.php" class="table-action">Review →</a></td>
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

</html>
