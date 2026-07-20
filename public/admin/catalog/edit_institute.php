<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// 1. Validate the Institute ID from the URL
$institute_id = $_GET['id'] ?? null;
if (!$institute_id || !ctype_digit((string)$institute_id)) {
    header("Location: ../catalog-main.php?tab=institutes");
    exit;
}

// 2. Fetch the existing institute
$stmt = $pdo->prepare("SELECT * FROM institutes WHERE institute_id = :id");
$stmt->execute([':id' => $institute_id]);
$institute = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$institute) {
    // If the ID doesn't exist, bounce them back
    header("Location: ../catalog-main.php?tab=institutes");
    exit;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $shorthand_name = trim($_POST['shorthand_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // Basic Validation
    if ($name === '') {
        $errors[] = 'Institute name is required.';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Institute name must be 255 characters or less.';
    } else {
        // Duplicate Check - IMPORTANT: We exclude the current institute's ID from this check!
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM institutes WHERE name = :name AND institute_id != :id");
        $checkStmt->execute([':name' => $name, ':id' => $institute_id]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = 'Another institute with this name already exists.';
        }
    }

    if (strlen($shorthand_name) > 10) {
        $errors[] = 'Shorthand name must be 10 characters or less.';
    }

    // If no errors, update the database
    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE institutes 
            SET name = :name, 
                shorthand_name = :shorthand_name, 
                is_active = :is_active 
            WHERE institute_id = :institute_id
        ");
        
        $updateStmt->execute([
            ':name' => $name,
            ':shorthand_name' => $shorthand_name !== '' ? $shorthand_name : null,
            ':is_active' => $is_active,
            ':institute_id' => $institute_id
        ]);

        header("Location: ../catalog-main.php?tab=institutes");
        exit;
    }
}

// 4. Smart Pre-filling Logic
$val_name = $_POST['name'] ?? $institute['name'];
$val_shorthand = $_POST['shorthand_name'] ?? $institute['shorthand_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_is_active = isset($_POST['is_active']) ? 1 : 0;
} else {
    $val_is_active = $institute['is_active'];
}

$pageTitle = 'Edit Institute';
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
                <h1>Edit Institute</h1>
                <p style="color: var(--color-text-secondary); margin-top: 5px;">
                    ID-<?= str_pad((string)$institute_id, 4, '0', STR_PAD_LEFT) ?>
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
                        <label for="name">Institute Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= e($val_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="shorthand_name">Shorthand Name</label>
                        <input type="text" id="shorthand_name" name="shorthand_name" class="form-control" 
                               placeholder="e.g., UCSF, UCLA (Max 10 chars)"
                               value="<?= e($val_shorthand) ?>"
                               maxlength="10">
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= $val_is_active ? 'checked' : '' ?>>
                        <label for="is_active">Institute is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=institutes" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Update Institute</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>