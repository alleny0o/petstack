<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin'); // directory management is admin-only

$pdo = get_db();

const LABS_DEFAULT_PAGE_SIZE = 20;
const LABS_PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

// One-shot arrival-toast flags set by the PRG redirects below -- same
// convention as nuclides.php / institutes.php.
$justCreated = ($_GET['created'] ?? null) === '1';
$justUpdated = ($_GET['updated'] ?? null) === '1';
$justActivated = ($_GET['activated'] ?? null) === '1';
$justDeactivated = ($_GET['deactivated'] ?? null) === '1';
unset($_GET['created'], $_GET['updated'], $_GET['activated'], $_GET['deactivated']);

$q = trim($_GET['q'] ?? '');
// Status is the DERIVED effective-availability state, same shape as
// products.php: active = l.active AND i.active, unavailable = l.active
// but institute off, inactive = l.active off.
$status = in_array($_GET['status'] ?? '', ['active', 'unavailable', 'inactive'], true) ? $_GET['status'] : '';
$instituteFilter = ctype_digit((string) ($_GET['institute'] ?? '')) ? (int) $_GET['institute'] : 0;
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageSize = in_array((int) ($_GET['page_size'] ?? 0), LABS_PAGE_SIZE_OPTIONS, true)
    ? (int) $_GET['page_size'] : LABS_DEFAULT_PAGE_SIZE;

// Canonicalize so every link built via labs_query() below carries the
// real applied values -- same convention as products.php.
$_GET['status'] = $status;
$_GET['institute'] = $instituteFilter > 0 ? (string) $instituteFilter : '';
$_GET['page'] = (string) $page;
$_GET['page_size'] = (string) $pageSize;

/**
 * Builds a query string from the current GET params with the given
 * overrides applied, dropping empty values. Mirrors products_query() /
 * institutes_query().
 */
function labs_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

/**
 * Shared by create and edit: text-field rules only. No uniqueness -- the
 * labs table has no unique key on lab_name, deliberately not invented at
 * the app level either.
 */
function validate_lab_fields(string $labName, string $building, string $room): array
{
    $errors = [];

    if ($labName === '') {
        $errors['lab_name'] = 'Lab name is required.';
    } elseif (mb_strlen($labName) > 100) {
        $errors['lab_name'] = 'Lab name must be 100 characters or fewer.';
    }

    if (mb_strlen($building) > 50) {
        $errors['building'] = 'Building must be 50 characters or fewer.';
    }
    if (mb_strlen($room) > 20) {
        $errors['room'] = 'Room must be 20 characters or fewer.';
    }

    return $errors;
}

/**
 * Normalizes the submitted PI roster (pi_ids[] checkboxes) to a unique
 * int list and verifies every id is a real PI. Inactive PIs ARE allowed
 * in a roster -- pairing is membership, not availability: an inactive PI
 * stays unselectable on registrations (which gate on pis.active) until
 * reactivated, consistent with the computed-availability model.
 */
