<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

$id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

if ($id && ($new_status === '0' || $new_status === '1')) {
    $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE product_id = ?");
    $stmt->execute([$new_status, $id]);
}

// 1. Remove the action parameters so they don't clutter the URL on redirect
unset($_GET['id'], $_GET['status']);

// 2. Build the clean query string with just the search filters (page, q, nuclide, etc.)
$query_string = http_build_query($_GET);

// 3. Redirect back to the correct ADMIN catalog page
$redirect_url = "catalog.php" . ($query_string ? '?' . $query_string : '');
header("Location: " . $redirect_url);
exit;