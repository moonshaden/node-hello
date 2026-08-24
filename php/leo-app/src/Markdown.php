<?php
declare(strict_types=1);

namespace Leo;

/**
 * A small Markdown subset — headings, lists, emphasis, links, paragraphs.
 *
 * Hand-written rather than pulled from Packagist because the hosting account
 * has no shell, so there is no Composer to install a library with. The subset
 * covers everything the admin forms actually need.
 */
final class Markdown
{
    public static function render(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $lines = preg_split('/\R/', str_replace("\r\n", "\n", $value)) ?: [];
        $html = '';
        $listType = null;

        $closeList = static function () use (&$listType, &$html): void {
            if ($listType !== null) {
                $html .= "</$listType>\n";
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.*)$/', $trimmed, $match)) {
                $closeList();
                $level = strlen($match[1]) + 1; // '#' becomes h2 — h1 is the page title
                $level = min($level, 6);
                $html .= "<h$level>" . self::inline($match[2]) . "</h$level>\n";
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $match)) {
                if ($listType !== 'ul') {
                    $closeList();
                    $html .= "<ul>\n";
                    $listType = 'ul';
                }
                $html .= '<li>' . self::inline($match[1]) . "</li>\n";
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $match)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $html .= "<ol>\n";
                    $listType = 'ol';
                }
                $html .= '<li>' . self::inline($match[1]) . "</li>\n";
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $match)) {
                $closeList();
                $html .= '<blockquote><p>' . self::inline($match[1]) . "</p></blockquote>\n";
                continue;
            }

            $closeList();
            $html .= '<p>' . self::inline($trimmed) . "</p>\n";
        }

        $closeList();
        return $html;
    }

    /** Markdown with no block wrapper — for one-line summaries. */
    public static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Links: only http, https and mailto, so a pasted javascript: URL cannot execute.
        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\(((?:https?:\/\/|mailto:|\/)[^\s)]+)\)/',
            static fn (array $m) => '<a href="' . $m[2] . '">' . $m[1] . '</a>',
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<![\*\w])\*([^*]+)\*(?!\*)/s', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;

        return $escaped;
    }
}
