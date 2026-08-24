<?php
declare(strict_types=1);

namespace Leo;

/**
 * The admin's field definitions.
 *
 * All four content types share one set of routes and one form template, driven
 * by this table. Adding a field is a one-line change here, not a new route and
 * a new view.
 */
final class Admin
{
    public static function windowField(): array
    {
        return [
            'key' => 'window',
            'type' => 'window',
            'label' => 'Application period',
            'help' => 'Inherit the site-wide enrollment period, or give this award its own dates.',
        ];
    }

    public static function publishFields(): array
    {
        return [
            [
                'key' => 'publish.showFrom',
                'type' => 'date',
                'label' => 'Show on the site from',
                'help' => 'Leave blank to show as soon as it is published.',
            ],
            [
                'key' => 'publish.showUntil',
                'type' => 'date',
                'label' => 'Hide after',
                'help' => 'Leave blank to keep it up indefinitely.',
            ],
            ['key' => 'draft', 'type' => 'checkbox', 'label' => 'Draft — hide from the public site'],
        ];
    }

    public static function resources(): array
    {
        return [
            'scholarships' => [
                'label' => 'Scholarship',
                'plural' => 'Scholarships',
                'titleKey' => 'name',
                'fields' => array_merge([
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                    ['key' => 'slug', 'type' => 'text', 'label' => 'URL slug', 'help' => 'Leave blank to generate from the name.'],
                    ['key' => 'amount', 'type' => 'text', 'label' => 'Award amount', 'placeholder' => '$1,000 or "Varies with available funds"'],
                    ['key' => 'summary', 'type' => 'textarea', 'label' => 'Summary', 'help' => 'One or two sentences, shown on cards and in search results.'],
                    ['key' => 'criteria', 'type' => 'markdown', 'label' => 'Who can apply', 'help' => 'Markdown. A bulleted list works best.'],
                    ['key' => 'essayPrompts', 'type' => 'lines', 'label' => 'Essay prompts', 'help' => 'One prompt per line.'],
                    ['key' => 'details', 'type' => 'markdown', 'label' => 'Additional detail'],
                    ['key' => 'applyUrl', 'type' => 'text', 'label' => 'Application link'],
                    self::windowField(),
                    ['key' => 'hideWhenClosed', 'type' => 'checkbox', 'label' => 'Hide from the site while applications are closed'],
                    ['key' => 'featured', 'type' => 'checkbox', 'label' => 'Feature on the homepage'],
                ], self::publishFields()),
            ],
            'recipients' => [
                'label' => 'Recipient',
                'plural' => 'Recipients',
                'titleKey' => 'name',
                'fields' => array_merge([
                    ['key' => 'name', 'type' => 'text', 'label' => 'Student name', 'required' => true],
                    ['key' => 'year', 'type' => 'text', 'label' => 'Award year', 'placeholder' => '2025'],
                    ['key' => 'scholarship', 'type' => 'scholarship', 'label' => 'Scholarship awarded'],
                    ['key' => 'school', 'type' => 'text', 'label' => 'School'],
                    ['key' => 'major', 'type' => 'text', 'label' => 'Major'],
                    ['key' => 'amount', 'type' => 'number', 'label' => 'Amount awarded', 'help' => 'Numbers only. Used for the published totals.'],
                    ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote'],
                    ['key' => 'photoUrl', 'type' => 'text', 'label' => 'Photo URL'],
                    ['key' => 'featured', 'type' => 'checkbox', 'label' => 'Feature on the homepage'],
                ], self::publishFields()),
            ],
            'pages' => [
                'label' => 'Page',
                'plural' => 'Pages',
                'titleKey' => 'title',
                'fields' => array_merge([
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
                    ['key' => 'slug', 'type' => 'text', 'label' => 'URL slug', 'help' => 'The page lives at /slug.'],
                    ['key' => 'navLabel', 'type' => 'text', 'label' => 'Navigation label', 'help' => 'Short version for the header. Defaults to the title.'],
                    ['key' => 'summary', 'type' => 'textarea', 'label' => 'Summary'],
                    ['key' => 'body', 'type' => 'markdown', 'label' => 'Body', 'rows' => 18],
                    ['key' => 'inNav', 'type' => 'checkbox', 'label' => 'Show in the main navigation'],
                ], self::publishFields()),
            ],
            'announcements' => [
                'label' => 'Announcement',
                'plural' => 'Announcements',
                'titleKey' => 'title',
                'fields' => array_merge([
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Message'],
                    ['key' => 'ctaLabel', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'ctaUrl', 'type' => 'text', 'label' => 'Button link'],
                    [
                        'key' => 'showWhen',
                        'type' => 'select',
                        'label' => 'Show this announcement',
                        'options' => [
                            ['value' => '', 'label' => 'Always'],
                            ['value' => 'open', 'label' => 'Only while applications are open'],
                            ['value' => 'closed', 'label' => 'Only while applications are closed'],
                        ],
                        'help' => 'Tie a notice to the enrollment period and it swaps itself over every year.',
                    ],
                ], self::publishFields()),
            ],
        ];
    }

