<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();
$adminId = (int) $_SESSION['user_id'];

$flash = null;
$rejectErrors = [];
$rejectOld = [];

/**
 * Single-use temp password: doesn't need to satisfy the full strength
 * policy (validate_password_strength()) since it's never kept — the
 * account is forced to change it on first login.
 */
function generate_temp_password(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);

    if ($action === 'approve' && $requestId > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM customer_registration_requests WHERE request_id = ? AND status = 'pending' FOR UPDATE"
            );
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                $pdo->rollBack();
                $flash = ['type' => 'error', 'message' => 'This request has already been reviewed.'];
            } else {
                $tempPassword = generate_temp_password();
                $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

                $pdo->prepare(
                    'INSERT INTO users (username, password_hash, must_change_password, active) VALUES (?, ?, 1, 1)'
                )->execute([$request['email'], $tempHash]);
                $newUserId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO customers
                        (user_id, first_name, last_name, phone, lab_id, supervising_pi_id, registration_status,
                         nrc_contact_name, nrc_contact_phone, nrc_contact_email)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $newUserId,
                    $request['first_name'],
                    $request['last_name'],
                    $request['phone'],
                    $request['lab_id'],
                    $request['pi_id'],
                    'approved',
                    $request['nrc_contact_name'],
                    $request['nrc_contact_phone'],
                    $request['nrc_contact_email'],
                ]);

                // Seeds password_history with the temp password's own hash so
                // it can't be reused as the "new" password on the forced
                // first change (is_password_reused() checks current hash +
                // this history).
                record_password_history($pdo, $newUserId, $tempHash);

                $pdo->prepare(
                    "UPDATE customer_registration_requests
                     SET status = 'approved', reviewed_by_admin_id = ?, reviewed_at = NOW()
                     WHERE request_id = ?"
                )->execute([$adminId, $requestId]);

                $pdo->commit();

                $flash = [
                    'type'         => 'success',
                    'email'        => $request['email'],
                    'tempPassword' => $tempPassword,
                ];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $flash = ['type' => 'error', 'message' => 'Could not create the account. An account for this email may already exist.'];
        }
    } elseif ($action === 'reject' && $requestId > 0) {
        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '') {
            $rejectErrors[$requestId] = 'A reason is required to reject a request.';
            $rejectOld[$requestId] = $reason;
        } else {
            $stmt = $pdo->prepare(
                "UPDATE customer_registration_requests
                 SET status = 'rejected', rejection_reason = ?, reviewed_by_admin_id = ?, reviewed_at = NOW()
                 WHERE request_id = ? AND status = 'pending'"
            );
            $stmt->execute([$reason, $adminId, $requestId]);

            if ($stmt->rowCount() === 0) {
                $flash = ['type' => 'error', 'message' => 'This request has already been reviewed.'];
            } else {
                $flash = ['type' => 'success', 'message' => 'Registration request rejected.'];
            }
        }
    }
}

// Institute is derived via lab_id -> labs.institute_id, per the
// isotope/compound-style "always derive, never duplicate" rule.
$requests = $pdo->query(
    "SELECT r.request_id, r.first_name, r.last_name, r.email, r.phone, r.submitted_at,
            l.lab_name, i.name AS institute_name, p.pi_name
     FROM customer_registration_requests r
     JOIN labs l ON l.lab_id = r.lab_id
     JOIN institutes i ON i.institute_id = l.institute_id
     JOIN pis p ON p.pi_id = r.pi_id
     WHERE r.status = 'pending'
     ORDER BY r.submitted_at DESC"
)->fetchAll();

$pageTitle = 'Registrations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <h1>Registrations</h1>
            </div>

            <?php if ($flash && $flash['type'] === 'success' && isset($flash['tempPassword'])): ?>
                <div class="alert alert--success">
                    Account created for <strong><?= e($flash['email']) ?></strong>.
                    Temporary password: <code><?= e($flash['tempPassword']) ?></code>.
                    Relay this to the applicant manually via NIH email &mdash; it will not be shown again.
                </div>
            <?php elseif ($flash && $flash['type'] === 'success'): ?>
                <div class="alert alert--success"><?= e($flash['message']) ?></div>
            <?php elseif ($flash && $flash['type'] === 'error'): ?>
                <div class="alert alert--error"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Pending Requests</span>
                </div>

                <?php if (!$requests): ?>
                    <div class="table-empty">No pending registrations.</div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Institute</th>
                                    <th>Lab</th>
                                    <th>PI</th>
                                    <th>Phone</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                        <td><?= e($r['email']) ?></td>
                                        <td><?= e($r['institute_name']) ?></td>
                                        <td><?= e($r['lab_name']) ?></td>
                                        <td><?= e($r['pi_name']) ?></td>
                                        <td><?= e($r['phone']) ?></td>
                                        <td><?= e(date('M j, Y g:i A', strtotime($r['submitted_at']))) ?></td>
                                        <td>
                                            <div class="flex gap-2">
                                                <form method="post" onsubmit="return confirm('Approve this registration and create an account?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                                                    <button type="submit" class="btn btn--primary btn--sm">Approve</button>
                                                </form>
                                                <details <?= isset($rejectErrors[$r['request_id']]) ? 'open' : '' ?>>
                                                    <summary class="table-action">Reject</summary>
                                                    <form method="post">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                                                        <div class="field mb-2">
                                                            <label for="reason-<?= (int) $r['request_id'] ?>">Reason</label>
                                                            <textarea id="reason-<?= (int) $r['request_id'] ?>" name="reason" required><?= e($rejectOld[$r['request_id']] ?? '') ?></textarea>
                                                            <?php if (isset($rejectErrors[$r['request_id']])): ?>
                                                                <span class="field-error"><?= e($rejectErrors[$r['request_id']]) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="submit" class="btn btn--danger btn--sm">Confirm Reject</button>
                                                    </form>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
