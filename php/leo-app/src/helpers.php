<?php
declare(strict_types=1);

// Global on purpose: views are plain includes, so an unqualified call in a
// template resolves here rather than inside the Leo namespace.

/** Escape for HTML. Every template uses it — never echo raw content. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Render Markdown from admin-authored copy. */
function md(?string $value): string
{
    return Leo\Markdown::render($value);
}

/** Markdown plus its anchored headings — for a page long enough to need an index. */
function md_sections(?string $value): array
{
    return Leo\Markdown::renderSections($value);
}

/**
 * Prefix a stored link with the site's mount point, if it is internal.
 *
 * Admin-entered URLs like an announcement's button link are root-relative
 * ('/recipients'), and need the same prefix a template link gets. External
 * URLs, mailto: and tel: are returned untouched.
 */
function link_url(?string $url, string $basePath): string
{
    $url = (string) $url;
    return ($url !== '' && $url[0] === '/') ? $basePath . $url : $url;
}

/**
 * A cache-busting URL for a static asset.
 *
 * The host sends no Cache-Control for css or js, so a browser caches them
 * heuristically off Last-Modified -- and a file that was a fortnight old when
 * it was fetched stays "fresh" for over a day. After a deploy the server had
 * the new stylesheet and a visitor kept being shown the old one, with no way
 * to tell and nothing a page reload would fix.
 *
 * The version is the CONTENT hash, not the mtime: a deploy rewrites mtimes
 * whether or not the bytes changed, and the two builds must emit the identical
 * string or the cross-build render diff reads it as a divergence. Same bytes,
 * same URL, in both builds and on every server.
 */
function asset_url(string $path, string $basePath): string
{
    static $versions = [];

    if (!array_key_exists($path, $versions)) {
        $root = defined('LEO_PUBLIC_DIR') ? LEO_PUBLIC_DIR : dirname(__DIR__, 2) . '/public_html';
        $file = $root . $path;
        $versions[$path] = is_file($file) ? substr(hash_file('sha256', $file), 0, 10) : '';
    }

    return $basePath . $path . ($versions[$path] !== '' ? '?v=' . $versions[$path] : '');
}

/** Format a stored calendar date for display. */
function fdate(?string $date, bool $short = false): string
{
    return Leo\Schedule::formatDate($date, $short);
}

/** Card-sized excerpt of a recipient story. Returns text/full/truncated. */
function excerpt(mixed $text): array
{
    return Leo\Content::excerpt($text);
}

function money(mixed $value): string
{
    return Leo\Content::formatMoney($value);
}

/** 'selected'/'checked' attribute helpers keep the templates readable. */
function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return !empty($value) ? ' checked' : '';
}
