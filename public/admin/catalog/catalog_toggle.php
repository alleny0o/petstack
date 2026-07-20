<?php
// Enable error reporting for all types of errors
error_reporting(E_ALL);

// Force PHP to output the errors to the browser
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require __DIR__ . '/../../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../../src/auth.php';
require_role('admin');

$pdo = get_db();

// 1. The Strict Whitelist
// Maps a URL "type" to the actual database table and its primary key column
$allowed_targets = [
    'product'   => ['table' => 'products',   'pk' => 'product_id'],
    'nuclide'   => ['table' => 'nuclides',   'pk' => 'nuclide_name'],
    'institute' => ['table' => 'institutes', 'pk' => 'institute_id'],
    'lab'       => ['table' => 'labs',       'pk' => 'lab_id'],
    'pi'        => ['table' => 'pis',        'pk' => 'pi_id']
];

$type = $_GET['type'] ?? null;
$id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

// 2. Validate Inputs
if (
    $type && array_key_exists($type, $allowed_targets) && 
    $id !== null && $id !== '' && 
    ($new_status === '0' || $new_status === '1')
) {
    // 3. Prepare the dynamic (but 100% safe) query
    $target = $allowed_targets[$type];
    $table = $target['table'];
    $pk = $target['pk'];
    
    // Because $table and $pk come strictly from our hardcoded array, this is safe from SQL Injection.
    // We bind $new_status and $id safely using PDO parameters.
    $stmt = $pdo->prepare("UPDATE {$table} SET is_active = ? WHERE {$pk} = ?");
    $stmt->execute([$new_status, $id]);
}

// 4. Clean up the URL query string
// Remove the action variables so they don't get appended to our redirect URL
unset($_GET['type'], $_GET['id'], $_GET['status']);

// 5. Redirect back to the catalog (with all search and tab filters preserved!)
$query_string = http_build_query($_GET);
header("Location: /admin/catalog-main.php" . ($query_string ? '?' . $query_string : ''));
exit;