<?php
// Notice the extra '../' in these paths since we are now inside the catalog/ folder
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $shorthand_name = trim($_POST['shorthand_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // 1. Basic Validation
    if ($name === '') {
        $errors[] = 'Institute name is required.';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Institute name must be 255 characters or less.';
    } else {
        // 2. Duplicate Check (Enforcing the UNIQUE KEY constraint)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM institutes WHERE name = :name");
        $checkStmt->execute([':name' => $name]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = 'An institute with this name already exists.';
        }
    }

    if (strlen($shorthand_name) > 10) {
        $errors[] = 'Shorthand name must be 10 characters or less.';
    }

    // 3. Insert into the database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO institutes (name, shorthand_name, is_active) 
            VALUES (:name, :shorthand_name, :is_active)
        ");
        
        $stmt->execute([
            ':name' => $name,
            // If they left shorthand blank, store it as NULL instead of an empty string
            ':shorthand_name' => $shorthand_name !== '' ? $shorthand_name : null,
            ':is_active' => $is_active
        ]);

        // Redirect UP one directory to the main catalog wrapper
        header("Location: ../catalog-main.php?tab=institutes");
        exit;
    }
}

$pageTitle = 'Add Institute';
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
                <h1>Add New Institute</h1>
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
                        <label for="name">Institute Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= isset($_POST['name']) ? e($_POST['name']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="shorthand_name">Shorthand Name</label>
                        <input type="text" id="shorthand_name" name="shorthand_name" class="form-control" 
                               placeholder="Max 10 chars"
                               value="<?= isset($_POST['shorthand_name']) ? e($_POST['shorthand_name']) : '' ?>"
                               maxlength="10">
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= (!$_POST || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        <label for="is_active">Institute is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=institutes" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Save Institute</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>