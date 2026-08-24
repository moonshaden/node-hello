<?php
declare(strict_types=1);

namespace Leo;

/**
 * View-model layer: turns stored records into what the templates render.
 *
 * Everything time-sensitive resolves against a single $today, so a page can
 * never show one section as open and another as closed, and the whole site can
 * be previewed at any date.
 */
final class Content
{
    /** A scholarship's own window, or the site-wide period it inherits. */
    public static function windowFor(array $scholarship, array $enrollment): array
    {
        $own = $scholarship['window'] ?? null;
        if (!is_array($own) || ($own['type'] ?? 'inherit') === 'inherit') {
            return $enrollment;
        }
        return $own;
    }

    public static function resolveScholarship(array $scholarship, array $enrollment, string $today): array
    {
        $spec = self::windowFor($scholarship, $enrollment);
        $window = Schedule::resolveWindow($spec, $today);

        $own = $scholarship['window'] ?? null;
        $inherits = !is_array($own) || ($own['type'] ?? 'inherit') === 'inherit';

        // array_merge, not +: the resolved window must overwrite the stored spec.
        return array_merge($scholarship, [
            'window' => $window,
            'inheritsWindow' => $inherits,
            'isOpen' => $window['state'] === 'open',
            'statusLabel' => Schedule::describeWindow($window),
            'published' => Schedule::isPublished($scholarship, $today),
        ]);
    }

    /**
     * Scholarships for the public site.
     *
     * An award stays listed year-round by default — students research these
     * months before they can apply — and only disappears when an admin ticks
     * "hide while closed".
     */
    public static function publicScholarships(Store $store, string $today, bool $includeHidden = false): array
    {
        $items = [];
        foreach ($store->list('scholarships') as $scholarship) {
            $resolved = self::resolveScholarship($scholarship, $store->enrollment(), $today);
            $visible = $resolved['published'] && (empty($resolved['hideWhenClosed']) || $resolved['isOpen']);
            if ($includeHidden || $visible) {
                $items[] = $resolved;
            }
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['isOpen'] !== $b['isOpen']) {
                return $a['isOpen'] ? -1 : 1;
            }
            $aFeatured = !empty($a['featured']);
            $bFeatured = !empty($b['featured']);
            if ($aFeatured !== $bFeatured) {
                return $aFeatured ? -1 : 1;
            }
            return 0;
        });

        return $items;
    }

    public static function openScholarships(array $scholarships): array
    {
        return array_values(array_filter($scholarships, static fn (array $s) => $s['isOpen']));
    }

    /** Awarded students, newest class first — the site's headline content. */
    public static function publicRecipients(Store $store, string $today, bool $includeHidden = false): array
    {
        $items = [];
        foreach ($store->list('recipients') as $recipient) {
            if ($includeHidden || Schedule::isPublished($recipient, $today)) {
                $items[] = $recipient;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $byYear = strcmp((string) ($b['year'] ?? ''), (string) ($a['year'] ?? ''));
            if ($byYear !== 0) {
                return $byYear;
            }
            $aFeatured = !empty($a['featured']);
            $bFeatured = !empty($b['featured']);
            if ($aFeatured !== $bFeatured) {
                return $aFeatured ? -1 : 1;
            }
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $items;
    }

    public static function groupRecipientsByYear(array $recipients): array
    {
        $groups = [];
        foreach ($recipients as $recipient) {
            $year = (string) ($recipient['year'] ?? '');
            $groups[$year][] = $recipient;
        }
        // A numeric-looking key becomes an int in PHP, so cast back: the year
        // is compared strictly against a string from the query string.
        return array_map(
            static fn ($year, $items) => ['year' => (string) $year, 'items' => $items],
            array_keys($groups),
            array_values($groups)
        );
    }

    public static function featuredRecipients(array $recipients, int $limit = 3): array
    {
        $featured = array_values(array_filter($recipients, static fn (array $r) => !empty($r['featured'])));
        return array_slice($featured !== [] ? $featured : $recipients, 0, $limit);
    }

    /**
     * Announcements visible right now.
     *
     * Besides show-from/show-until dates, an announcement can be tied to the
     * enrollment period itself, so the "apply now" and "reopens in November"
     * banners swap over every year with nothing for staff to remember.
     */
    public static function activeAnnouncements(Store $store, string $today, string $enrollmentState): array
    {
        $items = [];
        foreach ($store->list('announcements') as $item) {
            if (!Schedule::isPublished($item, $today)) {
                continue;
            }
            $showWhen = $item['showWhen'] ?? '';
            if ($showWhen === 'open' && $enrollmentState !== 'open') {
                continue;
            }
            if ($showWhen === 'closed' && $enrollmentState === 'open') {
                continue;
            }
            $items[] = $item;
        }
        return $items;
    }

    public static function publicPages(Store $store, string $today, bool $includeHidden = false): array
    {
        $items = [];
        foreach ($store->list('pages') as $page) {
            if ($includeHidden || Schedule::isPublished($page, $today)) {
                $items[] = $page;
            }
        }
        return $items;
    }

    public static function navPages(array $pages): array
    {
        return array_values(array_filter($pages, static fn (array $p) => !empty($p['inNav'])));
    }

    /** Totals for the impact band — computed, so they cannot drift from the data. */
    public static function awardStats(array $recipients): array
    {
        $total = 0;
        $years = [];
        foreach ($recipients as $recipient) {
            $total += (int) ($recipient['amount'] ?? 0);
            if (!empty($recipient['year'])) {
                $years[(string) $recipient['year']] = true;
            }
        }
        $yearList = array_map(strval(...), array_keys($years));
        sort($yearList, SORT_STRING);

        return [
            'recipientCount' => count($recipients),
            'totalAwarded' => $total,
            'yearCount' => count($yearList),
            'latestYear' => $yearList === [] ? null : end($yearList),
        ];
    }

    public static function formatMoney(mixed $value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        if ($number === 0.0) {
            return '';
        }
        return '$' . number_format($number, 0, '.', ',');
    }
    /**
     * Trim a recipient story down to a card-sized excerpt.
     *
     * The published bios run from 334 to 1,271 characters, which left one card
     * in a row roughly four times the height of its neighbour. Cutting at a
     * sentence end keeps the excerpt readable; the full text is still in the
     * store, and the card offers it behind a disclosure.
     *
     * Multibyte throughout: several bios use curly quotes and en dashes.
     */
    public const EXCERPT_LIMIT = 520;

    public static function excerpt(mixed $text, int $limit = self::EXCERPT_LIMIT): array
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        if (mb_strlen($clean) <= $limit) {
            return ['text' => $clean, 'full' => $clean, 'truncated' => false];
        }

        $head = mb_substr($clean, 0, $limit);
        $sentence = max(
            (int) mb_strrpos($head, '. '),
            (int) mb_strrpos($head, '! '),
            (int) mb_strrpos($head, '? ')
        );
        // Only respect a sentence end past the halfway mark, or a single long
        // opening sentence would cut the excerpt down to almost nothing.
        $space = (int) mb_strrpos($head, ' ');
        $atSentence = $sentence > intdiv($limit, 2);
        $cut = $atSentence ? $sentence + 1 : $space;
        $trimmed = preg_replace('/[\s,;:]+$/u', '', mb_substr($clean, 0, $cut > 0 ? $cut : $limit));

        // A sentence end already closes the excerpt; an ellipsis after the full
        // stop just reads as ".…". Only a mid-sentence cut needs one.
        return [
            'text' => $atSentence ? $trimmed : $trimmed . '…',
            'full' => $clean,
            'truncated' => true,
        ];
    }
}
