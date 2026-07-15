<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

const CATALOG_PAGE_SIZE = 5;

// 1. Capture GET Parameters
$q = trim($_GET['q'] ?? '');
$isotope = trim($_GET['isotope'] ?? '');
$compound = trim($_GET['compound'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// 2. Build the Dynamic SQL WHERE Clause
$where = [];
$params = [];

if ($q !== '') {
    // Escape LIKE wildcards so literal "%" or "_" searches don't break the query
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "(c.name LIKE ? ESCAPE '\\\\' OR c.compound_id LIKE ? ESCAPE '\\\\')";
    $params[] = '%' . $escaped . '%';
    $params[] = '%' . $escaped . '%';
}
if ($isotope !== '') {
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $isotope);
    $where[] = "c.isotope_name LIKE ? ESCAPE '\\\\'";
    $params[] = '%' . $escaped . '%';
}
if ($compound !== '') {
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $compound);
    $where[] = "c.name LIKE ? ESCAPE '\\\\'";
    $params[] = '%' . $escaped . '%';
}
if ($status === 'active') {
    $where[] = "c.active = 1";
} elseif ($status === 'inactive') {
    $where[] = "c.active = 0";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// 3. Get Total Count for Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM compounds c $whereSql");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

// Calculate Pagination Math
$totalPages = max(1, (int) ceil($totalCount / CATALOG_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * CATALOG_PAGE_SIZE;

// 4. Fetch the Actual Data for the Current Page
// Note: LIMIT and OFFSET are interpolated directly as they are strictly server-computed integers
$listStmt = $pdo->prepare(
    "SELECT c.compound_id, c.isotope_name, c.name, c.category, c.description, c.active 
     FROM compounds c 
     $whereSql 
     ORDER BY c.active DESC, c.isotope_name ASC, c.name ASC
     LIMIT $offset, " . CATALOG_PAGE_SIZE
);
$listStmt->execute($params);
$display_products = $listStmt->fetchAll();

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + CATALOG_PAGE_SIZE, $totalCount);

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values -- used for pagination links
 */
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
            
            <div class="page-header page-header-flex">
                <h1>Manage Catalog</h1>
                <a href="/admin/product_add.php" class="btn btn--primary">Add Product</a>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Inventory Index</span>
                    <form method="get" class="table-card-controls">
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Name or ID&hellip;">
                        <input type="text" name="isotope" value="<?= e($isotope) ?>" placeholder="Isotope (e.g., C-14)">
                        <input type="text" name="compound" value="<?= e($compound) ?>" placeholder="Compound (e.g., Glucose)">
                        
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
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Isotope</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_products as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="status-indicator">
                                                <span class="status-dot <?= !empty($p['active']) ? 'active' : 'inactive' ?>"></span>
                                                <?= !empty($p['active']) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <span class="badge" style="font-family: monospace;">
                                                <?= str_pad((string) ($p['compound_id'] ?? 0), 4, '0', STR_PAD_LEFT) ?>
                                            </span>
                                        </td>
                                        
                                        <td><strong><?= e($p['name'] ?? '') ?></strong></td>
                                        <td><?= e($p['isotope_name'] ?? '') ?></td>
                                        <td><span class="badge badge--neutral"><?= e($p['category'] ?? '') ?></span></td>
                                        
                                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($p['description'] ?? 'null') ?>">
                                            <?= e($p['description'] ?? '') ?>
                                        </td>
                                        
                                        <td>
                                            <select class="catalog-actions" style="max-width: 140px" onchange="if(this.value) window.location.href=this.value;">
                                                <option value="">Actions</option>
                                                <option value="/admin/product_edit.php?id=<?= (int) $p['compound_id'] ?>">View / Edit</option>
                                                <option value="/admin/catalog_product_toggle.php?id=<?= (int) $p['compound_id'] ?>&status=<?= $p['active'] ? '0' : '1' ?>">
                                                    Set as <?= $p['active'] ? 'Inactive' : 'Active' ?>
                                                </option>
                                            </select>
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
    <script src="/assets/js/script.js" defer></script>
</body>
</html>