<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Past Orders'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">
            <h1>Past Orders</h1>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
