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

/** Format a stored calendar date for display. */
function fdate(?string $date, bool $short = false): string
{
    return Leo\Schedule::formatDate($date, $short);
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