function normalize_pi_ids(PDO $pdo, array $raw): array
{
    $piIds = [];
    foreach ($raw as $value) {
        if (ctype_digit((string) $value) && (int) $value > 0) {
            $piIds[(int) $value] = true;
        }
    }
    $piIds = array_keys($piIds);

    if ($piIds) {
        $placeholders = implode(',', array_fill(0, count($piIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pis WHERE pi_id IN ($placeholders)");
        $stmt->execute($piIds);
        if ((int) $stmt->fetchColumn() !== count($piIds)) {
            return ['error' => 'One or more selected PIs no longer exist. Reload and try again.', 'pi_ids' => $piIds];
        }
    }

    return ['error' => null, 'pi_ids' => $piIds];
}

$addErrors = [];
$addOld = ['institute_id' => '', 'lab_name' => '', 'building' => '', 'room' => '', 'active' => '1', 'pi_ids' => []];
$editErrors = [];
$editOld = ['lab_id' => '', 'institute_id' => '', 'lab_name' => '', 'building' => '', 'room' => '', 'pi_ids' => []];
// Per-PI customer counts for the edit modal's roster labels on a
// validation-error reopen (normally supplied via row data attributes).
$editPiCounts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $addOld['institute_id'] = trim($_POST['institute_id'] ?? '');
        $addOld['lab_name'] = trim($_POST['lab_name'] ?? '');
        $addOld['building'] = trim($_POST['building'] ?? '');
        $addOld['room'] = trim($_POST['room'] ?? '');
        $addOld['active'] = trim($_POST['active'] ?? '');

        $addErrors = validate_lab_fields($addOld['lab_name'], $addOld['building'], $addOld['room']);

        $instituteId = ctype_digit($addOld['institute_id']) ? (int) $addOld['institute_id'] : 0;
        if ($instituteId <= 0) {
            $addErrors['institute_id'] = 'Select an institute.';
        } else {
            // Create requires an ACTIVE institute, same rule as
            // products.php's create-under-active-nuclide.
            $stmt = $pdo->prepare('SELECT 1 FROM institutes WHERE institute_id = ? AND active = 1');
            $stmt->execute([$instituteId]);
            if (!$stmt->fetchColumn()) {
                $addErrors['institute_id'] = 'Select a valid institute.';
            }
        }

        if ($addOld['active'] !== '0' && $addOld['active'] !== '1') {
            $addErrors['active'] = 'Select a status.';
        }

        $normalized = normalize_pi_ids($pdo, (array) ($_POST['pi_ids'] ?? []));
        $addOld['pi_ids'] = $normalized['pi_ids'];
        if ($normalized['error'] !== null) {
            $addErrors['pi_ids'] = $normalized['error'];
        }

        if (!$addErrors) {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO labs (institute_id, lab_name, building, room, active) VALUES (?, ?, ?, ?, ?)')
                ->execute([
                    $instituteId,
                    $addOld['lab_name'],
                    $addOld['building'] !== '' ? $addOld['building'] : null,
                    $addOld['room'] !== '' ? $addOld['room'] : null,
                    (int) $addOld['active'],
                ]);
            $newLabId = (int) $pdo->lastInsertId();
            $pairStmt = $pdo->prepare('INSERT INTO lab_pis (lab_id, pi_id) VALUES (?, ?)');
            foreach ($addOld['pi_ids'] as $piId) {
                $pairStmt->execute([$newLabId, $piId]);
            }
            $pdo->commit();
            redirect('/admin/labs.php?' . labs_query(['created' => '1']));
        }
    } elseif ($action === 'update') {
        $editOld['lab_id'] = trim($_POST['lab_id'] ?? '');
        $editOld['institute_id'] = trim($_POST['institute_id'] ?? '');
        $editOld['lab_name'] = trim($_POST['lab_name'] ?? '');
        $editOld['building'] = trim($_POST['building'] ?? '');
        $editOld['room'] = trim($_POST['room'] ?? '');

        $labId = ctype_digit($editOld['lab_id']) ? (int) $editOld['lab_id'] : 0;
        $current = false;
        if ($labId > 0) {
            $stmt = $pdo->prepare('SELECT institute_id FROM labs WHERE lab_id = ?');
            $stmt->execute([$labId]);
            $current = $stmt->fetch();
        }
        if (!$current) {
            $editErrors['lab_id'] = 'Unknown lab.';
        }

        $editErrors += validate_lab_fields($editOld['lab_name'], $editOld['building'], $editOld['room']);

        $instituteId = ctype_digit($editOld['institute_id']) ? (int) $editOld['institute_id'] : 0;
        if ($current) {
            // Institute is freely editable (unlike products.php's
            // lock-once-ordered nuclide): institute is stored exactly once
            // and derived live everywhere by design, so display always
            // reflects current org structure. A CHANGED institute must be
            // active, same rule shape as product edit; keeping the current
            // (possibly inactive) institute is always allowed.
            if ($instituteId <= 0) {
                $editErrors['institute_id'] = 'Select an institute.';
            } elseif ($instituteId !== (int) $current['institute_id']) {
                $stmt = $pdo->prepare('SELECT 1 FROM institutes WHERE institute_id = ? AND active = 1');
                $stmt->execute([$instituteId]);
                if (!$stmt->fetchColumn()) {
                    $editErrors['institute_id'] = 'Select an active institute.';
                }
            }
        }

        $normalized = normalize_pi_ids($pdo, (array) ($_POST['pi_ids'] ?? []));
        $editOld['pi_ids'] = $normalized['pi_ids'];
        if ($normalized['error'] !== null) {
            $editErrors['pi_ids'] = $normalized['error'];
        }

        if (!$editErrors) {
            // Sync lab_pis to the submitted roster in the same transaction
            // as the lab row update. Removals are allowed even for a PI
            // who currently supervises customers here (the checkbox label
            // makes that an informed act); existing customers keep their
            // supervising_pi_id -- customer_detail.php's keep-current rule
            // tolerates a since-removed pairing.
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE labs SET institute_id = ?, lab_name = ?, building = ?, room = ? WHERE lab_id = ?')
                ->execute([
                    $instituteId,
                    $editOld['lab_name'],
                    $editOld['building'] !== '' ? $editOld['building'] : null,
                    $editOld['room'] !== '' ? $editOld['room'] : null,
                    $labId,
                ]);

            $stmt = $pdo->prepare('SELECT pi_id FROM lab_pis WHERE lab_id = ?');
            $stmt->execute([$labId]);
            $currentPiIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $toRemove = array_diff($currentPiIds, $editOld['pi_ids']);
            $toAdd = array_diff($editOld['pi_ids'], $currentPiIds);
            if ($toRemove) {
                $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
                $pdo->prepare("DELETE FROM lab_pis WHERE lab_id = ? AND pi_id IN ($placeholders)")
                    ->execute(array_merge([$labId], array_values($toRemove)));
            }
            $pairStmt = $pdo->prepare('INSERT INTO lab_pis (lab_id, pi_id) VALUES (?, ?)');
            foreach ($toAdd as $piId) {
                $pairStmt->execute([$labId, $piId]);
            }
            $pdo->commit();
            redirect('/admin/labs.php?' . labs_query(['updated' => '1']));
        }

        // Validation-error reopen needs the per-PI customer counts for
        // this lab's roster labels (normally read from the row's data
        // attributes, which this render can't reach via JS).
        if ($labId > 0) {
            $stmt = $pdo->prepare(
                'SELECT supervising_pi_id, COUNT(*) AS c FROM customers
                 WHERE lab_id = ? AND supervising_pi_id IS NOT NULL
                 GROUP BY supervising_pi_id'
            );
            $stmt->execute([$labId]);
            foreach ($stmt->fetchAll() as $row) {
                $editPiCounts[(int) $row['supervising_pi_id']] = (int) $row['c'];
            }
        }
    } elseif ($action === 'toggle_active') {
        $labId = ctype_digit((string) ($_POST['lab_id'] ?? '')) ? (int) $_POST['lab_id'] : 0;
        if ($labId > 0) {
            $stmt = $pdo->prepare('SELECT active FROM labs WHERE lab_id = ?');
            $stmt->execute([$labId]);
            $currentActive = $stmt->fetchColumn();

            if ($currentActive !== false) {
                $newActive = $currentActive ? 0 : 1;
                $pdo->prepare('UPDATE labs SET active = ? WHERE lab_id = ?')
                    ->execute([$newActive, $labId]);
                redirect('/admin/labs.php?' . labs_query([$newActive ? 'activated' : 'deactivated' => '1']));
            }
        }
    }
}

// Base filters (search/institute), WITHOUT the status condition --
// reused for the tab counts, then extended below for the actual list.
$where = [];
$params = [];

if ($q !== '') {
    // Escape LIKE wildcards in the search term itself, same convention
    // as accounts.php / nuclides.php.
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "l.lab_name LIKE ? ESCAPE '\\\\'";
    $params[] = '%' . $escaped . '%';
}
if ($instituteFilter > 0) {
    $where[] = 'l.institute_id = ?';
    $params[] = $instituteFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Three-way DERIVED status, same shape as products.php: a lab with
// active = 1 under a deactivated institute is "unavailable" -- hidden
// from new registrations by the computed-availability rule (see
// register.php), but not "inactive", which always means an admin turned
// the lab itself off.
$derivedStatusSql = "CASE WHEN l.active = 0 THEN 'inactive'
                          WHEN i.active = 0 THEN 'unavailable'
                          ELSE 'active' END";

$countsStmt = $pdo->prepare(
    "SELECT $derivedStatusSql AS derived_status, COUNT(*) AS c
     FROM labs l
     JOIN institutes i ON i.institute_id = l.institute_id
     $whereSql
     GROUP BY derived_status"
);
$countsStmt->execute($params);
$statusCounts = ['active' => 0, 'unavailable' => 0, 'inactive' => 0];
foreach ($countsStmt->fetchAll() as $row) {
    $statusCounts[$row['derived_status']] = (int) $row['c'];
}
$allCount = array_sum($statusCounts);
$totalCount = $status !== '' ? $statusCounts[$status] : $allCount;

$statusTabs = [
    ['value' => '',            'label' => 'All',         'count' => $allCount],
    ['value' => 'active',      'label' => 'Active',      'count' => $statusCounts['active']],
    ['value' => 'unavailable', 'label' => 'Unavailable', 'count' => $statusCounts['unavailable']],
    ['value' => 'inactive',    'label' => 'Inactive',    'count' => $statusCounts['inactive']],
];

$listWhere = $where;
$listParams = $params;
if ($status === 'active') {
    $listWhere[] = 'l.active = 1 AND i.active = 1';
} elseif ($status === 'unavailable') {
    $listWhere[] = 'l.active = 1 AND i.active = 0';
} elseif ($status === 'inactive') {
    $listWhere[] = 'l.active = 0';
}
$listWhereSql = $listWhere ? ('WHERE ' . implode(' AND ', $listWhere)) : '';

$totalPages = max(1, (int) ceil($totalCount / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
// Keep $_GET in sync with the clamped page so labs_query() (and
// $formAction below) never echoes back an out-of-range page number.
$_GET['page'] = (string) $page;

// Management list: institutes joined unfiltered on purpose, i.active
// pulled for the Unavailable treatment. LIMIT/OFFSET interpolation: same
// server-computed-ints convention as the other admin lists.
$listStmt = $pdo->prepare(
    "SELECT l.lab_id, l.lab_name, l.building, l.room, l.active,
            l.institute_id, i.name AS institute_name, i.active AS institute_active,
            (SELECT COUNT(*) FROM lab_pis lp WHERE lp.lab_id = l.lab_id) AS pi_count,
            (SELECT COUNT(*) FROM customers c WHERE c.lab_id = l.lab_id) AS customer_count
     FROM labs l
     JOIN institutes i ON i.institute_id = l.institute_id
     $listWhereSql
     ORDER BY l.lab_name
     LIMIT $offset, $pageSize"
);
$listStmt->execute($listParams);
$labsList = $listStmt->fetchAll();

// Per-row roster data for the shared edit modal: paired PI ids plus the
// per-PI customer counts within that lab (the "supervises N customers
// here" labels), both embedded as JSON data attributes on each Edit
// button. Fetched only for the labs on this page.
$labPiIds = [];
$labPiCustomerCounts = [];
if ($labsList) {
    $pageLabIds = array_map(fn($l) => (int) $l['lab_id'], $labsList);
    $placeholders = implode(',', array_fill(0, count($pageLabIds), '?'));

    $stmt = $pdo->prepare("SELECT lab_id, pi_id FROM lab_pis WHERE lab_id IN ($placeholders)");
    $stmt->execute($pageLabIds);
    foreach ($stmt->fetchAll() as $row) {
        $labPiIds[(int) $row['lab_id']][] = (int) $row['pi_id'];
    }

    $stmt = $pdo->prepare(
        "SELECT lab_id, supervising_pi_id, COUNT(*) AS c FROM customers
         WHERE lab_id IN ($placeholders) AND supervising_pi_id IS NOT NULL
         GROUP BY lab_id, supervising_pi_id"
    );
    $stmt->execute($pageLabIds);
    foreach ($stmt->fetchAll() as $row) {
        $labPiCustomerCounts[(int) $row['lab_id']][(string) $row['supervising_pi_id']] = (int) $row['c'];
    }
}

// Backing data for the filter select and both modals. The Add modal's
// institute select is ACTIVE-only (a lab unavailable from birth is almost
// always a mistake); the filter and Edit selects list ALL institutes,
// inactive ones suffixed -- Edit must render a lab's current, possibly
// inactive institute, and a CHANGED institute is re-checked server-side
// against the active-only rule. The PI roster deliberately lists ALL PIs
// (inactive suffixed): pairing is membership, not availability.
$allInstitutes = $pdo->query('SELECT institute_id, name, active FROM institutes ORDER BY name')->fetchAll();
$activeInstitutes = array_values(array_filter($allInstitutes, fn($i) => $i['active']));
$allPis = $pdo->query('SELECT pi_id, pi_name, active FROM pis ORDER BY pi_name')->fetchAll();

$formAction = '/admin/labs.php';
$currentQueryString = labs_query();
if ($currentQueryString !== '') {
    $formAction .= '?' . $currentQueryString;
}

$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $pageSize, $totalCount);
$hasFilters = $q !== '' || $status !== '' || $instituteFilter > 0;

$pageTitle = 'Labs';
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
                <h1>Labs</h1>
                <div class="page-header__actions">
                    <button type="button" class="btn btn--primary" id="add-lab-btn">+ Lab</button>
                </div>
            </div>

            <?php if ($justCreated): ?>
                <?= toast_flash('success', 'Lab added.') ?>
            <?php elseif ($justUpdated): ?>
                <?= toast_flash('success', 'Lab updated.') ?>
            <?php elseif ($justActivated): ?>
                <?= toast_flash('success', 'Lab activated.') ?>
            <?php elseif ($justDeactivated): ?>
                <?= toast_flash('success', 'Lab deactivated.') ?>
            <?php endif; ?>

            <nav class="status-tabs" aria-label="Filter by status">
                <?php foreach ($statusTabs as $tab): ?>
                    <a href="?<?= e(labs_query(['status' => $tab['value'], 'page' => 1])) ?>" class="status-tabs__link <?= $status === $tab['value'] ? 'is-active' : '' ?>">
                        <?= e($tab['label']) ?> <span class="status-tabs__count"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="table-card">
                <div class="table-card-header">
                    <span class="table-card-title">Labs</span>
                    <form method="get" class="table-card-controls">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">

                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by lab name&hellip;">

                        <select name="institute">
                            <option value="">All institutes</option>
                            <?php foreach ($allInstitutes as $i): ?>
                                <option value="<?= (int) $i['institute_id'] ?>" <?= $instituteFilter === (int) $i['institute_id'] ? 'selected' : '' ?>><?= e($i['name']) ?><?= $i['active'] ? '' : ' (inactive)' ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
                    </form>
                </div>

                <?php if (!$labsList): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <?php if ($hasFilters): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="10" cy="10" r="7"></circle>
                                    <line x1="21" y1="21" x2="15" y2="15"></line>
                                </svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 2v7.31"></path>
                                    <path d="M14 9.3V1.99"></path>
                                    <path d="M8.5 2h7"></path>
                                    <path d="M14 9.3a6.5 6.5 0 1 1-4 0"></path>
                                    <path d="M5.52 16h12.96"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="empty-state__title"><?= $hasFilters ? 'No labs match these filters' : 'No labs yet' ?></div>
                        <p class="empty-state__hint"><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Add a lab so customers can register under it.' ?></p>
                        <div class="empty-state__action">
                            <?php if ($hasFilters): ?>
                                <a href="/admin/labs.php" class="btn btn--secondary btn--sm">Clear filters</a>
                            <?php else: ?>
                                <button type="button" class="btn btn--primary btn--sm" id="add-lab-btn-empty">+ Lab</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Lab</th>
                                    <th>Institute</th>
                                    <th>Building / Room</th>
                                    <th>PIs</th>
                                    <th>Customers</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($labsList as $l): ?>
                                    <?php
                                    $labId = (int) $l['lab_id'];
                                    $buildingRoom = trim(($l['building'] ?? '') . (($l['building'] ?? '') !== '' && ($l['room'] ?? '') !== '' ? ' / ' : '') . ($l['room'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?= e($l['lab_name']) ?></td>
                                        <td class="muted"><?= e($l['institute_name']) ?><?= $l['institute_active'] ? '' : ' <span class="text-sm">(inactive)</span>' ?></td>
                                        <td class="muted"><?= $buildingRoom !== '' ? e($buildingRoom) : '&mdash;' ?></td>
                                        <td class="muted"><?= (int) $l['pi_count'] ?: '&mdash;' ?></td>
                                        <td class="muted"><?= (int) $l['customer_count'] ?: '&mdash;' ?></td>
                                        <?php // Three-way derived status, same treatment as
                                              // products.php's Unavailable rows. ?>
                                        <td>
                                            <?php if (!$l['active']): ?>
                                                <span class="badge badge--inactive">Inactive</span>
                                            <?php elseif (!$l['institute_active']): ?>
                                                <div><span class="badge badge--unavailable">Unavailable</span></div>
                                                <div class="muted text-sm">Institute inactive</div>
                                            <?php else: ?>
                                                <span class="badge badge--active">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-2 justify-end">
                                                <button type="button" class="table-action"
                                                        data-edit-lab
                                                        data-lab-id="<?= $labId ?>"
                                                        data-lab-name="<?= e($l['lab_name']) ?>"
                                                        data-lab-institute-id="<?= (int) $l['institute_id'] ?>"
                                                        data-lab-building="<?= e($l['building'] ?? '') ?>"
                                                        data-lab-room="<?= e($l['room'] ?? '') ?>"
                                                        data-lab-pi-ids="<?= e(json_encode($labPiIds[$labId] ?? [])) ?>"
                                                        data-lab-pi-counts="<?= e(json_encode($labPiCustomerCounts[$labId] ?? new stdClass())) ?>">Edit</button>

                                                <?php if ($l['active']): ?>
                                                    <form method="post" action="<?= e($formAction) ?>"
                                                          data-confirm="Deactivate &ldquo;<?= e($l['lab_name']) ?>&rdquo;? New registrations can no longer select this lab. Its existing customers, orders, delivery locations, and product users are unaffected."
                                                          data-confirm-title="Deactivate lab"
                                                          data-confirm-verb="Deactivate"
                                                          data-confirm-danger>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="lab_id" value="<?= $labId ?>">
                                                        <button type="submit" class="btn btn--danger btn--sm">Deactivate</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" action="<?= e($formAction) ?>"
                                                          data-confirm="Activate &ldquo;<?= e($l['lab_name']) ?>&rdquo;?<?= $l['institute_active'] ? '' : ' Its institute is currently inactive, so it will stay unavailable to new registrations until the institute is reactivated.' ?>"
                                                          data-confirm-title="Activate lab"
                                                          data-confirm-verb="Activate">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="lab_id" value="<?= $labId ?>">
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
                            <form method="get" class="table-card-controls">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input type="hidden" name="institute" value="<?= $instituteFilter > 0 ? $instituteFilter : '' ?>">
                                <input type="hidden" name="page" value="1">
                                <label for="labs-page-size" class="sr-only">Labs per page</label>
                                <select name="page_size" id="labs-page-size" onchange="this.form.submit()">
                                    <?php foreach (LABS_PAGE_SIZE_OPTIONS as $option): ?>
                                        <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="table-pagination__controls">
                            <?php if ($page <= 1): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(labs_query(['page' => $page - 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Previous page">&lsaquo;</a>
                            <?php endif; ?>
                            <form method="get" class="table-card-controls table-pagination__jump">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input type="hidden" name="institute" value="<?= $instituteFilter > 0 ? $instituteFilter : '' ?>">
                                <input type="hidden" name="page_size" value="<?= e((string) $pageSize) ?>">
                                <label for="labs-page-jump" class="sr-only">Go to page</label>
                                <input type="number" name="page" id="labs-page-jump" min="1" max="<?= $totalPages ?>" value="<?= $page ?>">
                                <span class="table-pagination__status">of <?= $totalPages ?></span>
                                <button type="submit" class="btn btn--secondary btn--sm">Go</button>
                            </form>
                            <?php if ($page >= $totalPages): ?>
                                <span class="btn btn--secondary btn--sm" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
                            <?php else: ?>
                                <a href="?<?= e(labs_query(['page' => $page + 1])) ?>" class="btn btn--secondary btn--sm" aria-label="Next page">&rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add modal. The PI roster is this app's ONLY lab_pis
                 management UI (pis.php deliberately has none). -->
            <div class="modal-overlay" id="add-lab-modal" hidden>
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-lab-modal-title">
                    <div class="modal__header">
                        <h2 class="modal__title" id="add-lab-modal-title">Add lab</h2>
                        <button type="button" class="modal__close" data-modal-close aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <form method="post" action="<?= e($formAction) ?>" id="add-lab-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <div class="modal__body">
                            <div class="<?= field_class($addErrors, 'institute_id') ?>">
                                <label for="add-lab-institute">Institute <span class="required-mark">*</span></label>
                                <select id="add-lab-institute" name="institute_id" required data-modal-focus>
                                    <option value="">Select institute&hellip;</option>
                                    <?php foreach ($activeInstitutes as $i): ?>
                                        <option value="<?= (int) $i['institute_id'] ?>" <?= $addOld['institute_id'] === (string) $i['institute_id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($addErrors, 'institute_id') ?>
                            </div>
                            <div class="<?= field_class($addErrors, 'lab_name') ?>">
                                <label for="add-lab-name">Lab name <span class="required-mark">*</span></label>
                                <input type="text" id="add-lab-name" name="lab_name" maxlength="100" value="<?= e($addOld['lab_name']) ?>" required>
                                <?= field_error($addErrors, 'lab_name') ?>
                            </div>
                            <div class="field-row">
                                <div class="<?= field_class($addErrors, 'building') ?>">
                                    <label for="add-lab-building">Building</label>
                                    <input type="text" id="add-lab-building" name="building" maxlength="50" value="<?= e($addOld['building']) ?>">
                                    <?= field_error($addErrors, 'building') ?>
                                </div>
                                <div class="<?= field_class($addErrors, 'room') ?>">
                                    <label for="add-lab-room">Room</label>
                                    <input type="text" id="add-lab-room" name="room" maxlength="20" value="<?= e($addOld['room']) ?>">
                                    <?= field_error($addErrors, 'room') ?>
                                </div>
                            </div>
                            <div class="<?= field_class($addErrors, 'pi_ids') ?>">
                                <label>PIs at this lab</label>
                                <?php if (!$allPis): ?>
                                    <p class="field-hint mb-0">No PIs exist yet &mdash; add them under Directory &rsaquo; PIs first.</p>
                                <?php else: ?>
                                    <?php foreach ($allPis as $pi): ?>
                                        <label class="text-sm">
                                            <input type="checkbox" name="pi_ids[]" value="<?= (int) $pi['pi_id'] ?>" <?= in_array((int) $pi['pi_id'], $addOld['pi_ids'], true) ? 'checked' : '' ?>>
                                            <?= e($pi['pi_name']) ?><?= $pi['active'] ? '' : ' <span class="muted">(inactive)</span>' ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <span class="field-hint">A lab with no PIs can't be chosen on new registrations &mdash; a supervising PI from the lab's roster is required there.</span>
                                <?= field_error($addErrors, 'pi_ids') ?>
                            </div>
                            <?php // No required-mark or required attr on Status: the
                                  // select has no empty option, so it always submits
                                  // a value -- same reasoning as nuclides.php. ?>
                            <div class="<?= field_class($addErrors, 'active') ?>">
                                <label for="add-lab-active">Status</label>
                                <select id="add-lab-active" name="active">
                                    <option value="1" <?= $addOld['active'] === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= $addOld['active'] === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <span class="field-hint">Inactive labs can't be selected on new registrations.</span>
                                <?= field_error($addErrors, 'active') ?>
                            </div>
                        </div>
                        <div class="modal__footer modal__footer--split">
                            <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn--primary">Add Lab</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit modal: single shared modal, populated via JS from the
                 clicked row's data-lab-* attributes (or $editOld on a
                 failed submit). Institute is freely editable -- see the
                 update handler's rationale; the select lists ALL institutes
                 so the current, possibly inactive one renders selected, and
                 a change to an inactive one is rejected server-side. Each
                 PI checkbox's count label ("supervises N customers here")
                 is filled per-lab by JS from data-lab-pi-counts. -->
            <div class="modal-overlay" id="edit-lab-modal" hidden>
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="edit-lab-modal-title">
                    <div class="modal__header">
                        <h2 class="modal__title" id="edit-lab-modal-title">Edit lab</h2>
                        <button type="button" class="modal__close" data-modal-close aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <form method="post" action="<?= e($formAction) ?>" id="edit-lab-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="lab_id" id="edit-lab-id" value="<?= e($editOld['lab_id']) ?>">
                        <div class="modal__body">
                            <div class="<?= field_class($editErrors, 'lab_name') ?>">
                                <label for="edit-lab-name">Lab name <span class="required-mark">*</span></label>
                                <input type="text" id="edit-lab-name" name="lab_name" maxlength="100" value="<?= e($editOld['lab_name']) ?>" required data-modal-focus>
                                <?= field_error($editErrors, 'lab_name') ?>
                            </div>
                            <div class="<?= field_class($editErrors, 'institute_id') ?>">
                                <label for="edit-lab-institute">Institute <span class="required-mark">*</span></label>
                                <select id="edit-lab-institute" name="institute_id" required>
                                    <option value="">Select institute&hellip;</option>
                                    <?php foreach ($allInstitutes as $i): ?>
                                        <option value="<?= (int) $i['institute_id'] ?>" <?= $editOld['institute_id'] === (string) $i['institute_id'] ? 'selected' : '' ?>><?= e($i['name']) ?><?= $i['active'] ? '' : ' (inactive)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="field-hint">Changing the institute updates how this lab displays everywhere, including past orders.</span>
                                <?= field_error($editErrors, 'institute_id') ?>
                            </div>
                            <div class="field-row">
                                <div class="<?= field_class($editErrors, 'building') ?>">
                                    <label for="edit-lab-building">Building</label>
                                    <input type="text" id="edit-lab-building" name="building" maxlength="50" value="<?= e($editOld['building']) ?>">
                                    <?= field_error($editErrors, 'building') ?>
                                </div>
                                <div class="<?= field_class($editErrors, 'room') ?>">
                                    <label for="edit-lab-room">Room</label>
                                    <input type="text" id="edit-lab-room" name="room" maxlength="20" value="<?= e($editOld['room']) ?>">
                                    <?= field_error($editErrors, 'room') ?>
                                </div>
                            </div>
                            <div class="<?= field_class($editErrors, 'pi_ids') ?>">
                                <label>PIs at this lab</label>
                                <?php if (!$allPis): ?>
                                    <p class="field-hint mb-0">No PIs exist yet &mdash; add them under Directory &rsaquo; PIs first.</p>
                                <?php else: ?>
                                    <?php foreach ($allPis as $pi): ?>
                                        <label class="text-sm">
                                            <input type="checkbox" name="pi_ids[]" value="<?= (int) $pi['pi_id'] ?>" data-edit-lab-pi <?= in_array((int) $pi['pi_id'], $editOld['pi_ids'], true) ? 'checked' : '' ?>>
                                            <?= e($pi['pi_name']) ?><?= $pi['active'] ? '' : ' <span class="muted">(inactive)</span>' ?>
                                            <span class="muted" data-pi-count-label></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <span class="field-hint">Unchecking a PI removes them from this lab's roster; customers they already supervise keep their assignment.</span>
                                <?= field_error($editErrors, 'pi_ids') ?>
                            </div>
                        </div>
                        <div class="modal__footer modal__footer--split">
                            <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn--primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
<script src="<?= asset_url('/assets/js/script.js') ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function snapshotForm(form) {
    var values = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      // Two deliberate departures from the other pages' copies of this
      // helper: checkboxes snapshot their CHECKED state (their value
      // attribute never changes, so it can't carry dirtiness), and the
      // key includes the value so same-named pi_ids[] boxes don't
      // overwrite each other's slot.
      if (!el.name) return;
      if (el.type === 'checkbox') {
        values[el.name + ':' + el.value] = String(el.checked);
      } else {
        values[el.name] = el.value;
      }
    });
    return values;
  }

  // ---- Shared dirty-tracking + discard-confirm-on-close wiring, same
  // isDirty() / petcomBeforeClose / petcomConfirm() pattern as
  // nuclides.php / lab_product_users.php -- copied inline per convention. ----
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
  var addModal = document.getElementById('add-lab-modal');
  var addForm = document.getElementById('add-lab-form');
  var addTracking = wireModalDirtyTracking(
    addModal,
    addForm,
    { title: 'Discard this lab?', message: 'Your entries will be discarded.' },
    function () { addForm.reset(); }
  );

  ['add-lab-btn', 'add-lab-btn-empty'].forEach(function (id) {
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

  // ---- Edit modal: population + roster labels + dirty-tracking ----
  var editModal = document.getElementById('edit-lab-modal');
  var editForm = document.getElementById('edit-lab-form');
  var editIdField = document.getElementById('edit-lab-id');
  var editNameField = document.getElementById('edit-lab-name');
  var editInstituteSelect = document.getElementById('edit-lab-institute');
  var editBuildingField = document.getElementById('edit-lab-building');
  var editRoomField = document.getElementById('edit-lab-room');
  var editPiCheckboxes = editModal.querySelectorAll('[data-edit-lab-pi]');
  var editTracking = wireModalDirtyTracking(editModal, editForm, {
    title: 'Discard these changes?',
    message: 'Your edits to this lab will be discarded.'
  });

  function openEditModal(values, opener) {
    editIdField.value = values.lab_id;
    editNameField.value = values.lab_name;
    editInstituteSelect.value = values.institute_id;
    editBuildingField.value = values.building;
    editRoomField.value = values.room;
    editPiCheckboxes.forEach(function (box) {
      box.checked = values.pi_ids.indexOf(parseInt(box.value, 10)) !== -1;
      var count = values.pi_counts[box.value];
      var label = box.parentElement.querySelector('[data-pi-count-label]');
      label.textContent = count ? '— supervises ' + count + ' customer' + (count === 1 ? '' : 's') + ' here' : '';
    });
    window.petcomOpenModal(editModal, { opener: opener || document.activeElement });
    editTracking.markPristine();
  }

  document.querySelectorAll('[data-edit-lab]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      openEditModal({
        lab_id: btn.dataset.labId,
        lab_name: btn.dataset.labName,
        institute_id: btn.dataset.labInstituteId,
        building: btn.dataset.labBuilding,
        room: btn.dataset.labRoom,
        pi_ids: JSON.parse(btn.dataset.labPiIds),
        pi_counts: JSON.parse(btn.dataset.labPiCounts)
      }, e.currentTarget);
    });
  });

  <?php if ($editErrors): ?>
  openEditModal({
    lab_id: <?= json_encode($editOld['lab_id']) ?>,
    lab_name: <?= json_encode($editOld['lab_name']) ?>,
    institute_id: <?= json_encode($editOld['institute_id']) ?>,
    building: <?= json_encode($editOld['building']) ?>,
    room: <?= json_encode($editOld['room']) ?>,
    pi_ids: <?= json_encode($editOld['pi_ids']) ?>,
    pi_counts: <?= json_encode((object) array_combine(array_map('strval', array_keys($editPiCounts)), array_values($editPiCounts))) ?>
  }, null);
  <?php endif; ?>

  // ---- Strip one-time arrival-toast query flags from the URL bar once
  // their toast has been queued -- same fix as nuclides.php. ----
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
</html>
