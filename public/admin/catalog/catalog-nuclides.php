<?php
// Note: $pdo and authentication are already handled by catalog-main.php!

const NUCLIDE_PAGE_SIZE = 10;

// 1. Handle Filters 
$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "n.nuclide_name LIKE :q";
    $params[':q'] = "%$q%";
}

if ($status === 'active') {
    $where[] = "n.is_active = 1";
} elseif ($status === 'inactive') {
    $where[] = "n.is_active = 0";
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// 2. Get Total Count for Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM nuclides n $whereSql");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

// 3. Calculate Pagination Math
$page = max(1, (int)($_GET['page'] ?? 1)); 
$totalPages = max(1, (int) ceil($totalCount / NUCLIDE_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * NUCLIDE_PAGE_SIZE;

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($totalCount, $offset + NUCLIDE_PAGE_SIZE);

// 4. Fetch the Actual Data using a LEFT JOIN
// We join the products table so we can COUNT them and GROUP_CONCAT their names into a single string
$listStmt = $pdo->prepare(
    "SELECT 
        n.nuclide_name, 
        n.is_active,
        COUNT(p.product_id) as product_count,
        GROUP_CONCAT(p.product_name ORDER BY p.product_name ASC SEPARATOR '|') as product_list
     FROM nuclides n
     LEFT JOIN products p ON n.nuclide_name = p.nuclide_name
     $whereSql 
     GROUP BY n.nuclide_name
     ORDER BY n.is_active DESC, n.nuclide_name ASC 
     LIMIT $offset, " . NUCLIDE_PAGE_SIZE
);
$listStmt->execute($params);
$display_nuclides = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Safe query builder for pagination (only declare if it doesn't exist to prevent conflicts with products tab)
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
        <span class="table-card-title">Nuclides Index</span>
        
        <form method="get" action="catalog-main.php" class="table-card-controls">
            <!-- Keeps us on the nuclides tab when filtering -->
            <input type="hidden" name="tab" value="nuclides">
            
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Nuclide Name&hellip;">
            
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
            </select>

            <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
        </form>
    </div>

    <?php if (!$display_nuclides): ?>
        <?php $hasFilters = $q !== '' || $status !== ''; ?>
        <div class="empty-state">
            <div class="empty-state__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="10" r="7"></circle>
                    <line x1="21" y1="21" x2="15" y2="15"></line>
                </svg>
            </div>
            <div class="empty-state__title"><?= $hasFilters ? 'No nuclides match these filters' : 'No nuclides available' ?></div>
            <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Click "Add Nuclide" to create your first entry.' ?></p>
            <div class="empty-state__action">
                <?php if ($hasFilters): ?>
                    <a href="catalog-main.php?tab=nuclides" class="btn btn--secondary btn--sm">Clear filters</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Nuclide Name</th>
                        <th>Products / Chemical Forms</th>
                        <th>Product Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($display_nuclides as $n): ?>
                        <tr>
                            <td>
                                <span class="status-indicator">
                                    <span class="status-dot <?= !empty($n['is_active']) ? 'active' : 'inactive' ?>"></span>
                                    <?= !empty($n['is_active']) ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            
                            <td><strong><?= e($n['nuclide_name']) ?></strong></td>
                            
                            <!-- Teammate's Product List Concept -->
                            <td>
                                <?php if ($n['product_list']): ?>
                                    <ul style="margin: 0; padding-left: 18px; color: var(--color-text-secondary, #4b5563);">
                                        <?php 
                                            // Split the string created by GROUP_CONCAT into an array
                                            $products = explode('|', $n['product_list']);
                                            foreach ($products as $prod): 
                                        ?>
                                            <li><?= e($prod) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-style: italic;">No products attached</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Teammate's Count Badge Concept -->
                            <td>
                                <span class="badge" style="background-color: #edf5ff; color: #0d5bd7; font-weight: bold; font-size: 14px; padding: 4px 10px;">
                                    <?= (int) $n['product_count'] ?>
                                </span>
                            </td>
                            
                            <td>
                                <select class="catalog-actions" style="max-width: 140px" onchange="if(this.value) window.location.href=this.value;">
                                    <option value="">Actions</option>
                                    <option value="/admin/catalog/edit_nuclide.php?id=<?= urlencode($n['nuclide_name']) ?>">Edit Name</option>
                                    <option value="/admin/catalog/catalog_toggle.php?type=nuclide&id=<?= urlencode($n['nuclide_name']) ?>&status=<?= $n['is_active'] ? '0' : '1' ?>&<?= e(catalog_query([])) ?>">
                                        Set as <?= $n['is_active'] ? 'Inactive' : 'Active' ?>
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