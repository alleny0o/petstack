<?php
/**
 * TEMPORARY demo persistence — $_SESSION stands in for MariaDB until
 * schema + db.php land. Shapes mirror the future tables so the swap is
 * mechanical: rewrite these function bodies as PDO queries and every
 * consumer keeps working. Requires session_start() before use.
 *
 * TODO(db): delete this file once orders live in MariaDB.
 */

/**
 * Compound catalog, keyed by isotope. Mirrors compounds +
 * compound_isotopes + compound_delivery_options.
 */
function demo_catalog(): array
{
    return [
        'F-18' => [
            ['name' => 'FDG',          'type' => 'A', 'leadHours' => 24, 'delivery' => ['Delivery', 'Pick up']],
            ['name' => 'Florbetapir',  'type' => 'A', 'leadHours' => 48, 'delivery' => ['Delivery']],
            ['name' => 'Fluciclovine', 'type' => 'A', 'leadHours' => 48, 'delivery' => ['Delivery', 'Pick up']],
        ],
        'C-11' => [
            ['name' => 'Raclopride', 'type' => 'B', 'leadHours' => 4, 'delivery' => ['Pick up']],
            ['name' => 'PiB',        'type' => 'B', 'leadHours' => 4, 'delivery' => ['Pick up']],
        ],
        'O-15' => [
            ['name' => 'Water', 'type' => 'B', 'leadHours' => 2, 'delivery' => ['Pick up']],
        ],
    ];
}

/** Look up one catalog entry, or null if the pair is invalid. */
function demo_catalog_find(string $isotope, string $compound): ?array
{
    foreach (demo_catalog()[$isotope] ?? [] as $c) {
        if ($c['name'] === $compound) {
            return $c;
        }
    }
    return null;
}

/**
 * All orders, newest first. Seeds sample data on first call so the
 * dashboard is never empty. Shape mirrors orders + order_type_a_details
 * + order_type_b_details.
 */
function demo_orders(): array
{
    if (!isset($_SESSION['demo_orders'])) {
        $_SESSION['demo_orders'] = [
            1042 => [
                'id' => 1042, 'compound' => 'FDG', 'isotope' => 'F-18', 'type' => 'A',
                'status' => 'pending', 'requested' => '2026-07-06 08:00', 'activity' => 15,
                'b_mode' => null, 'b_current' => null, 'b_time' => null,
                'b_activity' => null, 'b_datetime' => null,
                'delivery' => 'Delivery', 'comment' => '', 'placed_at' => '2026-07-01 14:12',
            ],
            1039 => [
                'id' => 1039, 'compound' => 'Florbetapir', 'isotope' => 'F-18', 'type' => 'A',
                'status' => 'accepted', 'requested' => '2026-07-03 10:30', 'activity' => 10,
                'b_mode' => null, 'b_current' => null, 'b_time' => null,
                'b_activity' => null, 'b_datetime' => null,
                'delivery' => 'Delivery', 'comment' => '', 'placed_at' => '2026-06-25 09:41',
            ],
            1035 => [
                'id' => 1035, 'compound' => 'Raclopride', 'isotope' => 'C-11', 'type' => 'B',
                'status' => 'completed', 'requested' => null, 'activity' => null,
                'b_mode' => 'beam', 'b_current' => 40, 'b_time' => 30,
                'b_activity' => null, 'b_datetime' => null,
                'delivery' => 'Pick up', 'comment' => '', 'placed_at' => '2026-06-16 11:02',
            ],
            1031 => [
                'id' => 1031, 'compound' => 'Fluciclovine', 'isotope' => 'F-18', 'type' => 'A',
                'status' => 'canceled', 'requested' => '2026-06-10 14:00', 'activity' => 20,
                'b_mode' => null, 'b_current' => null, 'b_time' => null,
                'b_activity' => null, 'b_datetime' => null,
                'delivery' => 'Pick up', 'comment' => '', 'placed_at' => '2026-06-08 16:30',
            ],
        ];
    }

    $orders = $_SESSION['demo_orders'];
    krsort($orders);
    return array_values($orders);
}

function demo_order_find(int $id): ?array
{
    demo_orders(); // ensure seeded
    return $_SESSION['demo_orders'][$id] ?? null;
}

/** Insert an order, assign the next id (ids always increment, never reused). */
function demo_order_add(array $order): int
{
    demo_orders(); // ensure seeded
    $id = max(array_keys($_SESSION['demo_orders'])) + 1;
    $order['id'] = $id;
    $order['status'] = 'pending';
    $order['placed_at'] = date('Y-m-d H:i');
    $_SESSION['demo_orders'][$id] = $order;
    return $id;
}

/** Cancel an order — only allowed while pending, per business rules. */
function demo_order_cancel(int $id): bool
{
    $order = demo_order_find($id);
    if ($order === null || $order['status'] !== 'pending') {
        return false;
    }
    $_SESSION['demo_orders'][$id]['status'] = 'canceled';
    return true;
}
