<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// Allowed values for the database enum
$allowed_delivery_options = ['delivery', 'pickup', 'pharmacy'];

// 1. Validate the Product ID from the URL
$product_id = $_GET['id'] ?? null;
if (!$product_id || !ctype_digit((string)$product_id)) {
    // If no valid ID is provided, bounce them back to the catalog
    header("Location: catalog.php");
    exit;
}

// 2. Fetch the existing product
$stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    // If the ID doesn't exist in the database, bounce them back
    header("Location: catalog.php");
    exit;
}

// 3. Handle Form Submission (The Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $nuclide_name = trim($_POST['nuclide_name'] ?? '');
    $delivery_option = trim($_POST['delivery_option'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // Basic Validation
    if ($product_name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($nuclide_name === '') {
        $errors[] = 'You must select a nuclide.';
    }
    if (!in_array($delivery_option, $allowed_delivery_options)) {
        $errors[] = 'You must select a valid delivery option.';
    }

    // If no errors, update the database
    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET nuclide_name = :nuclide_name, 
                product_name = :product_name, 
                delivery_option = :delivery_option, 
                is_active = :is_active 
            WHERE product_id = :product_id
        ");
        
        $updateStmt->execute([
            ':nuclide_name' => $nuclide_name,
            ':product_name' => $product_name,
            ':delivery_option' => $delivery_option,
            ':is_active' => $is_active,
            ':product_id' => $product_id
        ]);

        // Redirect back to catalog after successful update
        header("Location: catalog.php");
        exit;
    }
}

// 4. Fetch nuclides for the dropdown
// We pull active nuclides OR the current product's nuclide (in case the nuclide was retired but the product still uses it)
$nuclidesStmt = $pdo->prepare("SELECT nuclide_name FROM nuclides WHERE is_active = 1 OR nuclide_name = :current ORDER BY nuclide_name ASC");
$nuclidesStmt->execute([':current' => $product['nuclide_name']]);
$active_nuclides = $nuclidesStmt->fetchAll(PDO::FETCH_COLUMN);

// 5. Smart Pre-filling Logic
// If the form was submitted (and failed validation), use POST data so they don't lose their edits. 
// If it's a fresh page load, use the database data.
$val_product_name = $_POST['product_name'] ?? $product['product_name'];
$val_nuclide_name = $_POST['nuclide_name'] ?? $product['nuclide_name'];
$val_delivery_option = $_POST['delivery_option'] ?? $product['delivery_option'];

// Checkboxes are tricky: if POST occurred, it exists if checked. If no POST, use the database.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_is_active = isset($_POST['is_active']) ? 1 : 0;
} else {
    $val_is_active = $product['is_active'];
}

$pageTitle = 'Edit Product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
    <link rel="stylesheet" href="/assets/css/components/product_form.css">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            
            <div class="page-header">
                <h1>Edit Product</h1>
                <!-- Handy badge showing the admin which ID they are editing -->
                <p style="color: var(--color-text-secondary); margin-top: 5px;">
                    <?= str_pad((string)$product_id, 4, '0', STR_PAD_LEFT) ?>
                </p>
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
                               value="<?= e($val_product_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nuclide_name">Nuclide *</label>
                        <select id="nuclide_name" name="nuclide_name" class="form-control" required>
                            <option value="">-- Select a Nuclide --</option>
                            <?php foreach ($active_nuclides as $n_name): ?>
                                <option value="<?= e($n_name) ?>" 
                                    <?= $val_nuclide_name === $n_name ? 'selected' : '' ?>>
                                    <?= e($n_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="delivery_option">Delivery Option *</label>
                        <select id="delivery_option" name="delivery_option" class="form-control" required>
                            <option value="">-- Select a Delivery Option --</option>
                            <option value="delivery" <?= $val_delivery_option === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                            <option value="pickup" <?= $val_delivery_option === 'pickup' ? 'selected' : '' ?>>Pickup</option>
                            <option value="pharmacy" <?= $val_delivery_option === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                        </select>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= $val_is_active ? 'checked' : '' ?>>
                        <label for="is_active">Product is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="catalog.php" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Update Product</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>