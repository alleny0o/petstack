<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Home'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/sidebar_customer.php'; ?>

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
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">In Progress</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">Updates</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__value">—</span>
                    <span class="stat-card__label">Lab orders this month</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Active Orders</span>
                    <div class="table-card-controls">
                        <select>
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                        </select>
                        <input type="text" placeholder="Order # or compound…">
                    </div>
                </div>
                <table class="table">
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
                        <tr>
                            <td class="muted tabular">1042</td>
                            <td>FDG</td>
                            <td class="muted">F-18</td>
                            <td class="muted tabular">2026-06-30 08:00</td>
                            <td><span class="badge badge--pending">Pending</span></td>
                            <td><a href="#" class="table-action">View →</a></td>
                        </tr>
                        <tr>
                            <td class="muted tabular">1039</td>
                            <td>Florbetapir</td>
                            <td class="muted">F-18</td>
                            <td class="muted tabular">2026-06-25 10:30</td>
                            <td><span class="badge badge--accepted">Accepted</span></td>
                            <td><a href="#" class="table-action">View →</a></td>
                        </tr>
                        <tr>
                            <td class="muted tabular">1035</td>
                            <td>FDG</td>
                            <td class="muted">F-18</td>
                            <td class="muted tabular">2026-06-18 09:00</td>
                            <td><span class="badge badge--completed">Completed</span></td>
                            <td><a href="#" class="table-action">View →</a></td>
                        </tr>
                        <tr>
                            <td class="muted tabular">1031</td>
                            <td>Fluciclovine</td>
                            <td class="muted">F-18</td>
                            <td class="muted tabular">2026-06-10 14:00</td>
                            <td><span class="badge badge--canceled">Canceled</span></td>
                            <td><a href="#" class="table-action">View →</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>