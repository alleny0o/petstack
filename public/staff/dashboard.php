<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Dashboard'; include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_staff.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <h1>Dashboard</h1>
            </div>
            <p>You're logged in as <?= e($_SESSION['username']) ?>.</p>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
