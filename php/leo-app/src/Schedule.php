<?php
declare(strict_types=1);

namespace Leo;

/**
 * Date and time-window logic.
 *
 * Every date in the content store is a plain calendar date ('YYYY-MM-DD')
 * interpreted in the site's timezone. Working in calendar dates rather than
 * timestamps keeps the admin honest: a period that "closes March 31" closes at
 * the end of March 31 in Phoenix, wherever the server happens to be.
 */
final class Schedule
{
    private const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** Today's calendar date in $timeZone, as 'YYYY-MM-DD'. */
    public static function todayIn(string $timeZone): string
    {
        try {
            $zone = new \DateTimeZone($timeZone);
        } catch (\Exception) {
            $zone = new \DateTimeZone('America/Phoenix');
        }
        return (new \DateTimeImmutable('now', $zone))->format('Y-m-d');
    }

    public static function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    public static function isMonthDay(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{2}-\d{2}$/', $value) === 1;
    }

    /** Days from $from to $to; negative when $to is in the past. */
    public static function daysBetween(string $from, string $to): ?int
    {
        if (!self::isDate($from) || !self::isDate($to)) {
            return null;
        }
        $a = new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable($to . ' 00:00:00', new \DateTimeZone('UTC'));
        return (int) $a->diff($b)->format('%r%a');
    }

