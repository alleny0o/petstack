<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// 1. Validate the Nuclide ID (which is the name) from the URL
$old_nuclide_name = $_GET['id'] ?? null;
if (!$old_nuclide_name) {
    header("Location: ../catalog-main.php?tab=nuclides");
    exit;
}

// 2. Fetch the existing nuclide
$stmt = $pdo->prepare("SELECT * FROM nuclides WHERE nuclide_name = :id");
$stmt->execute([':id' => $old_nuclide_name]);
$nuclide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nuclide) {
    // If the nuclide doesn't exist, bounce them back
    header("Location: ../catalog-main.php?tab=nuclides");
    exit;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['nuclide_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // Basic Validation
    if ($new_name === '') {
        $errors[] = 'Nuclide name is required.';
    } elseif (strlen($new_name) > 30) {
        $errors[] = 'Nuclide name must be 30 characters or less.';
    } else {
        // Duplicate Check - ONLY run this if they are actually changing the name!
        if (strtolower($new_name) !== strtolower($old_nuclide_name)) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM nuclides WHERE nuclide_name = :name");
            $checkStmt->execute([':name' => $new_name]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = 'Another nuclide with this name already exists.';
            }
        }
    }

    // If no errors, update the database
    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE nuclides 
                SET nuclide_name = :new_name, 
                    is_active = :is_active 
                WHERE nuclide_name = :old_name
            ");
            
            $updateStmt->execute([
                ':new_name' => $new_name,
                ':is_active' => $is_active,
                ':old_name' => $old_nuclide_name
            ]);

            header("Location: ../catalog-main.php?tab=nuclides");
            exit;
            
        } catch (PDOException $e) {
            // Error 23000 is a Foreign Key Integrity Violation. 
            // It means products are relying on the old name, and the database refused to break the link.
            if ($e->getCode() == 23000) {
                $errors[] = "Cannot change this nuclide's name because it is currently linked to active products. You must reassign or delete those products first.";
            } else {
                // Catch-all for any other database errors
                $errors[] = "A database error occurred: " . $e->getMessage();
            }
        }
    }
}

// 4. Smart Pre-filling Logic
$val_name = $_POST['nuclide_name'] ?? $nuclide['nuclide_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_is_active = isset($_POST['is_active']) ? 1 : 0;
} else {
    $val_is_active = $nuclide['is_active'];
}

$pageTitle = 'Edit Nuclide';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../../src/partials/head.php'; ?>
    <link rel="stylesheet" href="/assets/css/components/product_form.css">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            
            <div class="page-header">
                <h1>Edit Nuclide</h1>
                <p style="color: var(--color-text-secondary); margin-top: 5px;">
                    Currently editing: <strong><?= e($old_nuclide_name) ?></strong>
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
                        <label for="nuclide_name">Nuclide Name *</label>
                        <input type="text" id="nuclide_name" name="nuclide_name" class="form-control" 
                               value="<?= e($val_name) ?>" required>
                        <p style="color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 5px;">
                            Warning: Changing this name will affect all database records linked to it.
                        </p>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= $val_is_active ? 'checked' : '' ?>>
                        <label for="is_active">Nuclide is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=nuclides" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Update Nuclide</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>