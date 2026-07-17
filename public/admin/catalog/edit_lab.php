<?php
require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

// 1. Validate the Lab ID from the URL
$lab_id = $_GET['id'] ?? null;
if (!$lab_id || !ctype_digit((string)$lab_id)) {
    header("Location: ../catalog-main.php?tab=labs");
    exit;
}

// 2. Fetch the existing lab
$stmt = $pdo->prepare("SELECT * FROM labs WHERE lab_id = :id");
$stmt->execute([':id' => $lab_id]);
$lab = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lab) {
    header("Location: ../catalog-main.php?tab=labs");
    exit;
}

// 3. Fetch currently assigned PIs to pre-select them in the dropdown
$piStmt = $pdo->prepare("SELECT pi_id FROM lab_pis WHERE lab_id = :id");
$piStmt->execute([':id' => $lab_id]);
$current_pi_ids = $piStmt->fetchAll(PDO::FETCH_COLUMN);

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_name = trim($_POST['lab_name'] ?? '');
    $institute_id = trim($_POST['institute_id'] ?? '');
    $pi_ids = $_POST['pi_ids'] ?? []; // Array of selected PI IDs
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

    // If no errors, update the database using a Transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Step 1: Update the main labs table
            $updateLabStmt = $pdo->prepare("
                UPDATE labs 
                SET institute_id = :institute_id, 
                    lab_name = :lab_name, 
                    is_active = :is_active 
                WHERE lab_id = :lab_id
            ");
            $updateLabStmt->execute([
                ':institute_id' => $institute_id,
                ':lab_name' => $lab_name,
                ':is_active' => $is_active,
                ':lab_id' => $lab_id
            ]);

            // Step 2: Clear existing PI relationships for this lab
            $deletePisStmt = $pdo->prepare("DELETE FROM lab_pis WHERE lab_id = :lab_id");
            $deletePisStmt->execute([':lab_id' => $lab_id]);

            // Step 3: Insert the newly selected PI relationships
            if (!empty($pi_ids)) {
                $insertPiStmt = $pdo->prepare("INSERT INTO lab_pis (lab_id, pi_id) VALUES (:lab_id, :pi_id)");
                foreach ($pi_ids as $pi_id) {
                    $insertPiStmt->execute([
                        ':lab_id' => $lab_id,
                        ':pi_id'  => $pi_id
                    ]);
                }
            }

            $pdo->commit();
            header("Location: ../catalog-main.php?tab=labs");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "A database error occurred: " . $e->getMessage();
        }
    }
}

// 5. Fetch dropdown options (Including currently selected ones even if they were marked inactive!)
$instListStmt = $pdo->prepare("SELECT institute_id, name, shorthand_name FROM institutes WHERE is_active = 1 OR institute_id = :current ORDER BY name ASC");
$instListStmt->execute([':current' => $lab['institute_id']]);
$institutes = $instListStmt->fetchAll(PDO::FETCH_ASSOC);

// For PIs, we grab active ones, plus any inactive ones that are currently assigned to this lab
$piListStmt = $pdo->prepare("
    SELECT pi_id, pi_name 
    FROM pis 
    WHERE is_active = 1 OR pi_id IN (SELECT pi_id FROM lab_pis WHERE lab_id = :current_lab) 
    ORDER BY pi_name ASC
");
$piListStmt->execute([':current_lab' => $lab_id]);
$pis = $piListStmt->fetchAll(PDO::FETCH_ASSOC);


// 6. Smart Pre-filling Logic
$val_name = $_POST['lab_name'] ?? $lab['lab_name'];
$val_institute = $_POST['institute_id'] ?? $lab['institute_id'];

// Checkboxes and multi-selects need special POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_is_active = isset($_POST['is_active']) ? 1 : 0;
    $val_pis = $_POST['pi_ids'] ?? [];
} else {
    $val_is_active = $lab['is_active'];
    $val_pis = $current_pi_ids;
}

$pageTitle = 'Edit Lab';
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
                <h1>Edit Lab</h1>
                <p style="color: var(--color-text-secondary); margin-top: 5px;">
                    ID-<?= str_pad((string)$lab_id, 4, '0', STR_PAD_LEFT) ?>
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
                        <label for="lab_name">Lab Name *</label>
                        <input type="text" id="lab_name" name="lab_name" class="form-control" 
                               value="<?= e($val_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="institute_id">Affiliated Institute *</label>
                        <select id="institute_id" name="institute_id" class="form-control" required>
                            <option value="">-- Select an Institute --</option>
                            <?php foreach ($institutes as $inst): ?>
                                <?php $displayName = $inst['shorthand_name'] ? $inst['shorthand_name'] : $inst['name']; ?>
                                <option value="<?= e($inst['institute_id']) ?>" 
                                    <?= $val_institute == $inst['institute_id'] ? 'selected' : '' ?>>
                                    <?= e($displayName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pi_ids">Principal Investigators (Optional)</label>
                        <select id="pi_ids" name="pi_ids[]" class="form-control" multiple style="height: 120px;">
                            <?php foreach ($pis as $pi): ?>
                                <option value="<?= e($pi['pi_id']) ?>"
                                    <?= in_array($pi['pi_id'], $val_pis) ? 'selected' : '' ?>>
                                    <?= e($pi['pi_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="color: var(--color-text-secondary); font-size: 0.85rem; margin-top: 5px;">
                            Hold <strong>Cmd</strong> (Mac) or <strong>Ctrl</strong> (Windows) to select multiple PIs.
                        </p>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" id="is_active" name="is_active" <?= $val_is_active ? 'checked' : '' ?>>
                        <label for="is_active">Lab is Active</label>
                    </div>

                    <div class="form-actions">
                        <a href="../catalog-main.php?tab=labs" class="btn btn--secondary">Cancel</a>
                        <button type="submit" class="btn btn--primary">Update Lab</button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script src="/assets/js/script.js" defer></script>
</body>
</html>