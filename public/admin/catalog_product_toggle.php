<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

$id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

if ($id && ($new_status === '0' || $new_status === '1')) {
    $stmt = $pdo->prepare("UPDATE compounds SET active = ? WHERE compound_id = ?");
    $stmt->execute([$new_status, $id]);
}

// Redirect back to the catalog with the same filters
header("Location: catalog.php?" . http_build_query($_GET));
exit;