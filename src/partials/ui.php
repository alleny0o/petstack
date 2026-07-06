<?php
/**
 * Presentation helpers — HTML-emitting only, no data access.
 */

/**
 * Render an isotope string ('F-18') as a chart-of-nuclides tile:
 * mass number superscripted before the element symbol (¹⁸F).
 * Falls back to plain text inside the tile if the string doesn't
 * split into element-mass.
 */
function ui_nuclide(string $isotope, bool $large = false): string
{
    $class = 'nuclide' . ($large ? ' nuclide--lg' : '');
    $parts = explode('-', $isotope, 2);
    if (count($parts) !== 2) {
        return '<span class="' . $class . '">' . htmlspecialchars($isotope) . '</span>';
    }
    return '<span class="' . $class . '" title="' . htmlspecialchars($isotope) . '">'
        . '<sup>' . htmlspecialchars($parts[1]) . '</sup>'
        . htmlspecialchars($parts[0])
        . '</span>';
}
