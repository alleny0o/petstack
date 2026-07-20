<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

// 1. Database Connection (Replace with your actual credentials)
$pdo = get_db();

// 2. Get Filters from the Frontend
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;
$status     = $_GET['status'] ?? 'all';
$institute  = $_GET['institute'] ?? 'all';
$nuclide    = $_GET['nuclide'] ?? 'all';
$product    = $_GET['product'] ?? 'all';
$chargable  = $_GET['chargable'] ?? 'all';

if (!$start_date || !$end_date) {
    die("Please provide a valid date range.");
}

$start_datetime = $start_date . " 00:00:00";
$end_datetime   = $end_date . " 23:59:59";

// 3. Prepare the Base SQL Query
$sql = "SELECT 
            o.order_id, 
            o.created_at, 
            i.name AS institute_name, 
            p.nuclide_name, 
            p.product_name, 
            o.chargable, 
            o.cancelation_notes 
        FROM orders o
        LEFT JOIN customers c ON o.created_by = c.user_id
        LEFT JOIN labs l ON c.lab_id = l.lab_id
        LEFT JOIN institutes i ON l.institute_id = i.institute_id
        LEFT JOIN products p ON o.product_id = p.product_id
        WHERE o.created_at BETWEEN :start_date AND :end_date";

// 4. Dynamically Append Filters if they aren't set to "all"
if ($status !== 'all') {
    $sql .= " AND o.status = :status";
}
if ($institute !== 'all') {
    $sql .= " AND i.institute_id = :institute";
}
if ($nuclide !== 'all') {
    $sql .= " AND p.nuclide_name = :nuclide";
}
if ($product !== 'all') {
    $sql .= " AND p.product_id = :product";
}
if ($chargable !== 'all') {
    $sql .= " AND o.chargable = :chargable";
}

$sql .= " ORDER BY o.created_at DESC";

// 5. Prepare and Bind Parameters
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':start_date', $start_datetime);
$stmt->bindParam(':end_date', $end_datetime);

// Bind the optional parameters conditionally
if ($status !== 'all')    $stmt->bindParam(':status', $status);
if ($institute !== 'all') $stmt->bindParam(':institute', $institute);
if ($nuclide !== 'all')   $stmt->bindParam(':nuclide', $nuclide);
if ($product !== 'all')   $stmt->bindParam(':product', $product);
if ($chargable !== 'all') $stmt->bindParam(':chargable', $chargable);

$stmt->execute();
$orders = $stmt->fetchAll();

// 6. Set Headers to force CSV Download
$filename = "pet_orders_report_" . date('Y-m-d') . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 7. Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// 8. Write the Column Headers to the CSV
fputcsv($output, ['Order ID', 'Order Date (Y/M/D)', 'Institute', 'Nuclide', 'Product', 'Chargable', 'Cancelation Notes']);

// 9. Write the Data Rows
foreach ($orders as $row) {
    // Format the date to Y/M/D exactly as requested
    $formatted_date = date('Y/m/d', strtotime($row['created_at']));
    
    // Convert the boolean/tinyint to Yes/No for easier reading
    $chargable_text = ($row['chargable'] == 1) ? 'Yes' : 'No';

    // Output the row
    fputcsv($output, [
        $row['order_id'],
        $formatted_date,
        $row['institute_name'] ?? 'N/A', // Fallback if no location found
        $row['nuclide_name'] ?? 'N/A',   // Fallback if no nuclide found
        $row['product_name'] ?? 'N/A',
        $chargable_text,
        $row['cancelation_notes']
    ]);
}

// Close the file pointer
fclose($output);
exit();
?>