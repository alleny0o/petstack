<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Catalog Config'; $roleCss = 'admin';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_admin.php'; ?>

        <main class="app-main">
            <header class="page-header">
                <div>
                    <span class="page-header__eyebrow">Admin</span>
                    <h1>Catalog Config</h1>
                </div>
            </header>
        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
