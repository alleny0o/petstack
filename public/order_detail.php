<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';
require __DIR__ . '/../src/partials/ui.php';

/**
 * Customer order detail view.
 *
 * Read-only view of one order + cancel while pending (business rule:
 * customers may edit/cancel their own order only while it's pending).
 * TODO(db): require_role('customer'), restrict to own-lab orders, add
 * edit-while-pending, public comment thread, and CSRF on the cancel POST.
 */

$id = (int) ($_GET['id'] ?? 0);

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $notice = demo_order_cancel($id)
        ? 'Order canceled.'
        : 'This order can no longer be canceled.';
}

$order = demo_order_find($id);
$placed = isset($_GET['placed']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = $order ? 'Order #' . $order['id'] : 'Order not found';
    $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">

            <?php if ($order === null): ?>

                <h1>Order not found</h1>
                <div class="alert alert--error">No order with that number exists.</div>
                <a href="customer_home.php" class="btn btn--secondary">&larr; Back to Home</a>

            <?php else: ?>

                <header class="page-header">
                    <div>
                        <span class="page-header__eyebrow">Customer &middot; Order</span>
                        <h1>
                            <span class="tabular">#<?= $order['id'] ?></span>
                            <span class="badge badge--<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                        </h1>
                        <span class="page-header__meta">
                            Placed <span class="tabular"><?= htmlspecialchars($order['placed_at']) ?></span>
                        </span>
                    </div>
                    <div class="page-header__actions">
                        <a href="customer_home.php" class="btn btn--secondary">&larr; Back</a>
                    </div>
                </header>

                <?php if ($placed): ?>
                    <div class="alert alert--success">Order placed — pending review by staff.</div>
                <?php elseif ($notice): ?>
                    <div class="alert alert--<?= $notice === 'Order canceled.' ? 'success' : 'warning' ?>">
                        <?= htmlspecialchars($notice) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Compound</span>
                            <span class="detail-list__value"><?= htmlspecialchars($order['compound']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Isotope</span>
                            <span class="detail-list__value"><?= ui_nuclide($order['isotope'], true) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Order type</span>
                            <span class="detail-list__value">
                                <?= $order['type'] === 'A' ? 'Type A — dose order' : 'Type B — cyclotron order' ?>
                            </span>
                        </div>

                        <?php if ($order['type'] === 'A'): ?>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Activity</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['activity']) ?> mCi</span>
                            </div>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Requested</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['requested']) ?></span>
                            </div>
                        <?php elseif ($order['b_mode'] === 'beam'): ?>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Beam current</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['b_current']) ?>
                                    &micro;A</span>
                            </div>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Beam time</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['b_time']) ?> min</span>
                            </div>
                        <?php else: ?>
                            <div class="detail-list__row">
                                <span class="detail-list__label">EOB activity</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['b_activity']) ?>
                                    mCi</span>
                            </div>
                            <div class="detail-list__row">
                                <span class="detail-list__label">EOB date &amp; time</span>
                                <span class="detail-list__value tabular"><?= htmlspecialchars($order['b_datetime']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="detail-list__row">
                            <span class="detail-list__label">Delivery</span>
                            <span class="detail-list__value"><?= htmlspecialchars($order['delivery']) ?></span>
                        </div>
                        <?php if ($order['comment'] !== ''): ?>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Comment</span>
                                <span class="detail-list__value"><?= htmlspecialchars($order['comment']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['status'] === 'pending'): ?>
                    <form method="post" action="order_detail.php?id=<?= $order['id'] ?>"
                        onsubmit="return confirm('Cancel order #<?= $order['id'] ?>? This cannot be undone.');">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn--danger">Cancel Order</button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>