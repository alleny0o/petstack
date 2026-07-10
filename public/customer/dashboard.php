<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.first_name, c.last_name, c.phone, c.registration_status,
            i.name AS institute_name, l.lab_name, p.pi_name,
            u.username, u.created_at
     FROM customers c
     JOIN users u ON u.user_id = c.user_id
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     LEFT JOIN institutes i ON i.institute_id = l.institute_id
     LEFT JOIN pis p ON p.pi_id = c.supervising_pi_id
     WHERE c.user_id = ?'
);
$stmt->execute([$myUserId]);
$myInfo = $stmt->fetch();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <div>
                    <span class="page-header__eyebrow">Dashboard</span>
                    <h1>Welcome, <?= e($myInfo['first_name']) ?></h1>
                    <span class="page-header__meta">Signed in as <?= e($myInfo['username']) ?></span>
                </div>
                <div class="page-header__actions">
                    <a href="/change_password.php" class="btn btn--secondary">Change Password</a>
                </div>
            </div>

            <div class="dash-grid dash-grid--even">
                <div class="card mt-0 mb-0">
                    <span class="card__title">My Lab</span>
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Institute</span>
                            <span class="detail-list__value"><?= e($myInfo['institute_name'] ?? '—') ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Lab</span>
                            <span class="detail-list__value"><?= e($myInfo['lab_name'] ?? '—') ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Supervising PI</span>
                            <span class="detail-list__value"><?= e($myInfo['pi_name'] ?? '—') ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Phone</span>
                            <span class="detail-list__value tabular"><?= e($myInfo['phone'] ?? '—') ?></span>
                        </div>
                    </div>
                    <p class="field-hint mt-2 mb-0">Something out of date? Contact an administrator to update your lab details.</p>
                </div>

                <div class="card mt-0 mb-0">
                    <span class="card__title">Account</span>
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Email (username)</span>
                            <span class="detail-list__value"><?= e($myInfo['username']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Registration</span>
                            <span class="detail-list__value"><span class="badge badge--<?= e($myInfo['registration_status']) ?>"><?= e(ucfirst($myInfo['registration_status'])) ?></span></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Member since</span>
                            <span class="detail-list__value"><?= e(date('M j, Y', strtotime($myInfo['created_at']))) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card mt-4">
                <div class="table-card-header">
                    <span class="table-card-title">Recent Orders</span>
                </div>
                <div class="empty-state">
                    <div class="empty-state__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="empty-state__title">You haven't placed any orders yet</div>
                    <p class="empty-state__hint">Ordering opens in a later phase &mdash; your lab's orders will show up here.</p>
                </div>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