    /** 'November 1, 2026'. Returns '' for anything unparseable. */
    public static function formatDate(?string $date, bool $short = false): string
    {
        if (!self::isDate($date)) {
            return '';
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        $name = self::MONTHS[$month - 1] ?? null;
        if ($name === null) {
            return '';
        }
        return ($short ? substr($name, 0, 3) : $name) . " $day, $year";
    }

    /** 'November 1' — a recurring window has no year to show. */
    public static function formatMonthDay(?string $monthDay): string
    {
        if (!self::isMonthDay($monthDay)) {
            return '';
        }
        [$month, $day] = array_map('intval', explode('-', $monthDay));
        $name = self::MONTHS[$month - 1] ?? null;
        return $name === null ? '' : "$name $day";
    }

    /**
     * Resolve an annual (recurring) window against $today.
     *
     * Windows may wrap the year boundary — the enrollment period runs Nov 1 to
     * Mar 31, so one cycle spans two calendar years. Build the candidate cycles
     * around today and pick the one containing it, else the next to start.
     */
    private static function resolveAnnual(string $opensOn, string $closesOn, string $today): array
    {
        $year = (int) substr($today, 0, 4);
        $wraps = $closesOn < $opensOn;

        $cycles = [];
        foreach ([$year - 1, $year, $year + 1] as $anchor) {
            $cycles[] = [
                'opensOn' => $anchor . '-' . $opensOn,
                'closesOn' => ($wraps ? $anchor + 1 : $anchor) . '-' . $closesOn,
            ];
        }
        usort($cycles, static fn ($a, $b) => $a['opensOn'] <=> $b['opensOn']);

        foreach ($cycles as $cycle) {
            if ($cycle['opensOn'] <= $today && $today <= $cycle['closesOn']) {
                return [
                    'state' => 'open',
                    'opensOn' => $cycle['opensOn'],
                    'closesOn' => $cycle['closesOn'],
                    'daysUntilOpen' => 0,
                    'daysUntilClose' => self::daysBetween($today, $cycle['closesOn']),
                    'previousClosedOn' => null,
                    'recurring' => true,
                    'always' => false,
                ];
            }
        }

        $next = null;
        foreach ($cycles as $cycle) {
            if ($cycle['opensOn'] > $today) {
                $next = $cycle;
                break;
            }
        }
        $previous = null;
        foreach (array_reverse($cycles) as $cycle) {
            if ($cycle['closesOn'] < $today) {
                $previous = $cycle;
                break;
            }
        }

        return [
            'state' => 'upcoming',
            'opensOn' => $next['opensOn'] ?? null,
            'closesOn' => $next['closesOn'] ?? null,
            'daysUntilOpen' => $next ? self::daysBetween($today, $next['opensOn']) : null,
            'daysUntilClose' => null,
            'previousClosedOn' => $previous['closesOn'] ?? null,
            'recurring' => true,
            'always' => false,
        ];
    }

    /**
     * Resolve any window shape into something the templates render directly.
     *
     *   ['type' => 'always']
     *   ['type' => 'fixed',  'opensOn' => 'YYYY-MM-DD', 'closesOn' => 'YYYY-MM-DD']
     *   ['type' => 'annual', 'opensOn' => 'MM-DD',      'closesOn' => 'MM-DD']
     */
    public static function resolveWindow(?array $window, string $today): array
    {
        $spec = $window ?? ['type' => 'always'];
        $type = $spec['type'] ?? 'always';
        $opensOn = $spec['opensOn'] ?? null;
        $closesOn = $spec['closesOn'] ?? null;

        if ($type === 'annual' && self::isMonthDay($opensOn) && self::isMonthDay($closesOn)) {
            return self::resolveAnnual($opensOn, $closesOn, $today);
        }

        if ($type === 'fixed') {
            $opens = self::isDate($opensOn) ? $opensOn : null;
            $closes = self::isDate($closesOn) ? $closesOn : null;

            if ($opens !== null && $today < $opens) {
                return [
                    'state' => 'upcoming',
                    'opensOn' => $opens,
                    'closesOn' => $closes,
                    'daysUntilOpen' => self::daysBetween($today, $opens),
                    'daysUntilClose' => null,
                    'previousClosedOn' => null,
                    'recurring' => false,
                    'always' => false,
                ];
            }
            if ($closes !== null && $today > $closes) {
                return [
                    'state' => 'closed',
                    'opensOn' => $opens,
                    'closesOn' => $closes,
                    'daysUntilOpen' => null,
                    'daysUntilClose' => null,
                    'previousClosedOn' => $closes,
                    'recurring' => false,
                    'always' => false,
                ];
            }
            return [
                'state' => 'open',
                'opensOn' => $opens,
                'closesOn' => $closes,
                'daysUntilOpen' => 0,
                'daysUntilClose' => $closes !== null ? self::daysBetween($today, $closes) : null,
                'previousClosedOn' => null,
                'recurring' => false,
                'always' => false,
            ];
        }

        return [
            'state' => 'open',
            'opensOn' => null,
            'closesOn' => null,
            'daysUntilOpen' => 0,
            'daysUntilClose' => null,
            'previousClosedOn' => null,
            'recurring' => false,
            'always' => true,
        ];
    }

    /**
     * Is an item allowed on the public site today?
     *
     * showFrom/showUntil are a hard gate for content that should only exist for
     * part of the year. They are independent of the application window: an award
     * can stay listed year-round while only accepting applications for five months.
     */
    public static function isPublished(?array $item, string $today): bool
    {
        if ($item === null || !empty($item['archived']) || !empty($item['draft'])) {
            return false;
        }
        $publish = $item['publish'] ?? [];
        $from = $publish['showFrom'] ?? null;
        $until = $publish['showUntil'] ?? null;

        if (self::isDate($from) && $today < $from) {
            return false;
        }
        if (self::isDate($until) && $today > $until) {
            return false;
        }
        return true;
    }

    /** Human-readable summary of a resolved window, for a status pill. */
    public static function describeWindow(?array $resolved): string
    {
        if ($resolved === null) {
            return '';
        }
        if (!empty($resolved['always'])) {
            return 'Open year-round';
        }
        if ($resolved['state'] === 'open') {
            return $resolved['closesOn']
                ? 'Accepting applications through ' . self::formatDate($resolved['closesOn'])
                : 'Accepting applications';
        }
        if ($resolved['state'] === 'upcoming') {
            return $resolved['opensOn']
                ? (!empty($resolved['recurring']) ? 'Reopens ' : 'Opens ') . self::formatDate($resolved['opensOn'])
                : 'Opening soon';
        }
        return $resolved['closesOn'] ? 'Closed ' . self::formatDate($resolved['closesOn']) : 'Closed';
    }
}
