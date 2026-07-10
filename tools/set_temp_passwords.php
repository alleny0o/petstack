#!/usr/bin/env php
<?php
/**
 * One-time (but safe-to-rerun) setup: sets every account's password
 * to a shared temp password and forces a change on next login. Run
 * once after loading sql/schema.sql + sql/seed.sql.
 *
 * Usage: php tools/set_temp_passwords.php
 */

require __DIR__ . '/../src/db.php';

$tempPassword = 'TempPass123!';
$hash = password_hash($tempPassword, PASSWORD_BCRYPT);

$stmt = get_db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1');
$stmt->execute([$hash]);

echo "Temp password for all accounts: {$tempPassword}\n";
echo "Rows updated: {$stmt->rowCount()}\n";
