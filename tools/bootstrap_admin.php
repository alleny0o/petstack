#!/usr/bin/env php
<?php
/**
 * One-time production/launch bootstrap: creates exactly one real admin
 * account in an otherwise-empty database. Refuses to run if `users`
 * already has any rows -- this is NOT for local/dev use and does not
 * touch or depend on sql/seed.sql; use that + tools/set_temp_passwords.php
 * for dev/test data instead.
 *
 * Usage: php tools/bootstrap_admin.php <username> <first_name> <last_name>
 *   <username> must be a valid email address (matches the app-wide
 *   username-is-email convention).
 *
 * Run once after loading sql/schema.sql alone, before pointing a real
 * deployment at the database.
 */

require __DIR__ . '/../src/db.php';

/**
 * Deliberately duplicated per-file, not shared into src/helpers.php --
 * same shape as accounts.php / registrations.php / customer_detail.php /
 * account_detail.php's copies.
 */
function generate_temp_password(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
}

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/bootstrap_admin.php <username> <first_name> <last_name>\n");
    exit(1);
}

[, $username, $firstName, $lastName] = $argv;

if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Error: username must be a valid email address.\n");
    exit(1);
}

if ($firstName === '' || $lastName === '') {
    fwrite(STDERR, "Error: first_name and last_name are required.\n");
    exit(1);
}

$pdo = get_db();

$pdo->beginTransaction();
try {
    // Broadest possible guard: abort if the database has ANY accounts at
    // all, dev-seeded or real -- this script is only for a truly empty
    // database. FOR UPDATE closes the race with a concurrent run.
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM users FOR UPDATE')->fetchColumn();
    if ($existing > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "Error: users table already has {$existing} row(s). Refusing to run against a non-empty database.\n");
        exit(1);
    }

    $tempPassword = generate_temp_password();
    $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

    $pdo->prepare(
        'INSERT INTO users (username, password_hash, first_name, last_name, must_change_password, active) VALUES (?, ?, ?, ?, 1, 1)'
    )->execute([$username, $tempHash, $firstName, $lastName]);
    $newUserId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO staff (user_id) VALUES (?)')->execute([$newUserId]);
    $pdo->prepare('INSERT INTO admins (user_id) VALUES (?)')->execute([$newUserId]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error: could not create the admin account: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Admin account created.\n";
echo "Username: {$username}\n";
echo "Temp password: {$tempPassword}\n";
echo "The account must change this password on first login.\n";
