<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

/**
 * Staff order processing view.
 *
 * One order + status actions (accept / complete / return / cancel) and
 * the two append-only threads: public comments (customer-visible) and
 * internal notes (staff only).
 * TODO(db): require_role('staff', 'admin'), restrict to the staff
 * member's categories, use the logged-in identity as comment author,
 * write audit-log rows on status changes, add CSRF to the POST forms.
 */

$id = (int) ($_GET['id'] ?? 0);

$notice = '';
$noticeKind = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'status') {
        $to = $_POST['to'] ?? '';
        if (demo_order_set_status($id, $to)) {
            $labels = [
                'accepted'  => 'Order accepted.',
                'completed' => 'Order completed.',
                'pending'   => 'Order returned to pending.',
                'canceled'  => 'Order canceled.',
            ];
            $notice = $labels[$to] ?? 'Status updated.';
        } else {
            $notice = 'That status change is not allowed.';
            $noticeKind = 'warning';
        }
    } elseif ($action === 'comment') {
        $thread = $_POST['thread'] === 'internal' ? 'internal' : 'public';
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            demo_order_thread_add($id, $thread, 'M. Okafor (staff)', $body);
            $notice = $thread === 'internal' ? 'Internal note added.' : 'Comment posted.';
        }
    }
}

$order = demo_order_find($id);
$publicComments = demo_order_thread($id, 'public');
$internalNotes  = demo_order_thread($id, 'internal');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = $order ? 'Order #' . $order['id'] : 'Order not found';
    $roleCss = 'staff';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_staff.php'; ?>

        <main class="app-main">

            <?php if ($order === null): ?>

                <h1>Order not found</h1>
                <div class="alert alert--error">No order with that number exists.</div>
                <a href="staff_home.php" class="btn btn--secondary">&larr; Back to Queue</a>

            <?php else: ?>

                <div class="flex-between">
                    <div>
                        <div class="mb-2">
                            <span class="badge badge--<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>

                        <h1 class="mb-0">Order #<?= $order['id'] ?></h1>

                        <span class="text-sm muted">
                            Placed <?= htmlspecialchars($order['placed_at']) ?>
                        </span>
                    </div>
                    <a href="staff_home.php" class="btn btn--secondary">&larr; Back</a>
                </div>

                <?php if ($notice): ?>
                    <div class="alert alert--<?= $noticeKind ?>"><?= htmlspecialchars($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Compound</span>
                            <span class="detail-list__value"><?= htmlspecialchars($order['compound']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Isotope</span>
                            <span class="detail-list__value"><?= htmlspecialchars($order['isotope']) ?></span>
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
                                <span class="detail-list__label">Order comment</span>
                                <span class="detail-list__value"><?= htmlspecialchars($order['comment']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /* Status actions. Completed and canceled are terminal —
                         no buttons, and demo_order_set_status() (later the
                         real transition function) rejects changes anyway. */ ?>
                <?php if ($order['status'] === 'pending' || $order['status'] === 'accepted'): ?>
                    <div class="flex gap-2">
                        <?php if ($order['status'] === 'pending'): ?>
                            <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="to" value="accepted">
                                <button type="submit" class="btn btn--primary">Accept Order</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="to" value="completed">
                                <button type="submit" class="btn btn--primary">Mark Completed</button>
                            </form>
                            <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="to" value="pending">
                                <button type="submit" class="btn btn--secondary">Return to Pending</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>"
                            onsubmit="return confirm('Cancel order #<?= $order['id'] ?>? This cannot be undone.');">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="to" value="canceled">
                            <button type="submit" class="btn btn--danger">Cancel Order</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <span class="card__title">Public comments</span>
                    <p class="text-sm muted">Visible to the customer — use for back-and-forth about this order.</p>

                    <?php if (count($publicComments) === 0): ?>
                        <p class="muted">No comments yet.</p>
                    <?php else: ?>
                        <ul class="comment-thread">
                            <?php foreach ($publicComments as $c): ?>
                                <li class="comment">
                                    <div class="comment__meta">
                                        <span class="comment__author"><?= htmlspecialchars($c['author']) ?></span>
                                        <span class="comment__time tabular"><?= htmlspecialchars($c['at']) ?></span>
                                    </div>
                                    <p class="comment__body"><?= htmlspecialchars($c['body']) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>">
                        <input type="hidden" name="action" value="comment">
                        <input type="hidden" name="thread" value="public">
                        <div class="field">
                            <textarea name="body" required placeholder="Write a comment the customer will see…"></textarea>
                        </div>
                        <button type="submit" class="btn btn--secondary">Post Comment</button>
                    </form>
                </div>

                <div class="card">
                    <span class="card__title">Internal notes</span>
                    <p class="text-sm muted">Staff only — the customer never sees these.</p>

                    <?php if (count($internalNotes) === 0): ?>
                        <p class="muted">No notes yet.</p>
                    <?php else: ?>
                        <ul class="comment-thread">
                            <?php foreach ($internalNotes as $c): ?>
                                <li class="comment comment--internal">
                                    <div class="comment__meta">
                                        <span class="comment__author"><?= htmlspecialchars($c['author']) ?></span>
                                        <span class="comment__time tabular"><?= htmlspecialchars($c['at']) ?></span>
                                    </div>
                                    <p class="comment__body"><?= htmlspecialchars($c['body']) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="staff_order_detail.php?id=<?= $order['id'] ?>">
                        <input type="hidden" name="action" value="comment">
                        <input type="hidden" name="thread" value="internal">
                        <div class="field">
                            <textarea name="body" required placeholder="Add an internal note…"></textarea>
                        </div>
                        <button type="submit" class="btn btn--secondary">Add Note</button>
                    </form>
                </div>

            <?php endif; ?>

        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