    /**
     * A field's HTML name attribute.
     *
     * PHP rewrites '.' to '_' in submitted parameter names, so a field called
     * publish.showFrom would arrive as publish_showFrom and silently never
     * save. Bracket notation is parsed into a nested array instead.
     */
    public static function fieldName(string $key): string
    {
        $parts = explode('.', $key);
        $name = array_shift($parts);
        foreach ($parts as $part) {
            $name .= '[' . $part . ']';
        }
        return $name;
    }

    /** A DOM id for a field — dots are legal but awkward in selectors. */
    public static function fieldId(string $key): string
    {
        return str_replace('.', '-', $key);
    }

    /** Read a dotted path out of a record: 'publish.showFrom'. */
    public static function get(array $source, string $path): mixed
    {
        $node = $source;
        foreach (explode('.', $path) as $key) {
            if (!is_array($node) || !array_key_exists($key, $node)) {
                return null;
            }
            $node = $node[$key];
        }
        return $node;
    }

    private static function set(array &$target, string $path, mixed $value): void
    {
        $parts = explode('.', $path);
        $node = &$target;
        while (count($parts) > 1) {
            $key = array_shift($parts);
            if (!isset($node[$key]) || !is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node[$parts[0]] = $value;
    }

    /** Turn submitted form values into stored values, one field type at a time. */
    public static function applyFields(array $record, array $body, array $fields): array
    {
        foreach ($fields as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';

            if ($type === 'window') {
                $submitted = is_array($body['window'] ?? null) ? $body['window'] : [];
                $windowType = (string) ($submitted['type'] ?? 'inherit');
                $window = ['type' => $windowType];

                if ($windowType === 'annual') {
                    $window['opensOn'] = trim((string) ($submitted['annualOpensOn'] ?? ''));
                    $window['closesOn'] = trim((string) ($submitted['annualClosesOn'] ?? ''));
                } elseif ($windowType === 'fixed') {
                    $window['opensOn'] = trim((string) ($submitted['opensOn'] ?? ''));
                    $window['closesOn'] = trim((string) ($submitted['closesOn'] ?? ''));
                }

                $record['window'] = $window;
                continue;
            }

            $raw = self::get($body, $key);

            if ($type === 'checkbox') {
                self::set($record, $key, $raw === 'on' || $raw === 'true');
            } elseif ($type === 'number') {
                $value = is_numeric($raw) ? (int) $raw : '';
                self::set($record, $key, $value);
            } elseif ($type === 'lines') {
                $lines = preg_split('/\R/', (string) $raw) ?: [];
                $lines = array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
                self::set($record, $key, $lines);
            } else {
                self::set($record, $key, is_string($raw) ? trim($raw) : '');
            }
        }

        return $record;
    }
}
