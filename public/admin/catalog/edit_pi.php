<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// 1. Validate the PI ID from the URL
$pi_id = $_GET['id'] ?? null;
if (!$pi_id || !ctype_digit((string)$pi_id)) {
    header("Location: ../catalog-main.php?tab=pis");
    exit;
}

// 2. Fetch the existing PI
$stmt = $pdo->prepare("SELECT * FROM pis WHERE pi_id = :id");
$stmt->execute([':id' => $pi_id]);
$pi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pi) {
    // If the ID doesn't exist, bounce them back
    header("Location: ../catalog-main.php?tab=pis");
    exit;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pi_name = trim($_POST['pi_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // 1. Basic Validation
    if ($pi_name === '') {
        $errors[] = 'PI name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // 2. If no errors, update the database
    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE pis 
            SET pi_name = :pi_name, 
                email = :email, 
                phone = :phone, 
                is_active = :is_active
            WHERE pi_id = :pi_id
        ");
        
        $updateStmt->execute([
            ':pi_name' => $pi_name,
            ':email'   => $email !== '' ? $email : null,
            ':phone'   => $phone !== '' ? $phone : null,
            ':is_active' => $is_active,
            ':pi_id'     => $pi_id
        ]);

        header("Location: ../catalog-main.php?tab=pis");
        exit;
    }
}




// 4. Smart Pre-filling Logic
$val_name = $_POST['pi_name'] ?? $pi['pi_name'];
$val_email = $_POST['email'] ?? $pi['email'];
$val_phone = $_POST['phone'] ?? $pi['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_is_active = isset($_POST['is_active']) ? 1 : 0;
} else {
    $val_is_active = $nuclide['is_active'];
}



$pageTitle = 'Edit PI Information';
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
                <h1>Edit PI</h1>
                <p style="color: var(--color-text-secondary); margin-top: 5px;">
                    ID-<?= str_pad((string)$pi_id, 4, '0', STR_PAD_LEFT) ?>
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
                        <label for="pi_name">Full Name *</label>
                        <input type="text" id="pi_name" name="pi_name" class="form-control" 
                            value="<?= e($val_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                            value="<?= e($val_email) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                            value="<?= e($val_phone) ?>" required>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= (!$_POST || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        <label for="is_active">PI is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=pis" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Update PI Information</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>