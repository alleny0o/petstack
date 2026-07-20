<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

const PRODUCT_USERS_DEFAULT_PAGE_SIZE = 10;
const PRODUCT_USERS_PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

// Pre-setting $labId here means layout_customer.php's guarded lookup
// never re-queries -- same convention as orders.php / order_detail.php /
// lab_delivery_locations.php.
$stmt = $pdo->prepare('SELECT lab_id FROM customers WHERE user_id = ?');
$stmt->execute([$myUserId]);
$labId = (int) ($stmt->fetchColumn() ?: 0);

// One-shot arrival-toast flags set by the PRG redirects below. Captured
// into locals then immediately stripped from $_GET so this render's own
// pagination/search links (built via product_users_query()) never carry a
// stale flag forward. That alone doesn't stop a manual reload of the
// arrived-at URL from re-sending the flag to the server, though -- the
// client-side history.replaceState() call near the bottom of the page
// handles that half, same fix as order_detail.php's/
// lab_delivery_locations.php's identical bug.
$justCreated = ($_GET['created'] ?? null) === '1';
$justUpdated = ($_GET['updated'] ?? null) === '1';
$justActivated = ($_GET['activated'] ?? null) === '1';
$justDeactivated = ($_GET['deactivated'] ?? null) === '1';
unset($_GET['created'], $_GET['updated'], $_GET['activated'], $_GET['deactivated']);

$q = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageSize = in_array((int) ($_GET['page_size'] ?? 0), PRODUCT_USERS_PAGE_SIZE_OPTIONS, true)
    ? (int) $_GET['page_size'] : PRODUCT_USERS_DEFAULT_PAGE_SIZE;

// Canonicalize so every link built via product_users_query() below
// (pagination, and every POST form's action, which embeds the current
// view so a create/edit/toggle redirects back to where the person was)
// carries the real applied values, never raw/invalid ones -- same
// convention as orders.php / lab_delivery_locations.php.
$_GET['page'] = (string) $page;
$_GET['page_size'] = (string) $pageSize;

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values. Two jobs: pagination links,
 * and embedding the current search/page state into every POST form's
 * action so create/edit/toggle_active redirect back to the same view
 * instead of resetting to page 1. Mirrors orders.php's orders_query() /
 * lab_delivery_locations.php's locations_query().
 */
function product_users_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

$addErrors = [];
$addOld = ['first_name' => '', 'last_name' => '', 'email' => ''];
$editErrors = [];
$editOld = ['product_user_id' => '', 'first_name' => '', 'last_name' => '', 'email' => ''];

/**
 * Email is required (not optional, unlike the column's own nullability --
 * the schema stays untouched, this is an app-level rule) and its
 * uniqueness is checked per-lab across ALL rows regardless of active
 * status: a deactivated product user's email still blocks reuse. Matches
 * the DB's own uq_lab_product_users_lab_email composite unique key --
 * this is the app-level pre-check for it, so a collision surfaces as a
 * normal field error instead of a fatal PDO exception. $excludeId is 0 on
 * create (no row to exclude) and the real row's id on edit.
 */
