<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// Handle Form Submission
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

    // 2. Insert into the database
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO pis (pi_name, email, phone, is_active) 
            VALUES (:pi_name, :email, :phone, :is_active)
        ");
        
        $stmt->execute([
            ':pi_name' => $pi_name,
            ':email'   => $email !== '' ? $email : null,
            ':phone'   => $phone !== '' ? $phone : null,
            ':is_active' => $is_active
        ]);

        header("Location: ../catalog-main.php?tab=pis");
        exit;
    }
}

$pageTitle = 'Add Principal Investigator';
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
                <h1>Add New PI</h1>
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
                               value="<?= isset($_POST['pi_name']) ? e($_POST['pi_name']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?= isset($_POST['phone']) ? e($_POST['phone']) : '' ?>">
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= (!$_POST || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        <label for="is_active">PI is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=pis" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Save PI</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>