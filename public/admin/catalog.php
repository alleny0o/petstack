<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

const CATALOG_PAGE_SIZE = 20;

// 1. Generate Mock Data (Now including an 'active' flag and ID)
$all_products = [];
$sample_isotopes = ['C-14', 'H-3', 'P-32', 'I-125', 'S-35'];
$sample_compounds = ['Glucose', 'Thymidine', 'ATP', 'Water', 'Methionine'];

for ($i = 1; $i <= 60; $i++) {
    $iso = $sample_isotopes[array_rand($sample_isotopes)];
    $comp = $sample_compounds[array_rand($sample_compounds)];
    // Randomly assign ~85% of products as active, 15% as inactive
    $is_active = (rand(1, 100) <= 85); 
    
    $all_products[] = [
        'id' => $i,
        'name' => "Radiolabeled $comp ($iso)",
        'sku' => 'SKU-' . str_pad($i, 4, '0', STR_PAD_LEFT),
        'isotope' => $iso,
        'compound' => $comp,
        'description' => "Standard specifications for $comp labeled with $iso. Manufactured in-house.",
        'active' => $is_active
    ];
}

// 2. Capture GET Parameters
$q = trim($_GET['q'] ?? '');
$isotope = trim($_GET['isotope'] ?? '');
$compound = trim($_GET['compound'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// 3. Filter the Data
$filtered_products = $all_products;
if ($q !== '' || $isotope !== '' || $compound !== '' || $status !== '') {
    $filtered_products = array_filter($all_products, function($product) use ($q, $isotope, $compound, $status) {
        $match_q = true;
        $match_iso = true;
        $match_comp = true;
        $match_status = true;

        if ($q !== '') {
            $match_q = (stripos($product['name'], $q) !== false || stripos($product['sku'], $q) !== false);
        }
        if ($isotope !== '') {
            $match_iso = (stripos($product['isotope'], $isotope) !== false);
        }
        if ($compound !== '') {
            $match_comp = (stripos($product['compound'], $compound) !== false);
        }
        if ($status === 'active') {
            $match_status = ($product['active'] === true);
        } elseif ($status === 'inactive') {
            $match_status = ($product['active'] === false);
        }

        return $match_q && $match_iso && $match_comp && $match_status;
    });
}

// 4. Pagination Math
$totalCount = count($filtered_products);
$totalPages = max(1, (int) ceil($totalCount / CATALOG_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * CATALOG_PAGE_SIZE;

$display_products = array_slice($filtered_products, $offset, CATALOG_PAGE_SIZE);

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + CATALOG_PAGE_SIZE, $totalCount);

function catalog_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

$pageTitle = 'Manage Catalog';
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
            
            <div class="page-header">
                <h1>Manage Catalog</h1>
                <a href="/admin/product_add.php" class="btn btn--primary">Add Product</a>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Inventory Index</span>
                    <form method="get" class="table-card-controls">
                        <!-- Search Inputs -->
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Name or SKU&hellip;">
                        <input type="text" name="isotope" value="<?= e($isotope) ?>" placeholder="Isotope (e.g., C-14)">
                        <input type="text" name="compound" value="<?= e($compound) ?>" placeholder="Compound (e.g., Glucose)">
                        
                        <!-- Status Dropdown -->
                        <select name="status">
                            <option value="">All statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
                        </select>

                        <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
                    </form>
                </div>

                <?php if (!$display_products): ?>
                    <?php $hasFilters = $q !== '' || $isotope !== '' || $compound !== '' || $status !== ''; ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="10" r="7"></circle>
                                <line x1="21" y1="21" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="empty-state__title"><?= $hasFilters ? 'No products match these filters' : 'No products available' ?></div>
                        <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Click "Add Product" to create your first catalog entry.' ?></p>
                        <div class="empty-state__action">
                            <?php if ($hasFilters): ?>
                                <a href="admin_catalog.php" class="btn btn--secondary btn--sm">Clear filters</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Isotope</th>
                                    <th>Compound</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_products as $p): ?>
                                    <tr>
                                        <!-- Restored Status Indicator (Dot + Text) -->
                                        <td>
                                            <span class="status-indicator">
                                                <span class="status-dot <?= $p['active'] ? 'active' : 'inactive' ?>"></span>
                                                <?= $p['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        
                                        <td><strong><?= e($p['name']) ?></strong></td>
                                        <td><span class="badge" style="font-family: monospace;"><?= e($p['sku']) ?></span></td>
                                        <td><?= e($p['isotope']) ?></td>
                                        <td><?= e($p['compound']) ?></td>
                                        <td style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($p['description']) ?>">
                                            <?= e($p['description']) ?>
                                        </td>
                                        
                                        <!-- Admin Actions -->
                                        <td>
                                            <a href="/admin/product_edit.php?id=<?= (int) $p['id'] ?>" class="table-action">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-pagination">
                        <span class="table-pagination__status">Showing <?= $rangeStart ?>&ndash;<?= $rangeEnd ?> of <?= $totalCount ?></span>
                        <div class="table-pagination__controls">
                            <span class="table-pagination__status">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page <= 1): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(catalog_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                            <?php endif; ?>
                            <?php if ($page >= $totalPages): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(catalog_query(['page' => $page + 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Next page">&rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>