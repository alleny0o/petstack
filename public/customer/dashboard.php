<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.registration_status, i.name AS institute_name, l.lab_name
     FROM customers c
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     LEFT JOIN institutes i ON i.institute_id = l.institute_id
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
                <h1>Dashboard</h1>
            </div>
            <p>You're logged in as <?= e($_SESSION['username']) ?>.</p>
            <p>Registration status: <strong><?= e(ucfirst($myInfo['registration_status'])) ?></strong></p>
            <?php if ($myInfo['registration_status'] === 'approved'): ?>
                <p class="muted"><?= e($myInfo['institute_name']) ?> &middot; <?= e($myInfo['lab_name']) ?></p>
            <?php endif; ?>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
