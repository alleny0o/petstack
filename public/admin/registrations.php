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

                // No password_history seeding: the temp can't be reused as
                // the "new" password anyway (is_password_reused() checks
                // the current users.password_hash), and history holds
                // outgoing hashes only.

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

// When a reject fails validation, the page re-renders and reopens the
// modal for that request (see the inline script at the bottom).
$rejectRetryId = $rejectErrors ? (int) array_key_first($rejectErrors) : 0;

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
                <div class="temp-password-banner">
                    <div class="temp-password-banner__heading">Account created for <?= e($flash['email']) ?></div>
                    <div>Relay this temporary password to the applicant via NIH email &mdash; it will not be shown again.</div>
                    <div class="temp-password-banner__row">
                        <span class="temp-password-banner__password" id="temp-password-value"><?= e($flash['tempPassword']) ?></span>
                        <button type="button" class="btn btn--secondary btn--sm" data-copy-target="#temp-password-value">Copy</button>
                    </div>
                    <div class="temp-password-banner__warning">Save this now. Leaving or refreshing this page will not bring it back.</div>
                </div>
            <?php elseif ($flash && $flash['type'] === 'success'): ?>
                <?= toast_flash('success', $flash['message']) ?>
            <?php elseif ($flash && $flash['type'] === 'error'): ?>
                <div class="alert alert--error"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Pending Requests</span>
                </div>

                <?php if (!$requests): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <div class="empty-state__title">You're all caught up</div>
                        <p class="empty-state__hint">New self-registration requests will appear here for review.</p>
                    </div>
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
                                    <?php $applicantName = $r['first_name'] . ' ' . $r['last_name']; ?>
                                    <tr>
                                        <td><?= e($applicantName) ?></td>
                                        <td><?= e($r['email']) ?></td>
                                        <td><?= e($r['institute_name']) ?></td>
                                        <td><?= e($r['lab_name']) ?></td>
                                        <td><?= e($r['pi_name']) ?></td>
                                        <td class="tabular"><?= e($r['phone']) ?></td>
                                        <td class="text-sm muted"><?= e(date('M j, Y g:i A', strtotime($r['submitted_at']))) ?></td>
                                        <td>
                                            <div class="flex gap-2 justify-end">
                                                <form method="post"
                                                      data-confirm="Approve <?= e($applicantName) ?>'s registration? This creates their account and a temporary password."
                                                      data-confirm-title="Approve registration"
                                                      data-confirm-verb="Approve">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                                                    <button type="submit" class="btn btn--primary btn--sm">Approve</button>
                                                </form>
                                                <button type="button" class="btn btn--danger btn--sm js-reject-btn"
                                                        data-request-id="<?= (int) $r['request_id'] ?>"
                                                        data-applicant="<?= e($applicantName) ?>">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reject modal: one shared dialog; the clicked row fills in
                 request_id + applicant name. POST semantics are identical
                 to the old inline <details> form. -->
            <div class="modal-overlay" id="reject-modal" hidden>
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" id="reject-request-id" value="<?= $rejectRetryId ?>">
                        <div class="modal__body">
                            <h2 class="modal__title" id="reject-modal-title">Reject registration</h2>
                            <p class="modal__message">Rejecting <strong id="reject-applicant-name">this request</strong>. The applicant sees your reason on the status page and may submit a new registration.</p>
                            <div class="<?= $rejectErrors ? 'field field--invalid' : 'field' ?> mb-0">
                                <label for="reject-reason">Reason <span class="required-mark">*</span></label>
                                <textarea id="reject-reason" name="reason" required data-modal-focus><?= e($rejectErrors ? (string) reset($rejectOld) : '') ?></textarea>
                                <?php if ($rejectErrors): ?>
                                    <span class="field-error"><?= e((string) reset($rejectErrors)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal__footer">
                            <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn--danger-solid">Reject request</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('reject-modal');
  var requestIdInput = document.getElementById('reject-request-id');
  var applicantLabel = document.getElementById('reject-applicant-name');

  document.querySelectorAll('.js-reject-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      requestIdInput.value = btn.dataset.requestId;
      applicantLabel.textContent = btn.dataset.applicant;
      window.petcomOpenModal(modal, { opener: btn });
    });
  });

  <?php if ($rejectErrors): ?>
  // Server-side validation failed — reopen the dialog with the error.
  (function () {
    var btn = document.querySelector('.js-reject-btn[data-request-id="<?= $rejectRetryId ?>"]');
    if (btn) { applicantLabel.textContent = btn.dataset.applicant; }
    window.petcomOpenModal(modal, { opener: btn || undefined });
  })();
  <?php endif; ?>
});
</script>
</html>
