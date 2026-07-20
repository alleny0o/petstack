<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// 1. Fetch active Institutes for the dropdown
$instStmt = $pdo->query("SELECT institute_id, name, shorthand_name FROM institutes WHERE is_active = 1 ORDER BY name ASC");
$active_institutes = $instStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch active PIs for the multi-select dropdown
$piStmt = $pdo->query("SELECT pi_id, pi_name FROM pis WHERE is_active = 1 ORDER BY pi_name ASC");
$active_pis = $piStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_name = trim($_POST['lab_name'] ?? '');
    $institute_id = trim($_POST['institute_id'] ?? '');
    $pi_ids = $_POST['pi_ids'] ?? []; // This will be an array because of the multi-select
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    // Basic Validation
    if ($lab_name === '') {
        $errors[] = 'Lab name is required.';
    } elseif (strlen($lab_name) > 100) {
        $errors[] = 'Lab name must be 100 characters or less.';
    }
    
    if ($institute_id === '') {
        $errors[] = 'You must select an affiliated institute.';
    }

    // Insert into the database
    if (empty($errors)) {
        try {
            // Start a transaction so both inserts succeed, or neither do
            $pdo->beginTransaction();

            // Insert the main Lab record
            $stmt = $pdo->prepare("
                INSERT INTO labs (institute_id, lab_name, is_active) 
                VALUES (:institute_id, :lab_name, :is_active)
            ");
            
            $stmt->execute([
                ':institute_id' => $institute_id,
                ':lab_name' => $lab_name,
                ':is_active' => $is_active
            ]);

            // Grab the ID of the lab we just created
            $new_lab_id = $pdo->lastInsertId();

            // Insert the PI relationships into the lab_pis junction table
            if (!empty($pi_ids)) {
                $piStmt = $pdo->prepare("INSERT INTO lab_pis (lab_id, pi_id) VALUES (:lab_id, :pi_id)");
                foreach ($pi_ids as $pi_id) {
                    $piStmt->execute([
                        ':lab_id' => $new_lab_id,
                        ':pi_id'  => $pi_id
                    ]);
                }
            }

            // Commit the transaction
            $pdo->commit();

            header("Location: ../catalog-main.php?tab=labs");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "A database error occurred: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Lab';
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
                <h1>Add New Lab</h1>
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
                        <label for="lab_name">Lab Name *</label>
                        <input type="text" id="lab_name" name="lab_name" class="form-control" 
                               placeholder="e.g., Smith Lab"
                               value="<?= isset($_POST['lab_name']) ? e($_POST['lab_name']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="institute_id">Affiliated Institute *</label>
                        <select id="institute_id" name="institute_id" class="form-control" required>
                            <option value="">-- Select an Institute --</option>
                            <?php foreach ($active_institutes as $inst): ?>
                                <?php $displayName = $inst['shorthand_name'] ? $inst['shorthand_name'] : $inst['name']; ?>
                                <option value="<?= e($inst['institute_id']) ?>" 
                                    <?= (isset($_POST['institute_id']) && $_POST['institute_id'] == $inst['institute_id']) ? 'selected' : '' ?>>
                                    <?= e($displayName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pi_ids">Principal Investigators (Optional)</label>
                        <!-- The empty brackets [] in the name attribute tell PHP to expect an array! -->
                        <select id="pi_ids" name="pi_ids[]" class="form-control" multiple style="height: 120px;">
                            <?php foreach ($active_pis as $pi): ?>
                                <option value="<?= e($pi['pi_id']) ?>"
                                    <?= (isset($_POST['pi_ids']) && in_array($pi['pi_id'], $_POST['pi_ids'])) ? 'selected' : '' ?>>
                                    <?= e($pi['pi_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 5px;">
                            Hold <strong>Cmd</strong> (Mac) or <strong>Ctrl</strong> (Windows) to select multiple PIs.
                        </p>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= (!$_POST || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        <label for="is_active">Lab is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=labs" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Save Lab</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>