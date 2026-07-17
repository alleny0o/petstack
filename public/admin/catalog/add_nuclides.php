<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuclide_name = trim($_POST['nuclide_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // 1. Basic Validation
    if ($nuclide_name === '') {
        $errors[] = 'Nuclide name is required.';
    } elseif (strlen($nuclide_name) > 30) {
        $errors[] = 'Nuclide name must be 30 characters or less.';
    } else {
        // 2. Duplicate Check
        // Since nuclide_name is the Primary Key, it must be unique.
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM nuclides WHERE nuclide_name = :name");
        $checkStmt->execute([':name' => $nuclide_name]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = 'A nuclide with this name already exists. Please enter a unique name.';
        }
    }

    // 3. Insert into the database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO nuclides (nuclide_name, is_active) 
            VALUES (:nuclide_name, :is_active)
        ");
        
        $stmt->execute([
            ':nuclide_name' => $nuclide_name,
            ':is_active' => $is_active
        ]);

        // Redirect back to the nuclides tab after successful creation
        header("Location: catalog-main.php?tab=nuclides");
        exit;
    }
}

$pageTitle = 'Add Nuclide';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../../src/partials/head.php'; ?>
    <!-- Reusing the exact same CSS file we used for the product form! -->
    <link rel="stylesheet" href="/assets/css/components/product_form.css">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            
            <div class="page-header">
                <h1>Add New Nuclide</h1>
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
                        <label for="nuclide_name">Nuclide Name *</label>
                        <input type="text" id="nuclide_name" name="nuclide_name" class="form-control" 
                               placeholder="e.g., F-18 or C-11"
                               value="<?= isset($_POST['nuclide_name']) ? e($_POST['nuclide_name']) : '' ?>" required>
                        <p style="color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 5px;">
                            Must be unique. Maximum 30 characters.
                        </p>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= (!$_POST || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        <label for="is_active">Nuclide is Active</label>
                    </div>

                    <div class="form-actions">
                        <!-- Safely cancel back to the nuclides tab -->
                        <a href="../catalog-main.php?tab=nuclides" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Save Nuclide</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>