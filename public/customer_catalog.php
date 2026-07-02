<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Catalog'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/sidebar_customer.php'; ?>

        <main class="app-main">
            <h1>Catalog</h1>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
