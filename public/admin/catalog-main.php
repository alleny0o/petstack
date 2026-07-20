<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

// 1. Tab Routing Logic
$allowed_tabs = ['products', 'nuclides', 'institutes', 'labs', 'pis'];
$current_tab = $_GET['tab'] ?? 'products';

if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'products';
}

$pageTitle = 'Manage Databases';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            
            <div class="page-header page-header-flex">
                <h1>Manage Databases</h1>
                <!-- Dynamically changes the button based on the active tab! -->
                <a href="/admin/catalog/add_<?= e($current_tab) ?>.php" class="btn btn--primary">
                    Add <?= $current_tab === 'pis' ? 'Principal Investigator' : ucfirst(rtrim($current_tab, 's')) ?>
                </a>
            </div>

            <!-- The Folder Tabs -->
            <nav class="page-tabs">
                <a href="?tab=products" class="tab-link <?= $current_tab === 'products' ? 'active' : '' ?>">Products</a>
                <a href="?tab=nuclides" class="tab-link <?= $current_tab === 'nuclides' ? 'active' : '' ?>">Nuclides</a>
                <a href="?tab=institutes" class="tab-link <?= $current_tab === 'institutes' ? 'active' : '' ?>">Institutes</a>
                <a href="?tab=labs" class="tab-link <?= $current_tab === 'labs' ? 'active' : '' ?>">Labs</a>
                <a href="?tab=pis" class="tab-link <?= $current_tab === 'pis' ? 'active' : '' ?>">PIs</a>
            </nav>

            <!-- Load the specific table module -->
            <?php 
                $tab_file = __DIR__ . "/catalog/catalog-{$current_tab}.php";
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    // Fallback if you haven't created the file for a tab yet
                    echo '<div class="table-card">';
                    echo '<div class="table-card-header"><span class="table-card-title">Coming Soon</span></div>';
                    echo '<p style="padding: 20px;">The ' . e(ucfirst($current_tab)) . ' module is under construction.</p>';
                    echo '</div>';
                }
            ?>

        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>