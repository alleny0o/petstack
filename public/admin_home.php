<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Home'; $roleCss = 'admin';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <!-- TODO: include '../src/partials/sidebar_admin.php' once built -->

        <main class="app-main">
            <h1>Admin Dashboard</h1>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
