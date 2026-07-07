<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Home'; $roleCss = 'admin';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
    <?php include '../src/partials/sidebar_staff.php'; ?>

        <main class="app-main">
            <h1>Upcoming Orders</h1>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
