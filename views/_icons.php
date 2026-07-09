<?php
declare(strict_types=1);

/** Inline SVG icon set — keeps the page self-contained (no icon font, no CDN). */
function icon(string $name): string
{
    $paths = [
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18" stroke-linecap="round"/>',
        'pin'      => '<path d="M12 22s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="11" r="2.6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2" stroke-linecap="round"/>',
        'check'    => '<path d="M4 12.5 9.5 18 20 6.5" stroke-linecap="round" stroke-linejoin="round"/>',
        'stetho'   => '<path d="M6 3v6a5 5 0 0 0 10 0V3" stroke-linecap="round"/><circle cx="19" cy="15" r="2.6"/><path d="M11 14v2a5 5 0 0 0 8 1" stroke-linecap="round"/>',
        'heart'    => '<path d="M20.8 8.6a5 5 0 0 0-8.8-2.9A5 5 0 0 0 3.2 8.6c0 5 8.8 11.4 8.8 11.4s8.8-6.4 8.8-11.4z"/>',
        // Awareness ribbon: a loop above a crossing point, with two tails below.
        'ribbon'   => '<path d="M12 14C8.2 10.6 8.6 5.2 12 3.4c3.4 1.8 3.8 7.2 0 10.6z"/><path d="M12 14 8.6 21.6M12 14l3.4 7.6" stroke-linecap="round"/>',
        'monitor'  => '<path d="M2 12h5l2-5 3 10 2.5-6 1.5 3h6" stroke-linecap="round" stroke-linejoin="round"/>',
        'droplet'  => '<path d="M12 3s6 6.4 6 10.2A6 6 0 0 1 6 13.2C6 9.4 12 3 12 3z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/>',
        'lock'     => '<rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'download' => '<path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 19h16" stroke-linecap="round"/>',
        'print'    => '<path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="8" rx="2"/><path d="M7 15h10v6H7z"/>',
        'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a1.6 1.6 0 0 1-1.8 1.6A16 16 0 0 1 3.4 5.8 1.6 1.6 0 0 1 5 4z" stroke-linejoin="round"/>',
        'tag'      => '<path d="M3 12.5V4h8.5L21 13.5 13.5 21z" stroke-linejoin="round"/><circle cx="7.5" cy="8" r="1.6"/>',

        // ---- one icon per row of the service price list ----
        'consult'      => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0" stroke-linecap="round"/>',
        'hypertension' => '<path d="M20.4 8.9a4.7 4.7 0 0 0-8.4-2.8A4.7 4.7 0 0 0 3.6 8.9c0 4.7 8.4 10.7 8.4 10.7s8.4-6 8.4-10.7z"/><path d="M6.6 11.8h3l1.6-3 2 5.4 1.4-2.4h2.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'diabetes'     => '<rect x="6" y="3" width="12" height="18" rx="3"/><circle cx="12" cy="9" r="2.2"/><path d="M9 15h6M9 18h6" stroke-linecap="round"/>',
        'combined'     => '<path d="M12 20.5S4 14.8 4 9.9A4.4 4.4 0 0 1 12 7.3 4.4 4.4 0 0 1 20 9.9c0 4.9-8 10.6-8 10.6z"/><path d="M12 3.5v2.6M10.7 4.8h2.6" stroke-linecap="round"/>',
        'weight'       => '<path d="M4 20 6.2 8.6A2 2 0 0 1 8.2 7h7.6a2 2 0 0 1 2 1.6L20 20z" stroke-linejoin="round"/><path d="M12 11v3.4" stroke-linecap="round"/><circle cx="12" cy="4.6" r="1.8"/>',
        'child'        => '<circle cx="12" cy="7" r="3.2"/><path d="M12 10.2v6M8 13.2h8M9.5 21l2.5-4.8L14.5 21" stroke-linecap="round" stroke-linejoin="round"/>',
        'pregnancy'    => '<circle cx="12.4" cy="4.6" r="2.2"/><path d="M12.4 7.2c-2 0-3 1.6-3.2 3.6l-.6 5.2h1.8l.5 4.8h3l.5-4.8" stroke-linejoin="round"/><circle cx="15.6" cy="12.4" r="2.6"/>',
        'counselling'  => '<path d="M3.6 6.4A2 2 0 0 1 5.6 4.4h9.6a2 2 0 0 1 2 2v4.8a2 2 0 0 1-2 2H8l-4.4 3.2z" stroke-linejoin="round"/><path d="M7.6 8h5.6" stroke-linecap="round"/>',
        'bp'           => '<rect x="3.4" y="7" width="12.2" height="10" rx="2.4"/><path d="M6.4 12h1.8l1-2 1.6 4 1-2h1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.4 9.4a4 4 0 0 1 0 5.2" stroke-linecap="round"/><path d="M20.8 7.4a7 7 0 0 1 0 9.2" stroke-linecap="round"/>',
        'sugar'        => '<path d="M12 2.6s5.6 6 5.6 9.6a5.6 5.6 0 0 1-11.2 0C6.4 8.6 12 2.6 12 2.6z"/><path d="M9.6 12.6h4.8M12 10.2v4.8" stroke-linecap="round"/>',
        'bmi'          => '<rect x="5" y="3.4" width="14" height="17.2" rx="2.4"/><path d="M9 3.4h6v2.8H9z"/><path d="M8.6 11h6.8M8.6 15h4.4" stroke-linecap="round"/>',
        'supplement'   => '<rect x="3.6" y="8.8" width="16.8" height="4" rx="2" transform="rotate(-45 12 12)"/><path d="M9.2 9.2 14.8 14.8" stroke-linecap="round"/>',
        'dietplan'     => '<rect x="4.6" y="4" width="14.8" height="17" rx="2.4"/><path d="M9 4h6v2.6H9z"/><path d="m8.6 12.4 1.8 1.8 3.6-3.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.6 17.4h6.8" stroke-linecap="round"/>',
        'followup'     => '<path d="M5 4h3.4l1.8 4.4-2.2 1.4a10.6 10.6 0 0 0 4.6 4.6l1.4-2.2L18.4 14v3.4a1.6 1.6 0 0 1-1.8 1.6A14.6 14.6 0 0 1 3.4 5.8 1.6 1.6 0 0 1 5 4z" stroke-linejoin="round"/>',
    ];

    $body = $paths[$name] ?? '';

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' . $body . '</svg>';
}

/**
 * A single star, filled / half-filled / empty.
 *
 * Half stars use a per-instance gradient id, because several ratings can appear
 * on one page and duplicate ids would make every half-star follow the first.
 */
function star_svg(string $variant = 'empty'): string
{
    static $sequence = 0;

    $path  = 'M12 3.6 14.6 8.9 20.4 9.7 16.2 13.8 17.2 19.6 12 16.9 6.8 19.6 7.8 13.8 3.6 9.7 9.4 8.9z';
    $shell = '<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true">%s</svg>';

    if ($variant === 'full') {
        return sprintf($shell, '<path d="' . $path . '" fill="currentColor"/>');
    }

    if ($variant === 'half') {
        $id = 'halfstar' . (++$sequence);
        return sprintf($shell,
            '<defs><linearGradient id="' . $id . '">'
            . '<stop offset="50%" stop-color="currentColor"/>'
            . '<stop offset="50%" stop-color="transparent"/>'
            . '</linearGradient></defs>'
            . '<path d="' . $path . '" fill="url(#' . $id . ')"/>'
        );
    }

    return sprintf($shell, '<path d="' . $path . '" fill="none"/>');
}