function validate_product_user_fields(PDO $pdo, int $labId, string $firstName, string $lastName, string $email, int $excludeId): array
{
    $errors = [];

    if ($firstName === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (mb_strlen($firstName) > 100) {
        $errors['first_name'] = 'First name must be 100 characters or fewer.';
    }

    if ($lastName === '') {
        $errors['last_name'] = 'Last name is required.';
    } elseif (mb_strlen($lastName) > 100) {
        $errors['last_name'] = 'Last name must be 100 characters or fewer.';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (mb_strlen($email) > 254) {
        $errors['email'] = 'Email must be 254 characters or fewer.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM lab_product_users WHERE lab_id = ? AND email = ? AND product_user_id != ?');
        $stmt->execute([$labId, $email, $excludeId]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'A product user with this email already exists for your lab.';
        }
    }

    return $errors;
}

if ($labId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $addOld['first_name'] = trim($_POST['first_name'] ?? '');
        $addOld['last_name'] = trim($_POST['last_name'] ?? '');
        $addOld['email'] = trim($_POST['email'] ?? '');

        $addErrors = validate_product_user_fields($pdo, $labId, $addOld['first_name'], $addOld['last_name'], $addOld['email'], 0);

        if (!$addErrors) {
            $pdo->prepare('INSERT INTO lab_product_users (lab_id, first_name, last_name, email, active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$labId, $addOld['first_name'], $addOld['last_name'], $addOld['email']]);
            // PRG: redirect after a successful save so a reload doesn't hit
            // the browser's resubmit-form prompt (confirming it would
            // silently re-create the product user) -- same pattern as
            // order_detail.php / lab_delivery_locations.php.
            // product_users_query() carries the current search/page state
            // forward so the person lands back where they were.
            redirect('/customer/lab_product_users.php?' . product_users_query(['created' => '1']));
        }
    } elseif ($action === 'update') {
        $editOld['product_user_id'] = trim($_POST['product_user_id'] ?? '');
        $editOld['first_name'] = trim($_POST['first_name'] ?? '');
        $editOld['last_name'] = trim($_POST['last_name'] ?? '');
        $editOld['email'] = trim($_POST['email'] ?? '');

        $productUserId = ctype_digit($editOld['product_user_id']) ? (int) $editOld['product_user_id'] : 0;

        if ($productUserId <= 0) {
            $editErrors['product_user_id'] = 'Unknown product user.';
        } else {
            $stmt = $pdo->prepare('SELECT product_user_id FROM lab_product_users WHERE product_user_id = ? AND lab_id = ?');
            $stmt->execute([$productUserId, $labId]);
            if (!$stmt->fetchColumn()) {
                $editErrors['product_user_id'] = 'Unknown product user.';
            }
        }

        $editErrors += validate_product_user_fields($pdo, $labId, $editOld['first_name'], $editOld['last_name'], $editOld['email'], $productUserId);

        if (!$editErrors) {
            $pdo->prepare('UPDATE lab_product_users SET first_name = ?, last_name = ?, email = ? WHERE product_user_id = ? AND lab_id = ?')
                ->execute([$editOld['first_name'], $editOld['last_name'], $editOld['email'], $productUserId, $labId]);
            redirect('/customer/lab_product_users.php?' . product_users_query(['updated' => '1']));
        }
    } elseif ($action === 'toggle_active') {
        $productUserId = ctype_digit((string) ($_POST['product_user_id'] ?? '')) ? (int) $_POST['product_user_id'] : 0;
        if ($productUserId > 0) {
            $stmt = $pdo->prepare('SELECT active FROM lab_product_users WHERE product_user_id = ? AND lab_id = ?');
            $stmt->execute([$productUserId, $labId]);
            $currentActive = $stmt->fetchColumn();

            if ($currentActive !== false) {
                $newActive = $currentActive ? 0 : 1;
                $pdo->prepare('UPDATE lab_product_users SET active = ? WHERE product_user_id = ? AND lab_id = ?')
                    ->execute([$newActive, $productUserId, $labId]);
                redirect('/customer/lab_product_users.php?' . product_users_query([$newActive ? 'activated' : 'deactivated' => '1']));
            }
        }
    }
}

// Named $productUsersList (not $productUsers) deliberately: layout_customer.php
// (included below) guards its own New-Order-modal backing data on
// isset($nuclides), not isset($productUsers) -- so a same-named
// $productUsers here would get silently overwritten by
// get_new_order_form_data()'s active-only, no-email/no-active-column
// result (src/helpers.php's get_new_order_form_data()) after this point
// in the request, corrupting this page's own full list. Confirmed via
// grep against layout_customer.php's actual variable assignments before
// naming this -- this exact collision (with $locations) was the root
// cause of every earlier bug on lab_delivery_locations.php.
$productUsersList = [];
$totalCount = 0;
$totalPages = 1;
$offset = 0;

if ($labId > 0) {
    $where = ['lab_id = ?'];
    $params = [$labId];

    if ($q !== '') {
        // Escape LIKE wildcards in the search term itself, same convention
        // as orders.php/accounts.php/customers.php/lab_delivery_locations.php.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where[] = "CONCAT(first_name, ' ', last_name) LIKE ? ESCAPE '\\\\'";
        $params[] = '%' . $escaped . '%';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM lab_product_users $whereSql");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalCount / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    // Keep $_GET in sync with the DB-verified page so product_users_query()
    // (and $formAction below, which embeds it into every POST form) never
    // echoes back an out-of-range page number.
    $_GET['page'] = (string) $page;

    // LIMIT/OFFSET are interpolated directly rather than bound: both are
    // fully server-computed ints at this point (page size is clamped
    // against a fixed option set, offset is derived from a clamped,
    // ctype_digit-checked page number), same convention as orders.php /
    // lab_delivery_locations.php.
    $listStmt = $pdo->prepare(
        "SELECT product_user_id, first_name, last_name, email, active FROM lab_product_users
         $whereSql
         ORDER BY last_name, first_name
         LIMIT $offset, $pageSize"
    );
    $listStmt->execute($params);
    $productUsersList = $listStmt->fetchAll();

    // [PU-DEBUG] Temporary evidence logging -- keep until Allen has
    // confirmed live in the browser that (1) no "Undefined index" notices
    // appear, (2) a newly created product user shows Active immediately,
    // and (3) toggling in either direction never makes a row vanish or
    // mislabel. Do not remove preemptively.
    error_log('[PU-DEBUG] SQL=' . $listStmt->queryString);
    error_log('[PU-DEBUG] row_count=' . count($productUsersList));
    if ($productUsersList) {
        error_log('[PU-DEBUG] first_row=' . print_r($productUsersList[0], true));
    }
}

// Embeds the current search/page/page-size state into every POST form's
// action on this page, computed after the DB-verified page clamp above --
// so create/edit/toggle_active all redirect back to the exact view the
// person was on, not page 1.
$formAction = '/customer/lab_product_users.php';
$currentQueryString = product_users_query();
if ($currentQueryString !== '') {
    $formAction .= '?' . $currentQueryString;
}

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $pageSize, $totalCount);
$hasFilters = $q !== '';

$pageTitle = 'Product Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <div class="page-header">
                <h1>Product Users</h1>
                <?php if ($labId > 0): ?>
                    <div class="page-header__actions">
                        <button type="button" class="btn btn--primary" id="add-product-user-btn">+ Product User</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php // Query flags carry the toast across the PRG redirect --
                  // same convention as order_detail.php's ?placed=1 /
                  // ?cancelled=1 / ?updated=1 / ?notes_updated=1 and
                  // lab_delivery_locations.php's ?created=1 / ?updated=1 /
                  // ?activated=1 / ?deactivated=1. ?>
            <?php if ($labId > 0 && $justCreated): ?>
                <?= toast_flash('success', 'Product user added.') ?>
            <?php elseif ($labId > 0 && $justUpdated): ?>
                <?= toast_flash('success', 'Product user updated.') ?>
            <?php elseif ($labId > 0 && $justActivated): ?>
                <?= toast_flash('success', 'Product user activated.') ?>
            <?php elseif ($labId > 0 && $justDeactivated): ?>
                <?= toast_flash('success', 'Product user deactivated.') ?>
            <?php endif; ?>

            <?php if ($labId <= 0): ?>
                <div class="card">
                    <p class="muted">No lab assigned to your account yet &mdash; contact an administrator.</p>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <span class="table-card-title">Product Users</span>
                        <?php // Explicit Search-button submit, never
                              // live-as-you-type -- same idiom as
                              // orders.php's filter form /
                              // lab_delivery_locations.php's search. ?>
                        <form method="get" class="table-card-controls">
                            <?php // Preserves the current page size across a
                                  // search-form submit -- that form has no
                                  // page_size field of its own, so without
                                  // this hidden input a search would
                                  // silently reset it to the default. ?>
                            <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">
                            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name&hellip;">
                            <button type="submit" class="btn btn--secondary btn--sm">Search</button>
                        </form>
                    </div>

                    <?php if (!$productUsersList): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <?php if ($hasFilters): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="10" cy="10" r="7"></circle>
                                        <line x1="21" y1="21" x2="15" y2="15"></line>
                                    </svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="empty-state__title"><?= $hasFilters ? 'No product users match this search' : 'No product users yet' ?></div>
                            <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search.' : 'Add a product user so orders can be placed on their behalf.' ?></p>
                            <div class="empty-state__action">
                                <?php if ($hasFilters): ?>
                                    <a href="/customer/lab_product_users.php" class="btn btn--secondary btn--sm">Clear filters</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn--primary btn--sm" id="add-product-user-btn-empty">+ Product User</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productUsersList as $pu): ?>
                                        <?php $displayName = $pu['first_name'] . ' ' . $pu['last_name']; ?>
                                        <tr>
                                            <td><?= e($displayName) ?></td>
                                            <td class="muted"><?= e($pu['email']) ?></td>
                                            <td><span class="badge badge--<?= $pu['active'] ? 'active' : 'inactive' ?>"><?= $pu['active'] ? 'Active' : 'Inactive' ?></span></td>
                                            <td>
                                                <div class="flex gap-2 justify-end">
                                                    <button type="button" class="table-action"
                                                            data-edit-product-user
                                                            data-product-user-id="<?= (int) $pu['product_user_id'] ?>"
                                                            data-product-user-first-name="<?= e($pu['first_name']) ?>"
                                                            data-product-user-last-name="<?= e($pu['last_name']) ?>"
                                                            data-product-user-email="<?= e($pu['email']) ?>">Edit</button>

                                                    <?php if ($pu['active']): ?>
                                                        <form method="post" action="<?= e($formAction) ?>"
                                                              data-confirm="Deactivate &ldquo;<?= e($displayName) ?>&rdquo;? They will no longer be selectable on new orders."
                                                              data-confirm-title="Deactivate product user"
                                                              data-confirm-verb="Deactivate"
                                                              data-confirm-danger>
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="toggle_active">
                                                            <input type="hidden" name="product_user_id" value="<?= (int) $pu['product_user_id'] ?>">
                                                            <button type="submit" class="btn btn--danger btn--sm">Deactivate</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="<?= e($formAction) ?>"
                                                              data-confirm="Activate &ldquo;<?= e($displayName) ?>&rdquo;?"
                                                              data-confirm-title="Activate product user"
                                                              data-confirm-verb="Activate">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="toggle_active">
                                                            <input type="hidden" name="product_user_id" value="<?= (int) $pu['product_user_id'] ?>">
                                                            <button type="submit" class="btn btn--secondary btn--sm">Activate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-pagination">
                            <div class="table-pagination__status-group">
                                <span class="table-pagination__status">Showing <?= $rangeStart ?>&ndash;<?= $rangeEnd ?> of <?= $totalCount ?></span>
                                <?php // Standalone form (not the header search
                                      // form) so changing page size never
                                      // also submits unconfirmed search text
                                      // sitting in that other form -- it
                                      // mirrors only the currently-APPLIED
                                      // search via a hidden field,
                                      // auto-submits on change, and always
                                      // resets to page 1, same as
                                      // orders.php / lab_delivery_locations.php. ?>
                                <form method="get" class="table-card-controls">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <input type="hidden" name="page" value="1">
                                    <label for="product-user-page-size" class="sr-only">Product users per page</label>
                                    <select name="page_size" id="product-user-page-size" onchange="this.form.submit()">
                                        <?php foreach (PRODUCT_USERS_PAGE_SIZE_OPTIONS as $option): ?>
                                            <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="table-pagination__controls">
                                <?php if ($page <= 1): ?>
                                    <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
                                <?php else: ?>
                                    <a href="?<?= e(product_users_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                                <?php endif; ?>
                                <?php // Jump-to-page input instead of a
                                      // numbered strip, same reasoning as
                                      // orders.php / lab_delivery_locations.php:
                                      // with page sizes as small as 10, a
                                      // long number list gets impractical
                                      // fast. ?>
                                <form method="get" class="table-card-controls table-pagination__jump">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">
                                    <label for="product-user-page-jump" class="sr-only">Go to page</label>
                                    <input type="number" name="page" id="product-user-page-jump" min="1" max="<?= $totalPages ?>" value="<?= $page ?>">
                                    <span class="table-pagination__status">of <?= $totalPages ?></span>
                                    <button type="submit" class="btn btn--secondary btn--sm">Go</button>
                                </form>
                                <?php if ($page >= $totalPages): ?>
                                    <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
                                <?php else: ?>
                                    <a href="?<?= e(product_users_query(['page' => $page + 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Next page">&rsaquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add modal: header (title + X close) / body / split
                     footer mirrors the New Order modal's visual language
                     (src/partials/new_order_modal.php), just sized for a
                     three-field form instead of copying its near-fullscreen
                     .modal--order treatment -- same pattern as
                     lab_delivery_locations.php's Add modal. -->
                <div class="modal-overlay" id="add-product-user-modal" hidden>
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-product-user-modal-title">
                        <div class="modal__header">
                            <h2 class="modal__title" id="add-product-user-modal-title">Add product user</h2>
                            <button type="button" class="modal__close" data-modal-close aria-label="Close">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <form method="post" action="<?= e($formAction) ?>" id="add-product-user-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create">
                            <div class="modal__body">
                                <div class="<?= field_class($addErrors, 'first_name') ?>">
                                    <label for="add-product-user-first-name">First name <span class="required-mark">*</span></label>
                                    <input type="text" id="add-product-user-first-name" name="first_name" maxlength="100" value="<?= e($addOld['first_name']) ?>" required data-modal-focus>
                                    <?= field_error($addErrors, 'first_name') ?>
                                </div>
                                <div class="<?= field_class($addErrors, 'last_name') ?>">
                                    <label for="add-product-user-last-name">Last name <span class="required-mark">*</span></label>
                                    <input type="text" id="add-product-user-last-name" name="last_name" maxlength="100" value="<?= e($addOld['last_name']) ?>" required>
                                    <?= field_error($addErrors, 'last_name') ?>
                                </div>
                                <div class="<?= field_class($addErrors, 'email') ?>">
                                    <label for="add-product-user-email">Email <span class="required-mark">*</span></label>
                                    <input type="email" id="add-product-user-email" name="email" maxlength="254" value="<?= e($addOld['email']) ?>" required>
                                    <?= field_error($addErrors, 'email') ?>
                                </div>
                            </div>
                            <div class="modal__footer modal__footer--split">
                                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                                <button type="submit" class="btn btn--primary">Add Product User</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit modal: single shared modal for every row, populated
                     via JS from the clicked row's data-product-user-*
                     attributes (or, on a failed submit, from $editOld
                     server-side). -->
                <div class="modal-overlay" id="edit-product-user-modal" hidden>
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="edit-product-user-modal-title">
                        <div class="modal__header">
                            <h2 class="modal__title" id="edit-product-user-modal-title">Edit product user</h2>
                            <button type="button" class="modal__close" data-modal-close aria-label="Close">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <form method="post" action="<?= e($formAction) ?>" id="edit-product-user-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_user_id" id="edit-product-user-id" value="<?= e($editOld['product_user_id']) ?>">
                            <div class="modal__body">
                                <div class="<?= field_class($editErrors, 'first_name') ?>">
                                    <label for="edit-product-user-first-name">First name <span class="required-mark">*</span></label>
                                    <input type="text" id="edit-product-user-first-name" name="first_name" maxlength="100" value="<?= e($editOld['first_name']) ?>" required data-modal-focus>
                                    <?= field_error($editErrors, 'first_name') ?>
                                </div>
                                <div class="<?= field_class($editErrors, 'last_name') ?>">
                                    <label for="edit-product-user-last-name">Last name <span class="required-mark">*</span></label>
                                    <input type="text" id="edit-product-user-last-name" name="last_name" maxlength="100" value="<?= e($editOld['last_name']) ?>" required>
                                    <?= field_error($editErrors, 'last_name') ?>
                                </div>
                                <div class="<?= field_class($editErrors, 'email') ?>">
                                    <label for="edit-product-user-email">Email <span class="required-mark">*</span></label>
                                    <input type="email" id="edit-product-user-email" name="email" maxlength="254" value="<?= e($editOld['email']) ?>" required>
                                    <?= field_error($editErrors, 'email') ?>
                                </div>
                            </div>
                            <div class="modal__footer modal__footer--split">
                                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                                <button type="submit" class="btn btn--primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
<script src="<?= asset_url('/assets/js/script.js') ?>" defer></script>
<?php if ($labId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function snapshotForm(form) {
    var values = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) return;
      values[el.name] = el.value;
    });
    return values;
  }

  // ---- Shared dirty-tracking + discard-confirm-on-close wiring, same
  // isDirty() / petcomBeforeClose / petcomConfirm() pattern as the New
  // Order modal (src/partials/new_order_form.php) and
  // lab_delivery_locations.php's Add/Edit modals, scaled down to a plain
  // POST form. markPristine() must be called every time the modal's
  // fields are (re)populated -- on open and on a validation-error reopen
  // -- so only edits made AFTER that point ever count as dirty. ----
  function wireModalDirtyTracking(overlay, form, discardCopy, onDiscard) {
    var pristineValues = {};

    function isDirty() {
      var now = snapshotForm(form);
      return Object.keys(pristineValues).some(function (name) {
        return now[name] !== pristineValues[name];
      });
    }

    overlay.petcomBeforeClose = function () {
      if (!isDirty()) return true;
      window.petcomConfirm({
        title: discardCopy.title,
        message: discardCopy.message,
        verb: 'Discard',
        danger: true
      }).then(function (discard) {
        if (!discard) return;
        if (onDiscard) onDiscard();
        window.petcomCloseModal(true);
      });
      return false;
    };

    return {
      markPristine: function () { pristineValues = snapshotForm(form); }
    };
  }

  // ---- Add modal ----
  var addModal = document.getElementById('add-product-user-modal');
  var addForm = document.getElementById('add-product-user-form');
  // Discard resets to blank: unlike the Edit modal, Add's fields are never
  // JS-populated -- their rendered value="" already IS the correct
  // pristine state (blank on a fresh load, the attempted values on a
  // validation-error reopen), so form.reset() is safe here.
  var addTracking = wireModalDirtyTracking(
    addModal,
    addForm,
    { title: 'Discard this product user?', message: 'Your entries will be discarded.' },
    function () { addForm.reset(); }
  );

  ['add-product-user-btn', 'add-product-user-btn-empty'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (btn) {
      btn.addEventListener('click', function (e) {
        window.petcomOpenModal(addModal, { opener: e.currentTarget });
        addTracking.markPristine();
      });
    }
  });

  <?php if ($addErrors): ?>
  window.petcomOpenModal(addModal);
  addTracking.markPristine();
  <?php endif; ?>

  // ---- Edit modal: population + dirty-tracking ----
  var editModal = document.getElementById('edit-product-user-modal');
  var editForm = document.getElementById('edit-product-user-form');
  var editIdField = document.getElementById('edit-product-user-id');
  var editFirstNameField = document.getElementById('edit-product-user-first-name');
  var editLastNameField = document.getElementById('edit-product-user-last-name');
  var editEmailField = document.getElementById('edit-product-user-email');
  // No onDiscard reset here: Edit's fields are JS-populated per row, so
  // resetting to the rendered value="" (blank, or a previous error's
  // values) would show stale data instead of the row actually being
  // edited. The next real open always repopulates from fresh data anyway
  // (a row click or a validation-error reopen), so nothing needs undoing.
  var editTracking = wireModalDirtyTracking(editModal, editForm, {
    title: 'Discard these changes?',
    message: 'Your edits to this product user will be discarded.'
  });

  function openEditModal(values, opener) {
    editIdField.value = values.product_user_id;
    editFirstNameField.value = values.first_name;
    editLastNameField.value = values.last_name;
    editEmailField.value = values.email;
    window.petcomOpenModal(editModal, { opener: opener || document.activeElement });
    editTracking.markPristine();
  }

  document.querySelectorAll('[data-edit-product-user]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      openEditModal({
        product_user_id: btn.dataset.productUserId,
        first_name: btn.dataset.productUserFirstName,
        last_name: btn.dataset.productUserLastName,
        email: btn.dataset.productUserEmail
      }, e.currentTarget);
    });
  });

  <?php if ($editErrors): ?>
  openEditModal({
    product_user_id: <?= json_encode($editOld['product_user_id']) ?>,
    first_name: <?= json_encode($editOld['first_name']) ?>,
    last_name: <?= json_encode($editOld['last_name']) ?>,
    email: <?= json_encode($editOld['email']) ?>
  }, null);
  <?php endif; ?>

  // ---- Strip one-time arrival-toast query flags (created/updated/
  // activated/deactivated) from the URL bar once their toast has been
  // queued above, so a reload or back-navigation doesn't re-show a toast
  // for an action that already happened. Same fix as order_detail.php's /
  // lab_delivery_locations.php's identical bug -- PRG already stops the
  // resubmit-form prompt; this separately stops a stale success toast
  // from replaying on a plain GET reload. ----
  var arrivalFlags = ['created', 'updated', 'activated', 'deactivated'];
  var urlParams = new URLSearchParams(window.location.search);
  var hasArrivalFlag = arrivalFlags.some(function (flag) {
    return urlParams.has(flag);
  });
  if (hasArrivalFlag) {
    arrivalFlags.forEach(function (flag) {
      urlParams.delete(flag);
    });
    var cleanedQuery = urlParams.toString();
    var cleanedUrl = window.location.pathname + (cleanedQuery ? '?' + cleanedQuery : '') + window.location.hash;
    history.replaceState(null, '', cleanedUrl);
  }
});
</script>
<?php endif; ?>
</html>
