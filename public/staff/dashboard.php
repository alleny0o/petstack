<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('staff');

$pdo = get_db();

$stmt = $pdo->prepare(
    'SELECT cat.category_name
     FROM staff s
     JOIN categories cat ON cat.category_id = s.category_id
     WHERE s.user_id = ?'
);
$stmt->execute([(int) $_SESSION['user_id']]);
$categoryName = (string) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Order Queue'; include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_staff.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <div>
                    <span class="page-header__eyebrow">Staff</span>
                    <h1>Order Queue</h1>
                    <span class="page-header__meta">Signed in as <?= e($_SESSION['username']) ?></span>
                </div>
                <div class="page-header__actions">
                    <a href="/change_password.php" class="btn btn--secondary">Change Password</a>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-tile">
                    <span class="stat-tile__label">Your Category</span>
                    <span class="stat-tile__value stat-tile__value--text"><?= e($categoryName) ?></span>
                    <span class="stat-tile__meta">You process orders in this category only</span>
                </div>
                <div class="stat-tile">
                    <span class="stat-tile__label">Orders To Process</span>
                    <span class="stat-tile__value tabular">&mdash;</span>
                    <span class="stat-tile__meta">Order processing arrives in a later phase</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Incoming Orders</span>
                </div>
                <div class="empty-state">
                    <div class="empty-state__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                        </svg>
                    </div>
                    <div class="empty-state__title">No orders to process yet</div>
                    <p class="empty-state__hint">Customer ordering isn't open yet. Orders for the <?= e($categoryName) ?> category will land here.</p>
                </div>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
