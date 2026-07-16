<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// Allowed values for the database enum
$allowed_delivery_options = ['delivery', 'pickup', 'pharmacy'];

// 1. Fetch active nuclides for the dropdown
// Assuming 'active' was also renamed to 'is_active' in the nuclides table for consistency
$nuclidesStmt = $pdo->query("SELECT nuclide_name FROM nuclides WHERE is_active = 1 ORDER BY nuclide_name ASC");
$active_nuclides = $nuclidesStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $nuclide_name = trim($_POST['nuclide_name'] ?? '');
    $delivery_option = trim($_POST['default_delivery_option'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // Basic Validation
    if ($product_name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($nuclide_name === '') {
        $errors[] = 'You must select a nuclide.';
    }
    if (!in_array($delivery_option, $allowed_delivery_options)) {
        $errors[] = 'You must select a valid default delivery option.';
    }

    // If no errors, insert into the database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO products (nuclide_name, product_name, default_delivery_option, is_active) 
            VALUES (:nuclide_name, :product_name, :default_delivery_option, :is_active)
        ");
        
        $stmt->execute([
            ':nuclide_name' => $nuclide_name,
            ':product_name' => $product_name,
            ':default_delivery_option' => $delivery_option,
            ':is_active' => $is_active
        ]);

        // Redirect back to catalog after successful creation
        header("Location: catalog.php");
        exit;
    }
}

$pageTitle = 'Add Product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
    <!-- Ensure your external CSS file is linked here -->
    <link rel="stylesheet" href="/assets/css/components/product_form.css">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            
            <div class="page-header">
                <h1>Add New Product</h1>
            </div>

            <div class="form-card">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    
                    <div class="form-group">
                        <label for="product_name">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" class="form-control" 
                               value="<?= isset($_POST['product_name']) ? e($_POST['product_name']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nuclide_name">Nuclide *</label>
                        <select id="nuclide_name" name="nuclide_name" class="form-control" required>
                            <option value="">-- Select a Nuclide --</option>
                            <?php foreach ($active_nuclides as $n_name): ?>
                                <option value="<?= e($n_name) ?>" 
                                    <?= (isset($_POST['nuclide_name']) && $_POST['nuclide_name'] === $n_name) ? 'selected' : '' ?>>
                                    <?= e($n_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="default_delivery_option">Default Delivery Option *</label>
                        <select id="default_delivery_option" name="default_delivery_option" class="form-control" required>
                            <option value="">-- Select a Delivery Option --</option>
                            <option value="delivery" <?= (isset($_POST['default_delivery_option']) && $_POST['default_delivery_option'] === 'delivery') ? 'selected' : '' ?>>Delivery</option>
                            <option value="pickup" <?= (isset($_POST['default_delivery_option']) && $_POST['default_delivery_option'] === 'pickup') ? 'selected' : '' ?>>Pickup</option>
                            <option value="pharmacy" <?= (isset($_POST['default_delivery_option']) && $_POST['default_delivery_option'] === 'pharmacy') ? 'selected' : '' ?>>Pharmacy</option>
                        </select>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active">Product is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="catalog.php" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Save Product</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>