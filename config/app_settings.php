<?php
// Static, app-wide settings -- a plain PHP file edited directly and
// redeployed (this app has no .env; this is that, in PHP). Not a
// database-backed feature: no table, no admin UI, no audit tracking.
// Read via app_setting() in src/helpers.php.

return [
    'app_name' => 'PETOrders',
];
