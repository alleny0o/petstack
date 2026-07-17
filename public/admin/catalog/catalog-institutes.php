<?php
// Note: $pdo and authentication are already handled by catalog-main.php!

const INSTITUTE_PAGE_SIZE = 10;

// 1. Handle Filters 
$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE :q OR shorthand_name LIKE :q)";
    $params[':q'] = "%$q%";
}

if ($status === 'active') {
    $where[] = "is_active = 1";
} elseif ($status === 'inactive') {
    $where[] = "is_active = 0";
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// 2. Get Total Count for Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM institutes $whereSql");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

// 3. Calculate Pagination Math
$page = max(1, (int)($_GET['page'] ?? 1)); 
$totalPages = max(1, (int) ceil($totalCount / INSTITUTE_PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * INSTITUTE_PAGE_SIZE;

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($totalCount, $offset + INSTITUTE_PAGE_SIZE);

// 4. Fetch the Actual Data
$listStmt = $pdo->prepare(
    "SELECT institute_id, name, shorthand_name, is_active 
     FROM institutes 
     $whereSql 
     ORDER BY is_active DESC, name ASC 
     LIMIT $offset, " . INSTITUTE_PAGE_SIZE
);
$listStmt->execute($params);
$display_institutes = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Safe query builder for pagination (only declare if it doesn't exist)
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
        <span class="table-card-title">Institutes Index</span>
        
        <form method="get" action="catalog-main.php" class="table-card-controls">
            <!-- Keeps us on the institutes tab when filtering -->
            <input type="hidden" name="tab" value="institutes">
            
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Name or Shorthand&hellip;">
            
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
            </select>

            <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
        </form>
    </div>

    <?php if (!$display_institutes): ?>
        <?php $hasFilters = $q !== '' || $status !== ''; ?>
        <div class="empty-state">
            <div class="empty-state__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="10" r="7"></circle>
                    <line x1="21" y1="21" x2="15" y2="15"></line>
                </svg>
            </div>
            <div class="empty-state__title"><?= $hasFilters ? 'No institutes match these filters' : 'No institutes available' ?></div>
            <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Click "Add Institute" to create your first entry.' ?></p>
            <div class="empty-state__action">
                <?php if ($hasFilters): ?>
                    <a href="catalog-main.php?tab=institutes" class="btn btn--secondary btn--sm">Clear filters</a>
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
                        <th>Institute Name</th>
                        <th>Shorthand</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($display_institutes as $inst): ?>
                        <tr>
                            <td>
                                <span class="status-indicator">
                                    <span class="status-dot <?= !empty($inst['is_active']) ? 'active' : 'inactive' ?>"></span>
                                    <?= !empty($inst['is_active']) ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            
                            <td>
                                <span class="badge" style="font-family: monospace;">
                                    <?= str_pad((string) ($inst['institute_id'] ?? 0), 4, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            
                            <td><strong><?= e($inst['name']) ?></strong></td>
                            
                            <td>
                                <?php if (!empty($inst['shorthand_name'])): ?>
                                    <span class="badge badge--neutral"><?= e($inst['shorthand_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-style: italic;">None</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <select class="catalog-actions" style="max-width: 140px" onchange="if(this.value) window.location.href=this.value;">
                                    <option value="">Actions</option>
                                    <option value="/admin/catalog/edit_institute.php?id=<?= (int) $inst['institute_id'] ?>">Edit</option>
                                    <option value="/admin/catalog/catalog_toggle.php?type=institute&id=<?= (int) $inst['institute_id'] ?>&status=<?= $inst['is_active'] ? '0' : '1' ?>&<?= e(catalog_query([])) ?>">
                                      Set as <?= $inst['is_active'] ? 'Inactive' : 'Active' ?>
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