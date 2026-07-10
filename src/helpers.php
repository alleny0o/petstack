<?php
/**
 * Session bootstrap, CSRF tokens, HTML escaping, redirects.
 */

require_once __DIR__ . '/config.php';

/**
 * Starts the session with hardened cookie flags. Every page must call
 * this instead of a bare session_start() so HttpOnly/Secure are never
 * missed (see CLAUDE.md page template).
 */
function bootstrap_session(): void
{
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => REQUIRE_SECURE_COOKIES,
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Emits a script tag that pops a toast once the page has loaded.
 * Used for transient success feedback on pages that re-render after
 * a POST (this app doesn't redirect-after-POST); persistent messages
 * (errors, temp passwords) stay as inline .alert markup instead.
 * json_encode with the HEX flags makes the values safe to embed in
 * an inline <script> (no </script> or quote breakouts).
 */
function toast_flash(string $type, string $message): string
{
    $args = json_encode(
        [$type, $message],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );

    return '<script>document.addEventListener("DOMContentLoaded",function(){'
        . 'window.showToast.apply(null,' . $args . ');});</script>';
}

/**
 * Renders the inline error message(s) for one form field, or nothing
 * when the field is clean. Values may be a string or a list of
 * strings (e.g. several password-strength failures on one field).
 */
function field_error(array $fieldErrors, string $key): string
{
    if (!isset($fieldErrors[$key])) {
        return '';
    }

    $html = '';
    foreach ((array) $fieldErrors[$key] as $message) {
        $html .= '<span class="field-error">' . e($message) . '</span>';
    }
    return $html;
}

/**
 * Class list for a .field wrapper: adds the invalid modifier (red
 * border + red label via CSS) when the field has an error.
 */
function field_class(array $fieldErrors, string $key, string $base = 'field'): string
{
    return $base . (isset($fieldErrors[$key]) ? ' field--invalid' : '');
}

/**
 * Builds a display name like "Alice Carter" from a customer's
 * first/last name. Falls back to the username when the customer has no
 * name on file (not-yet-approved rows) or the joined row belongs to a
 * non-customer (staff/admin authors on a shared comment thread).
 */
function customer_display_name(?string $firstName, ?string $lastName, string $usernameFallback): string
{
    if ($firstName === null || $firstName === '' || $lastName === null || $lastName === '') {
        return $usernameFallback;
    }

    return $firstName . ' ' . $lastName;
}
