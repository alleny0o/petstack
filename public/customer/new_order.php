<?php
// POST-only JSON endpoint behind the New Order modal
// (src/partials/new_order_modal.php) -- the form submits via fetch and
// reads the JSON result; there is no standalone page render. A stray GET
// (stale bookmark/history entry from when this was a full page) lands on
// the dashboard instead.
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/customer/dashboard.php');
}

verify_csrf();

/**
 * Emits a JSON response and stops. Success: {ok:true, redirect}.
 * Failure: {ok:false, errors:{field: message}} and/or {ok:false, message}
 * with a non-200 status.
 */
function order_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT lab_id FROM customers WHERE user_id = ?');
$stmt->execute([$myUserId]);
$labId = (int) ($stmt->fetchColumn() ?: 0);

if ($labId <= 0) {
    // The modal renders a "no lab assigned" notice instead of the form in
    // this state, so a POST here means a stale session or tampering.
    order_json_response(
        ['ok' => false, 'message' => 'No lab assigned to your account yet — contact an administrator.'],
        422
    );
}

$input = [
    'nuclide_id'      => '',
    'product_id'      => '',
    'activity_mci'    => '',
    'requested_date'  => '',
    'requested_time'  => '',
    'notes'           => '',
    'location_id'     => '',
    'product_user_id' => '',
];
foreach ($input as $key => $_) {
    $input[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

// The whole validation chain (nuclide active -> product resolution ->
// activity -> date/time -> notes length -> location
// required-iff-direct_delivery -> lab-scoped location/product user)
// lives in validate_order_input() (helpers.php), shared with the
// pending-order edit form on customer/order_detail.php.
$validation = validate_order_input($pdo, $input, $labId);

if ($validation['errors']) {
    order_json_response(['ok' => false, 'errors' => $validation['errors']], 422);
}

$values = $validation['values'];

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO orders
            (customer_id, product_id, location_id, product_user_id,
             activity_mci, requested_datetime, notes, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
    )->execute([
        $myUserId,
        $values['product_id'],
        $values['location_id'],
        $values['product_user_id'],
        $values['activity_mci'],
        $values['requested_datetime'],
        $values['notes'] !== '' ? $values['notes'] : null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO order_audit_log (order_id, status_from, status_to, changed_by_user_id)
         VALUES (?, NULL, 'pending', ?)"
    )->execute([$orderId, $myUserId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

// ?placed=1 is order_detail.php's arrival-toast contract (kept compatible
// with that page's pending rebuild / future print view).
order_json_response(['ok' => true, 'redirect' => '/customer/order_detail.php?id=' . $orderId . '&placed=1']);
