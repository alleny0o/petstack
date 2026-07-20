<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

// 1. Fetch data to populate the filter dropdowns
// Institutes
$stmtInst =$pdo->query("SELECT institute_id, name FROM institutes WHERE is_active = 1 ORDER BY name");
$institutes =$stmtInst->fetchAll(PDO::FETCH_ASSOC);

// Nuclides (assuming 'nuclides' table as per your earlier code)
$stmtNuc =$pdo->query("SELECT nuclide_name FROM nuclides WHERE is_active = 1 ORDER BY nuclide_name");
$nuclides =$stmtNuc->fetchAll(PDO::FETCH_COLUMN);

// Products
$stmtProd =$pdo->query("SELECT product_id, product_name FROM products ORDER BY product_name");
$products =$stmtProd->fetchAll(PDO::FETCH_ASSOC);
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
                <div>
                    <h1 class="mb-0">Order Reports</h1>
                    <span class="text-sm muted">Export past order data as a CSV</span>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header" style="flex-direction: column; align-items: stretch; gap: var(--sp-1);">
                    <span class="table-card-title mb-0">Report Criteria</span>
                    
                    <form method="GET" action="export_csv.php" id="report-form">
                        
                        <div class="advanced-filters is-open" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                            
                            <div class="filter-group">
                                <label for="start_date">From Date <span style="color: #d9534f;">*</span></label>
                                <input type="date" name="start_date" id="start_date" required>
                            </div>
                            <div class="filter-group">
                                <label for="end_date">To Date <span style="color: #d9534f;">*</span></label>
                                <input type="date" name="end_date" id="end_date" required>
                            </div>

                            <div class="filter-group">
                                <label for="filter-institute">Institute</label>
                                <select name="institute" id="filter-institute">
                                    <option value="all">All Institutes</option>
                                    <?php foreach ($institutes as$inst): ?>
                                        <option value="<?= htmlspecialchars($inst['institute_id']) ?>">
                                            <?= htmlspecialchars($inst['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter-nuclide">Nuclide</label>
                                <select name="nuclide" id="filter-nuclide">
                                    <option value="all">All Nuclides</option>
                                    <?php foreach ($nuclides as$nuc): ?>
                                        <option value="<?= htmlspecialchars($nuc) ?>">
                                            <?= htmlspecialchars($nuc) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter-product">Product</label>
                                <select name="product" id="filter-product">
                                    <option value="all">All Products</option>
                                    <?php foreach ($products as$prod): ?>
                                        <option value="<?= htmlspecialchars($prod['product_id']) ?>">
                                            <?= htmlspecialchars($prod['product_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter-status">Status</label>
                                <select name="status" id="filter-status">
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="ready for pickup">Ready for Pickup</option>
                                    <option value="returned">Returned</option>
                                    <option value="completed">Completed</option>
                                    <option value="canceled">Canceled</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter-chargable">Chargable</label>
                                <select name="chargable" id="filter-chargable">
                                    <option value="all">All</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>

                        <div class="search-bar-top" style="border-top: 1px solid var(--border-color, #eee); padding-top: 15px;">
                            <button type="submit" class="btn btn--primary">Download CSV Report</button>
                            <button type="button" class="btn btn--secondary" id="reset-dates">Reset Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const resetBtn = document.getElementById('reset-dates');

            function setDefaultDates() {
                let today = new Date();
                let lastMonth = new Date();
                lastMonth.setDate(today.getDate() - 30);
                endDateInput.valueAsDate = today;
                startDateInput.valueAsDate = lastMonth;
            }

            setDefaultDates();

            resetBtn.addEventListener('click', function() {
                setDefaultDates();
                // Reset all dropdowns back to 'all'
                document.querySelectorAll('select').forEach(select => select.value = 'all');
            });
        });
    </script>
</body>
</html>