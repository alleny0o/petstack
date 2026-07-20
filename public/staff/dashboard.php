<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('staff');

$pdo = get_db();

// Not lab-scoped -- staff triage spans every lab ("any staff, any
// order"), unlike the customer dashboard's own-lab-only stats.
$dashStatStmt = $pdo->query(
    "SELECT COUNT(*) AS total_count,
            COALESCE(SUM(status = 'pending'), 0) AS pending_count,
            COALESCE(SUM(status = 'accepted'), 0) AS accepted_count
     FROM orders"
);
$dashStats = $dashStatStmt->fetch();
$dashPendingCount = (int) $dashStats['pending_count'];
$dashAcceptedCount = (int) $dashStats['accepted_count'];
$dashTotalCount = (int) $dashStats['total_count'];

// 4th tile: pending/accepted orders due today or already overdue -- same
// actionable status set and date bound as "Due Today & Overdue" below,
// just a count instead of a list.
$dashDueTodayStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'accepted') AND requested_datetime <= ?"
);
$dashDueTodayStmt->execute([date('Y-m-d 23:59:59')]);
$dashDueTodayCount = (int) $dashDueTodayStmt->fetchColumn();

// Due Today & Overdue: the operationally useful view for a radiotracer
// department -- pending/accepted orders (the two actionable statuses)
// whose tracer is needed today or was needed already. No lower bound on
// requested_datetime: an order that's already past its requested time and
// still pending/accepted is MORE urgent, not excluded -- flagged as
// overdue below rather than hidden. Bounded to end-of-today (same window
// as the stat tile above), not a multi-day lookahead -- this is a landing
// page, not a work surface; the Order Queue is where staff triage further out.
$dashDueStmt = $pdo->prepare(
    "SELECT o.order_id, o.status, o.requested_datetime,
            p.name AS product_name,
            l.lab_name
     FROM orders o
     JOIN customers c ON c.user_id = o.customer_id
     JOIN products p  ON p.product_id = o.product_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     WHERE o.status IN ('pending', 'accepted')
       AND o.requested_datetime <= ?
     ORDER BY o.requested_datetime ASC
     LIMIT 8"
);
$dashDueStmt->execute([date('Y-m-d 23:59:59')]);
$dashDueOrders = $dashDueStmt->fetchAll();
$dashNow = time();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_staff.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <div>
                    <span class="page-header__eyebrow">Staff</span>
                    <h1>Dashboard</h1>
                    <span class="page-header__meta">Signed in as <?= e($_SESSION['username']) ?></span>
                </div>
                <div class="page-header__actions">
                    <a href="/staff/orders.php" class="btn btn--primary">Order Queue</a>
                </div>
            </div>

            <div class="stat-grid">
                <a class="stat-tile" href="/staff/orders.php?status=pending">
                    <span class="stat-tile__label">
                        <?php if ($dashPendingCount > 0): ?><span class="dot dot--warning"></span><?php endif; ?>
                        Pending Orders
                    </span>
                    <span class="stat-tile__value tabular"><?= $dashPendingCount ?></span>
                    <span class="stat-tile__meta"><?= $dashPendingCount > 0 ? 'Needs action' : 'None pending' ?></span>
                </a>
                <a class="stat-tile" href="/staff/orders.php?status=accepted">
                    <span class="stat-tile__label">Accepted Orders</span>
                    <span class="stat-tile__value tabular"><?= $dashAcceptedCount ?></span>
                    <span class="stat-tile__meta">In progress</span>
                </a>
                <a class="stat-tile" href="/staff/orders.php">
                    <span class="stat-tile__label">Total Orders</span>
                    <span class="stat-tile__value tabular"><?= $dashTotalCount ?></span>
                    <span class="stat-tile__meta">All labs, all time</span>
                </a>
                <?php // The queue's status tabs are single-status, so there's no
                      // exact "pending or accepted" filter to link into --
                      // landing on the unfiltered All tab with the date bound
                      // applied is the closest honest match; the tabs there
                      // preserve requested_to, so one more click narrows to
                      // Pending or Accepted without losing the date filter. ?>
                <a class="stat-tile" href="/staff/orders.php?requested_to=<?= e(date('Y-m-d')) ?>">
                    <span class="stat-tile__label">
                        <?php if ($dashDueTodayCount > 0): ?><span class="dot dot--warning"></span><?php endif; ?>
                        Due Today
                    </span>
                    <span class="stat-tile__value tabular"><?= $dashDueTodayCount ?></span>
                    <span class="stat-tile__meta"><?= $dashDueTodayCount > 0 ? 'Today or overdue' : 'Nothing due' ?></span>
                </a>
            </div>

            <?php // Full-width table-card, no side column -- this is a landing
                  // page, not a work surface; staff triage happens on the
                  // Order Queue (/staff/orders.php). Unlike the customer
                  // dashboard's .dash-grid + .dash-stack, there's nothing to
                  // put beside this table, so it isn't wrapped in one. ?>
            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Due Today &amp; Overdue</span>
                    <div class="table-card-controls">
                        <a href="/staff/orders.php?status=pending" class="table-action">View all</a>
                    </div>
                </div>
                <?php if (!$dashDueOrders): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="empty-state__title">Nothing due today</div>
                        <p class="empty-state__hint">Pending and accepted orders needed today, or already overdue, will show up here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Requested</th>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Lab</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashDueOrders as $o): ?>
                                    <?php
                                    // Two-tier urgency: past requested time (any
                                    // day) = overdue/red, later today = amber.
                                    // No neutral/future tier -- the query is
                                    // bounded to end-of-today, so every row is
                                    // one of these two.
                                    $dashBadgeClass = $o['status'];
                                    $dashDueTs = strtotime($o['requested_datetime']);
                                    $dashIsOverdue = $dashDueTs < $dashNow;
                                    ?>
                                    <tr>
                                        <?php // .table-flag fixed-width slot (tables.css) so
                                              // flagged and unflagged rows keep their dates
                                              // aligned -- same convention as the customer
                                              // dashboard's updated-since-last-visit dot. ?>
                                        <td class="tabular nowrap">
                                            <span class="table-flag"><?php if ($dashIsOverdue): ?><span class="dot dot--error" title="Overdue"></span><span class="sr-only">Overdue</span><?php else: ?><span class="dot dot--warning" title="Due today"></span><span class="sr-only">Due today</span><?php endif; ?></span><?= e(date('M j, Y H:i', $dashDueTs)) ?>
                                        </td>
                                        <td class="tabular"><?= (int) $o['order_id'] ?></td>
                                        <td><?= e($o['product_name']) ?></td>
                                        <td><?= e($o['lab_name'] ?? '—') ?></td>
                                        <td><span class="badge badge--<?= e($dashBadgeClass) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                        <td><a href="/staff/order_detail.php?id=<?= (int) $o['order_id'] ?>" class="table-action">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="<?= asset_url('/assets/js/script.js') ?>" defer></script>
</html>
