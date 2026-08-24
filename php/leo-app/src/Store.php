<?php
declare(strict_types=1);

namespace Leo;

/**
 * Content store.
 *
 * The whole site is one JSON document. That is deliberate over a database: the
 * foundation publishes a few dozen records, a backup is a file copy, and there
 * is nothing to administer. Writes are atomic (temp file then rename) so a
 * crash mid-save can never leave a truncated file behind.
 */
final class Store
{
    public const COLLECTIONS = ['announcements', 'scholarships', 'recipients', 'pages'];

    private array $data;

    public function __construct(private string $file)
    {
        $this->data = $this->read();
    }

    private function defaults(): array
    {
        return [
            'site' => [
                'name' => 'LEO Foundation',
                'tagline' => '',
                'timezone' => 'America/Phoenix',
            ],
            'enrollment' => ['type' => 'annual', 'opensOn' => '11-01', 'closesOn' => '03-31'],
            'announcements' => [],
            'scholarships' => [],
            'recipients' => [],
            'pages' => [],
        ];
    }

    private function read(): array
    {
        $defaults = $this->defaults();
        if (!is_file($this->file)) {
            return $defaults;
        }

        $parsed = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('content.json is not valid JSON: ' . json_last_error_msg());
        }

        $data = array_merge($defaults, $parsed);
        $data['site'] = array_merge($defaults['site'], $parsed['site'] ?? []);
        foreach (self::COLLECTIONS as $name) {
            if (!isset($data[$name]) || !is_array($data[$name])) {
                $data[$name] = [];
            }
            $data[$name] = array_values($data[$name]);
        }
        return $data;
    }

    public function save(): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('could not encode content: ' . json_last_error_msg());
        }

        $tmp = $dir . '/.' . basename($this->file) . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('could not write ' . $tmp . ' — check file permissions');
        }
        rename($tmp, $this->file);
    }

    public function site(): array
    {
        return $this->data['site'];
    }

    public function enrollment(): array
    {
        return $this->data['enrollment'];
    }

    public function list(string $collection): array
    {
        if (!in_array($collection, self::COLLECTIONS, true)) {
            throw new \InvalidArgumentException("unknown collection: $collection");
        }
        return $this->data[$collection];
    }

    public function find(string $collection, string $id): ?array
    {
        foreach ($this->list($collection) as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }
        return null;
    }

    public function findBySlug(string $collection, string $slug): ?array
    {
        foreach ($this->list($collection) as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return $item;
            }
        }
        return null;
    }

    /** Insert when the id is absent or unknown, otherwise merge in place. */
    public function upsert(string $collection, array $record): array
    {
        $this->list($collection);
        $id = $record['id'] ?? null;

        if ($id !== null) {
            foreach ($this->data[$collection] as $index => $item) {
                if (($item['id'] ?? null) === $id) {
                    $merged = array_merge($item, $record);
                    $this->data[$collection][$index] = $merged;
                    $this->save();
                    return $merged;
                }
            }
        }

        $record['id'] = $id ?? self::newId();
        $this->data[$collection][] = $record;
        $this->save();
        return $record;
    }

    public function remove(string $collection, string $id): bool
    {
        foreach ($this->list($collection) as $index => $item) {
            if (($item['id'] ?? null) === $id) {
                array_splice($this->data[$collection], $index, 1);
                $this->save();
                return true;
            }
        }
        return false;
    }

    /** Move a record up or down within its collection's display order. */
    public function reorder(string $collection, string $id, string $direction): bool
    {
        $items = $this->list($collection);
        $index = null;
        foreach ($items as $position => $item) {
            if (($item['id'] ?? null) === $id) {
                $index = $position;
                break;
            }
        }
        if ($index === null) {
            return false;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($items)) {
            return false;
        }

        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        $this->data[$collection] = $items;
        $this->save();
        return true;
    }

    public function updateSite(array $patch): void
    {
        $this->data['site'] = array_merge($this->data['site'], $patch);
        $this->save();
    }

    public function updateEnrollment(array $patch): void
    {
        $this->data['enrollment'] = array_merge($this->data['enrollment'], $patch);
        $this->save();
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** Turn a title into a URL-safe slug. */
    public static function slugify(?string $value): string
    {
        $value = (string) $value;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return substr($value, 0, 80);
    }
}
