<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Catalog'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">
            <header class="page-header">
                <div>
                    <span class="page-header__eyebrow">Customer</span>
                    <h1>Catalog</h1>
                </div>
            </header>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
