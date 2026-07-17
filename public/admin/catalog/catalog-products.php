<?php
// Note: $pdo and authentication are already handled by catalog-main.php!

const CATALOG_PAGE_SIZE = 10;

// 1. Fetch active nuclides for the dropdown filter
$nuclidesStmt = $pdo->query("SELECT nuclide_name FROM nuclides WHERE is_active = 1 ORDER BY nuclide_name ASC");
$active_nuclides = $nuclidesStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Handle Filters 
$q = $_GET['q'] ?? '';
$filter_nuclide = $_GET['nuclide'] ?? ''; 
$product = $_GET['product'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(p.product_name LIKE :q OR p.product_id LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($filter_nuclide !== '') {
    $where[] = "p.nuclide_name = :nuclide";
    $params[':nuclide'] = $filter_nuclide;
}
if ($product !== '') {
    $where[] = "p.product_name LIKE :product";
    $params[':product'] = "%$product%";
}
if ($status === 'active') {
    $where[] = "p.is_active = 1";
} elseif ($status === 'inactive') {
    $where[] = "p.is_active = 0";
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// 3. Get Total Count for Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

// 4. Calculate Pagination Math
$page = max(1, (int)($_GET['page'] ?? 1)); 
$totalPages = max(1, (int) ceil($totalCount / CATALOG_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * CATALOG_PAGE_SIZE;

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($totalCount, $offset + CATALOG_PAGE_SIZE);

// 5. Fetch the Actual Data
$listStmt = $pdo->prepare(
    "SELECT p.product_id, p.nuclide_name, p.product_name, p.default_delivery_option, p.is_active 
     FROM products p 
     $whereSql 
     ORDER BY p.is_active DESC, p.nuclide_name ASC, p.product_name ASC 
     LIMIT $offset, " . CATALOG_PAGE_SIZE
);
$listStmt->execute($params);
$display_products = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Wrap the function in a check so it doesn't cause errors if included multiple times
if (!function_exists('catalog_query')) {
    function catalog_query(array $overrides = []): string {
        $params = array_merge($_GET, $overrides);
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                unset($params[$key]);
            }
        }
        return http_build_query($params);
    }
}
?>

<div class="table-card">
    <div class="table-card-header">
        <span class="table-card-title">Product Index</span>
        
        <!-- Ensure action points back to the wrapper file -->
        <form method="get" action="catalog-main.php" class="table-card-controls">
            <!-- Hidden input ensures we stay on the products tab when filtering -->
            <input type="hidden" name="tab" value="products">
            
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Product Name or ID&hellip;">

            <select name="nuclide">
                <option value="">All Nuclides</option>
                <?php foreach ($active_nuclides as $n_name): ?>
                    <option value="<?= e($n_name) ?>" <?= $filter_nuclide === $n_name ? 'selected' : '' ?>>
                        <?= e($n_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
            </select>

            <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
        </form>
    </div>

    <?php if (!$display_products): ?>
        <?php $hasFilters = $q !== '' || $filter_nuclide !== '' || $product !== '' || $status !== ''; ?>
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
                    <!-- Clear filters points back to the base tab -->
                    <a href="catalog-main.php?tab=products" class="btn btn--secondary btn--sm">Clear filters</a>
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
                        <th>Product Name</th>
                        <th>Nuclide</th>
                        <th>Delivery Option</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($display_products as $p): ?>
                        <tr>
                            <td>
                                <span class="status-indicator">
                                    <span class="status-dot <?= !empty($p['is_active']) ? 'active' : 'inactive' ?>"></span>
                                    <?= !empty($p['is_active']) ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            
                            <td>
                                <span class="badge" style="font-family: monospace;">
                                    <?= str_pad((string) ($p['product_id'] ?? 0), 4, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            
                            <td><strong><?= e($p['product_name'] ?? '') ?></strong></td>
                            <td><?= e($p['nuclide_name'] ?? '') ?></td>
                            <td>
                                <span style="text-transform: capitalize;">
                                    <?= e($p['default_delivery_option'] ?? '') ?>
                                </span>
                            </td>
                            
                            <td>
                                <select class="catalog-actions" style="max-width: 140px" onchange="if(this.value) window.location.href=this.value;">
                                    <option value="">Actions</option>
                                    <option value="/admin/catalog/edit_product.php?id=<?= (int) $p['product_id'] ?>">View / Edit</option>
                                    <option value="/admin/catalog/catalog_toggle.php?type=product&id=<?= (int) $p['product_id'] ?>&status=<?= $p['is_active'] ? '0' : '1' ?>&<?= e(catalog_query([])) ?>">
                                        Set as <?= $p['is_active'] ? 'Inactive' : 'Active' ?>
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