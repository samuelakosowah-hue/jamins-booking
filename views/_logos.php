<?php
declare(strict_types=1);

/**
 * The Jamin's brand mark, drawn inline so the site stays image-free and works offline.
 * It uses fixed brand colours rather than currentColor — it is a logo, not an icon.
 */

/** Jamin's Nutrition Consult: a figure rising out of a heart, cradled by leaves. */
function logo_jamins(): string
{
    return <<<'SVG'
    <svg viewBox="0 0 48 48" aria-hidden="true">
      <path d="M23 45c-7-1-11.5-5.5-12.5-12.5C17.5 33.5 22 38 23 45z" fill="#2C6B2F"/>
      <path d="M25 45c7-1 11.5-5.5 12.5-12.5C30.5 33.5 26 38 25 45z" fill="#5AA65E"/>
      <path d="M24 39.5S5.5 27 5.5 16.2A9.7 9.7 0 0 1 24 11.6a9.7 9.7 0 0 1 18.5 4.6C42.5 27 24 39.5 24 39.5z" fill="#F26522"/>
      <circle cx="24" cy="14.6" r="3.5" fill="#fff"/>
      <path d="M24 19.6c-3.2 0-5.4 2.2-5.4 5.3v5.4L24 34.4l5.4-4.1v-5.4c0-3.1-2.2-5.3-5.4-5.3z" fill="#fff"/>
    </svg>
    SVG;
}
