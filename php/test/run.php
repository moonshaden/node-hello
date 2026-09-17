<?php
declare(strict_types=1);

/**
 * Test runner.
 *
 * Plain PHP rather than PHPUnit: the hosting account has no shell and therefore
 * no Composer, so a test suite that needs installing is a test suite that never
 * gets run. This one runs with `php test/run.php` anywhere PHP exists.
 */

require __DIR__ . '/../leo-app/src/Schedule.php';
require __DIR__ . '/../leo-app/src/Store.php';
require __DIR__ . '/../leo-app/src/Markdown.php';
require __DIR__ . '/../leo-app/src/Content.php';
require __DIR__ . '/../leo-app/src/Auth.php';
require __DIR__ . '/../leo-app/src/Admin.php';
require __DIR__ . '/../leo-app/src/App.php';
require __DIR__ . '/../leo-app/src/helpers.php';

use Leo\Admin;
use Leo\Content;
use Leo\Markdown;
use Leo\Schedule;
use Leo\Store;

$passed = 0;
$failed = 0;
$current = '';

function test(string $name, callable $body): void
{
    global $passed, $failed, $current;
    $current = $name;
    try {
        $body();
        $passed++;
        echo "  ok  $name\n";
    } catch (\Throwable $error) {
        $failed++;
        echo "  FAIL $name\n       " . $error->getMessage() . "\n";
    }
}

function is_same(mixed $actual, mixed $expected, string $note = ''): void
{
    if ($actual !== $expected) {
        throw new \RuntimeException(
            ($note !== '' ? "$note: " : '') .
            'expected ' . json_encode($expected) . ', got ' . json_encode($actual)
        );
    }
}

function ok(bool $value, string $note = 'expected true'): void
{
    if (!$value) {
        throw new \RuntimeException($note);
    }
}

function tempStore(array $seed = []): Store
{
    $dir = sys_get_temp_dir() . '/leo-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $file = $dir . '/content.json';
    file_put_contents($file, json_encode($seed));
    return new Store($file);
}

const ENROLLMENT = ['type' => 'annual', 'opensOn' => '11-01', 'closesOn' => '03-31'];

echo "\nSchedule\n";

test('today respects the site timezone', function () {
    // Nothing to stub here, so assert the shape and that the zone is honoured.
    $phoenix = Schedule::todayIn('America/Phoenix');
    $tokyo = Schedule::todayIn('Asia/Tokyo');
    ok((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $phoenix), 'not a calendar date');
    ok($phoenix <= $tokyo, 'Phoenix is never ahead of Tokyo');
});

test('an annual window that wraps the year is open on both sides of New Year', function () {
    foreach (['2025-11-01', '2025-12-25', '2026-01-15', '2026-03-31'] as $day) {
        is_same(Schedule::resolveWindow(ENROLLMENT, $day)['state'], 'open', $day);
    }
});

test('an annual window is closed between cycles and points at the next', function () {
    $resolved = Schedule::resolveWindow(ENROLLMENT, '2026-08-21');
    is_same($resolved['state'], 'upcoming');
    is_same($resolved['opensOn'], '2026-11-01');
    is_same($resolved['closesOn'], '2027-03-31');
    is_same($resolved['previousClosedOn'], '2026-03-31');
    is_same($resolved['daysUntilOpen'], 72);
});

test('an annual window rolls into the next cycle without an edit', function () {
    is_same(Schedule::resolveWindow(ENROLLMENT, '2028-12-01')['closesOn'], '2029-03-31');
    is_same(Schedule::resolveWindow(ENROLLMENT, '2031-02-01')['opensOn'], '2030-11-01');
});

test('boundary days are inclusive at both ends', function () {
    is_same(Schedule::resolveWindow(ENROLLMENT, '2025-10-31')['state'], 'upcoming');
    is_same(Schedule::resolveWindow(ENROLLMENT, '2025-11-01')['state'], 'open');
    is_same(Schedule::resolveWindow(ENROLLMENT, '2026-03-31')['state'], 'open');
    is_same(Schedule::resolveWindow(ENROLLMENT, '2026-04-01')['state'], 'upcoming');
});

test('a non-wrapping annual window works too', function () {
    $summer = ['type' => 'annual', 'opensOn' => '06-01', 'closesOn' => '08-31'];
    is_same(Schedule::resolveWindow($summer, '2026-07-04')['state'], 'open');
    is_same(Schedule::resolveWindow($summer, '2026-09-01')['state'], 'upcoming');
    is_same(Schedule::resolveWindow($summer, '2026-09-01')['opensOn'], '2027-06-01');
});

test('fixed windows move through upcoming, open, then closed for good', function () {
    $fixed = ['type' => 'fixed', 'opensOn' => '2026-09-01', 'closesOn' => '2026-09-30'];
    is_same(Schedule::resolveWindow($fixed, '2026-08-31')['state'], 'upcoming');
    is_same(Schedule::resolveWindow($fixed, '2026-09-15')['state'], 'open');
    is_same(Schedule::resolveWindow($fixed, '2026-10-01')['state'], 'closed');
});

test('malformed dates fall back to always-open rather than throwing', function () {
    is_same(Schedule::resolveWindow(['type' => 'annual', 'opensOn' => 'nov', 'closesOn' => ''], '2026-08-21')['state'], 'open');
    is_same(Schedule::resolveWindow(['type' => 'fixed', 'opensOn' => 'soon'], '2026-08-21')['state'], 'open');
    is_same(Schedule::resolveWindow(null, '2026-08-21')['state'], 'open');
});

test('publish windows gate visibility independently of applications', function () {
    $item = ['publish' => ['showFrom' => '2026-04-01', 'showUntil' => '2026-06-30']];
    is_same(Schedule::isPublished($item, '2026-03-31'), false);
    is_same(Schedule::isPublished($item, '2026-05-01'), true);
    is_same(Schedule::isPublished($item, '2026-07-01'), false);
    is_same(Schedule::isPublished(['draft' => true], '2026-05-01'), false);
    is_same(Schedule::isPublished([], '2026-05-01'), true);
});

test('dates are formatted for humans', function () {
    is_same(Schedule::formatDate('2026-11-01'), 'November 1, 2026');
    is_same(Schedule::formatDate('2026-11-01', true), 'Nov 1, 2026');
    is_same(Schedule::formatDate('garbage'), '');
    is_same(Schedule::formatMonthDay('03-31'), 'March 31');
});

test('window descriptions read correctly in each state', function () {
    ok(str_contains(Schedule::describeWindow(Schedule::resolveWindow(ENROLLMENT, '2026-01-15')), 'through March 31, 2026'));
    ok(str_contains(Schedule::describeWindow(Schedule::resolveWindow(ENROLLMENT, '2026-08-21')), 'Reopens November 1, 2026'));
    is_same(Schedule::describeWindow(Schedule::resolveWindow(['type' => 'always'], '2026-08-21')), 'Open year-round');
});

echo "\nStore\n";

test('a missing file starts from an empty site rather than throwing', function () {
    $store = new Store(sys_get_temp_dir() . '/leo-missing-' . bin2hex(random_bytes(4)) . '.json');
    is_same($store->list('scholarships'), []);
    is_same($store->site()['timezone'], 'America/Phoenix');
});

test('writes survive a reload', function () {
    $store = tempStore();
    $saved = $store->upsert('scholarships', ['name' => 'Nursing']);
    ok(!empty($saved['id']));
});

test('upsert merges into an existing record instead of duplicating it', function () {
    $store = tempStore();
    $created = $store->upsert('recipients', ['name' => 'Ada', 'year' => '2025']);
    $store->upsert('recipients', ['id' => $created['id'], 'name' => 'Ada Lovelace']);

    is_same(count($store->list('recipients')), 1);
    is_same($store->list('recipients')[0]['name'], 'Ada Lovelace');
    is_same($store->list('recipients')[0]['year'], '2025');
});

test('reorder moves a record and refuses to fall off either end', function () {
    $store = tempStore();
    $first = $store->upsert('pages', ['title' => 'One']);
    $second = $store->upsert('pages', ['title' => 'Two']);

    is_same($store->reorder('pages', $second['id'], 'up'), true);
    is_same(array_column($store->list('pages'), 'title'), ['Two', 'One']);
    is_same($store->reorder('pages', $second['id'], 'up'), false);
    is_same($store->reorder('pages', $first['id'], 'down'), false);
});

test('remove deletes only the named record', function () {
    $store = tempStore();
    $keep = $store->upsert('pages', ['title' => 'Keep']);
    $drop = $store->upsert('pages', ['title' => 'Drop']);

    is_same($store->remove('pages', $drop['id']), true);
    is_same($store->remove('pages', 'nope'), false);
    is_same(array_column($store->list('pages'), 'id'), [$keep['id']]);
});

test('an unknown collection is a programming error, not a silent empty list', function () {
    $store = tempStore();
    try {
        $store->list('sponsors');
        throw new \RuntimeException('expected an exception');
    } catch (\InvalidArgumentException) {
        // expected
    }
});

test('slugs are url-safe and stable', function () {
    is_same(Store::slugify('Joyce K. Smith Nursing Memorial Scholarship'), 'joyce-k-smith-nursing-memorial-scholarship');
    is_same(Store::slugify('  Women & Chemistry  '), 'women-chemistry');
    is_same(Store::slugify(''), '');
});

echo "\nContent\n";

$base = [
    'site' => ['name' => 'LEO Foundation', 'timezone' => 'America/Phoenix'],
    'enrollment' => ENROLLMENT,
    'scholarships' => [
        ['id' => 'a', 'slug' => 'a', 'name' => 'Inherits the site period'],
        ['id' => 'b', 'slug' => 'b', 'name' => 'Hides when closed', 'hideWhenClosed' => true],
        ['id' => 'c', 'slug' => 'c', 'name' => 'Always open', 'window' => ['type' => 'always']],
    ],
    'recipients' => [
        ['id' => 'r1', 'name' => 'Ada', 'year' => '2025', 'amount' => 1000, 'featured' => true],
        ['id' => 'r2', 'name' => 'Grace', 'year' => '2024', 'amount' => 500],
        ['id' => 'r3', 'name' => 'Draft person', 'year' => '2025', 'amount' => 9999, 'draft' => true],
    ],
    'pages' => [['id' => 'p1', 'slug' => 'about', 'title' => 'About', 'inNav' => true]],
    'announcements' => [
        ['id' => 'n1', 'title' => 'Apply now', 'showWhen' => 'open'],
        ['id' => 'n2', 'title' => 'Reopens soon', 'showWhen' => 'closed'],
        ['id' => 'n3', 'title' => 'Always visible'],
    ],
];

const OPEN_DAY = '2026-01-15';
const CLOSED_DAY = '2026-08-21';

test('scholarships inherit the site enrollment period', function () use ($base) {
    $store = tempStore($base);
    $open = Content::publicScholarships($store, OPEN_DAY);
    $closed = Content::publicScholarships($store, CLOSED_DAY);

    is_same(array_values(array_filter($open, fn ($s) => $s['id'] === 'a'))[0]['isOpen'], true);
    is_same(array_values(array_filter($closed, fn ($s) => $s['id'] === 'a'))[0]['isOpen'], false);
});

test('a scholarship can override the site period', function () use ($base) {
    $store = tempStore($base);
    $closed = Content::publicScholarships($store, CLOSED_DAY);
    is_same(count(Content::openScholarships($closed)), 1);
    is_same(Content::openScholarships($closed)[0]['id'], 'c');
});

test('hideWhenClosed removes a scholarship from the listing out of season', function () use ($base) {
    $store = tempStore($base);
    $ids = static fn (array $list) => array_column($list, 'id');
    ok(in_array('b', $ids(Content::publicScholarships($store, OPEN_DAY)), true));
    ok(!in_array('b', $ids(Content::publicScholarships($store, CLOSED_DAY)), true));
});

test('the resolved window overwrites the stored spec', function () use ($base) {
    $store = tempStore($base);
    $c = Content::resolveScholarship($base['scholarships'][2], ENROLLMENT, CLOSED_DAY);
    // Regression: array + array keeps the left operand, which left the raw
    // spec in place and made every override look closed.
    ok(isset($c['window']['state']), 'window was not resolved');
    is_same($c['window']['state'], 'open');
});

test('draft recipients stay off the public site but appear in preview', function () use ($base) {
    $store = tempStore($base);
    is_same(array_column(Content::publicRecipients($store, OPEN_DAY), 'name'), ['Ada', 'Grace']);
    is_same(count(Content::publicRecipients($store, OPEN_DAY, true)), 3);
});

test('recipients group newest year first, featured leading each year', function () use ($base) {
    $store = tempStore($base);
    $groups = Content::groupRecipientsByYear(Content::publicRecipients($store, OPEN_DAY));
    is_same(array_column($groups, 'year'), ['2025', '2024']);
    is_same($groups[0]['items'][0]['name'], 'Ada');
});

// The live site publishes no award year for anyone, so every recipient can
// arrive with year empty. That used to bucket them all under a literal "Other"
// heading and report "across 0 years".
test('a short story is left alone, a long one is cut at a sentence end', function () {
    $short = 'I am grateful for the scholarship. It changed things.';
    is_same(Content::excerpt($short), ['text' => $short, 'full' => $short, 'truncated' => false]);

    $long = str_repeat('This is a sentence that runs on for a while. ', 40);
    $cut = Content::excerpt($long);
    is_same($cut['truncated'], true);
    ok(mb_strlen($cut['text']) <= 521, 'excerpt was ' . mb_strlen($cut['text']));
    ok(str_ends_with($cut['text'], '.'), 'did not cut at a sentence end');
    ok(!str_contains($cut['text'], '…'), 'a sentence end should not take an ellipsis');
});

test('a single long opening sentence still yields a usable excerpt', function () {
    $cut = Content::excerpt(str_repeat('word ', 200) . '. Then more.');
    is_same($cut['truncated'], true);
    ok(mb_strlen($cut['text']) > 400, 'excerpt collapsed to ' . mb_strlen($cut['text']));
    ok(str_ends_with($cut['text'], '…'), 'a mid-sentence cut needs an ellipsis');
});

// Both builds render the same cards, so they must agree on where to cut.
test('every seeded recipient story fits a card once excerpted', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    foreach ($seed['recipients'] as $r) {
        if (empty($r['quote'])) {
            continue;
        }
        $e = Content::excerpt($r['quote']);
        ok(mb_strlen($e['text']) <= 521, $r['name'] . ': ' . mb_strlen($e['text']));
    }
});

// The recipient photos used to be hot-linked from the WordPress media library,
// which would have 404'd the moment the new site took over the domain.
test('every recipient photo is served from this repo and exists', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $withPhotos = array_filter($seed['recipients'], fn ($r) => !empty($r['photoUrl']));
    ok(count($withPhotos) > 0, 'no recipient has a photo');

    foreach ($withPhotos as $r) {
        ok(str_starts_with($r['photoUrl'], '/img/recipients/'), $r['name'] . ' is not served locally');
        foreach (['public', 'php/public_html'] as $base) {
            ok(is_file($root . '/' . $base . $r['photoUrl']), 'missing ' . $base . $r['photoUrl']);
        }
    }
});

// Community Partnerships sits under About as a dropdown rather than as an
// eighth top-level item. Mirrors the same assertions in test/content.test.js.
test('navPages nests a child under its parent and leaves the rest flat', function () {
    $pages = [
        ['slug' => 'about', 'title' => 'About', 'inNav' => true],
        ['slug' => 'community', 'title' => 'Community', 'inNav' => true, 'navParent' => 'about'],
        ['slug' => 'faq', 'title' => 'FAQs', 'inNav' => true],
        ['slug' => 'hidden', 'title' => 'Hidden', 'inNav' => false],
    ];
    $nav = Content::navPages($pages);
    is_same(array_map(fn ($p) => $p['slug'], $nav), ['about', 'faq'], 'top level changed');
    is_same(array_map(fn ($c) => $c['slug'], $nav[0]['children']), ['community'], 'about lost its child');
    is_same($nav[1]['children'], [], 'faq gained a child');
    is_same(array_map(fn ($p) => $p['slug'], Content::navFlat($pages)), ['about', 'community', 'faq'], 'the footer list changed');
});

// A child pointing at a parent that is not in the nav would otherwise vanish
// from the header entirely, which is worse than showing it at the top level.
test('a child with no visible parent falls back to the top level', function () {
    $orphan = Content::navPages([
        ['slug' => 'community', 'title' => 'Community', 'inNav' => true, 'navParent' => 'about'],
    ]);
    is_same(array_map(fn ($p) => $p['slug'], $orphan), ['community'], 'the orphan disappeared');

    $self = Content::navPages([
        ['slug' => 'about', 'title' => 'About', 'inNav' => true, 'navParent' => 'about'],
    ]);
    is_same(array_map(fn ($p) => $p['slug'], $self), ['about'], 'a self-parented page disappeared');
});

test('the seeded nav puts community partnerships under about', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $nav = Content::navPages($seed['pages']);
    $slugs = array_map(fn ($p) => $p['slug'], $nav);
    ok(in_array('about', $slugs, true), 'about is not in the nav');
    ok(!in_array('community', $slugs, true), 'community is still a top-level item');
    foreach ($nav as $p) {
        if ($p['slug'] === 'about') {
            is_same(array_map(fn ($c) => $c['slug'], $p['children']), ['community'], 'about lost its child');
        }
    }
});

// The Community Partnerships page is transcribed from /community-partnerships/
// on the live site: one partner, its copy word for word, and the event gallery.
// Mirrors the same assertions in test/content.test.js.
test('the community page publishes the partner and its gallery', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $page = null;
    foreach ($seed['pages'] as $candidate) {
        if (($candidate['slug'] ?? '') === 'community') {
            $page = $candidate;
        }
    }
    ok($page !== null, 'no community page in the seed');
    is_same(array_map(fn ($p) => $p['name'], $page['partners']), ['Alice Cooper’s Solid Rock Teen Center'], 'the partners changed');
    ok(count($page['gallery']) === 4, 'the gallery changed');

    $images = array_merge(
        array_map(fn ($p) => $p['photoUrl'], $page['partners']),
        array_map(fn ($g) => $g['src'], $page['gallery'])
    );
    foreach ($images as $src) {
        ok(str_starts_with($src, '/img/partners/'), $src . ' is not served locally');
        foreach (['public', 'php/public_html'] as $base) {
            ok(is_file($root . '/' . $base . $src), 'missing ' . $base . $src);
        }
    }
    foreach ($page['gallery'] as $g) {
        ok(trim($g['alt']) !== '', $g['src'] . ' has no alt text');
    }
});

// The community page is off the main nav by design, so the programs page is the
// way in. A reworded programs body must not quietly strip the link.
test('the programs page links to the community page', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'programs') {
            ok(str_contains($page['body'], '](/community)'), 'the programs page no longer links to /community');
        }
    }
});

// The Programs & Partnerships page is transcribed from /programs-partnerships/
// on the live site: three programs, in the live order, copy word for word.
// Mirrors the same assertions in test/content.test.js.
test('the programs page publishes all three programs in the live order', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $page = null;
    foreach ($seed['pages'] as $candidate) {
        if (($candidate['slug'] ?? '') === 'programs') {
            $page = $candidate;
        }
    }
    ok($page !== null, 'no programs page in the seed');
    $names = array_map(fn ($p) => $p['name'], $page['programs']);
    is_same($names, ['Foster Youth Programs', 'Impact Leadership Program', 'Youth Development Academy'], 'the programs changed');
});

// Same failure mode as the recipient, board and slide images.
test('every program photo is served from this repo and exists', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $programs = [];
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'programs') {
            $programs = $page['programs'];
        }
    }
    ok(count($programs) === 3, 'a program went missing');

    foreach ($programs as $p) {
        ok(str_starts_with($p['photoUrl'], '/img/programs/'), $p['name'] . ' is not served locally');
        ok(trim($p['alt']) !== '', $p['name'] . ' has no alt text');
        foreach (['public', 'php/public_html'] as $base) {
            ok(is_file($root . '/' . $base . $p['photoUrl']), 'missing ' . $base . $p['photoUrl']);
        }
    }
});

// The hero slide for this destination pointed nowhere until the page existed.
test('the programs hero slide points at the page that now exists', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    foreach ($seed['slides'] as $s) {
        if (stripos($s['heading'], 'PROGRAMS') !== false) {
            is_same($s['ctaUrl'], '/programs', 'the slide still points nowhere');
        }
    }
});

// The board is real named people transcribed from /leadership-2/ on the live
// site. A wrong name, a wrong office, or a reordered roster is worse than no
// page at all, so the seed is pinned here rather than left to drift. Mirrors
// the same three assertions in test/content.test.js.
test('the board page publishes every member in the live order', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $page = null;
    foreach ($seed['pages'] as $candidate) {
        if (($candidate['slug'] ?? '') === 'board') {
            $page = $candidate;
        }
    }
    ok($page !== null, 'no board page in the seed');
    // The client asked for it reachable from Contact, not added to the header.
    ok(($page['inNav'] ?? true) === false, 'the board page is in the main navigation');

    $expected = [
        ['Madeline LoConti Winney', 'Chief Executive Officer'],
        ['Michele Simphoukham', 'Chief Financial Officer'],
        ['Greg Sharp', 'Board Member'],
        ['Robb Kottman', 'Board Member and Investment Advisor'],
        ['Dr. Jennifer Billingsley', 'Board Member'],
        ['Darrin Anderson', 'Board Member'],
    ];
    $actual = array_map(fn ($m) => [$m['name'], $m['role']], $page['members']);
    is_same($actual, $expected, 'the board roster changed');
});

// Same failure mode as the recipient portraits: a wp-content URL would 404 the
// moment the new site took over the domain.
test('every board photo is served from this repo and exists', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $members = [];
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'board') {
            $members = array_filter($page['members'], fn ($m) => !empty($m['photoUrl']));
        }
    }
    ok(count($members) === 6, 'a board member lost their photo');

    foreach ($members as $m) {
        ok(str_starts_with($m['photoUrl'], '/img/board/'), $m['name'] . ' is not served locally');
        foreach (['public', 'php/public_html'] as $base) {
            ok(is_file($root . '/' . $base . $m['photoUrl']), 'missing ' . $base . $m['photoUrl']);
        }
    }
});

test('every seeded board bio fits a card once excerpted', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') !== 'board') {
            continue;
        }
        foreach ($page['members'] as $m) {
            $e = Content::excerpt($m['bio']);
            ok(mb_strlen($e['text']) <= 521, $m['name'] . ': ' . mb_strlen($e['text']));
        }
    }
});

// The homepage hero is published twice on the live site: three full-bleed
// panels for desktop and a mobile-only LayerSlider. Both carry the same three
// destinations, so the seed is the union — the slider's fuller headings over
// the panels' larger photographs. Order is the live order.
test('the homepage slider carries every live slide, in the live order', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $headings = array_map(fn ($s) => $s['heading'], $seed['slides']);
    is_same($headings, ['PROGRAMS & PARTNERSHIPS', "SCHOLARSHIP FAQ's", 'LEO FOUNDATION NEWS'], 'the slider changed');
    is_same(array_map(fn ($s) => $s['order'], $seed['slides']), [1, 2, 3], 'the slides reordered');
});

// A slider component reads the same keys off every slide. A slide that dropped
// one because the live site published nothing there would read as null rather
// than empty, so the shape is uniform and the keys are always strings.
test('every slide carries the same keys', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $keys = ['id', 'image', 'alt', 'heading', 'subheading', 'body', 'ctaLabel', 'ctaUrl', 'order'];
    foreach ($seed['slides'] as $s) {
        is_same(array_keys($s), $keys, $s['id'] . ' has the wrong keys');
        foreach ($keys as $k) {
            if ($k === 'order') {
                continue;
            }
            ok(is_string($s[$k]), $s['id'] . '.' . $k . ' is not a string');
        }
        ok(mb_strlen($s['alt']) > 20, $s['id'] . ' has no real alt text');
    }
});

// Same failure mode as the recipient and board photos: a wp-content URL would
// 404 the moment the new site took over the domain.
test('every slide image is served from this repo and exists', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    ok(count($seed['slides']) === 3, 'a slide went missing');

    foreach ($seed['slides'] as $s) {
        ok(str_starts_with($s['image'], '/img/slides/'), $s['id'] . ' is not served locally');
        foreach (['public', 'php/public_html'] as $base) {
            ok(is_file($root . '/' . $base . $s['image']), 'missing ' . $base . $s['image']);
        }
    }
});

// A slide may link nowhere — two of the three live destinations have no page on
// this site yet — but it must never link somewhere that 404s.
test('no slide links to a page this site does not serve', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $served = ['/', '/scholarships', '/recipients'];
    foreach ($seed['pages'] as $page) {
        $served[] = '/' . $page['slug'];
    }
    foreach ($seed['slides'] as $s) {
        if ($s['ctaUrl'] === '') {
            continue;
        }
        ok(in_array($s['ctaUrl'], $served, true), $s['id'] . ' links to ' . $s['ctaUrl'] . ', which nothing serves');
    }
});

// ---------------------------------------------------------------------------
// The split hero. Half the hero is an awarded student, cut free of the
// background of their own photograph, so the awards stay the focus the client
// asked for. Mirrors the tests in test/routes.test.js and test/content.test.js.
// ---------------------------------------------------------------------------

// A hero quote is an excerpt, never a paraphrase. Copy on this site is
// transcribed from what the foundation publishes or it does not ship, and a
// line pulled out for the hero is no exception, so it has to be a literal run
// of characters out of the bio the student published.
test('every hero quote is verbatim from the bio it was lifted from', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $withQuote = array_filter($seed['recipients'], fn ($r) => !empty($r['heroQuote']));
    ok(count($withQuote) >= 1, 'no student fronts the hero');

    foreach ($withQuote as $person) {
        ok(
            str_contains((string) $person['quote'], (string) $person['heroQuote']),
            $person['name'] . "'s hero quote is not a literal substring of their bio"
        );
    }
});

// The hero stands the figure free of its background, which only works if the
// cutout is a real transparent image alongside the ordinary portrait.
test('the hero student has a cutout in both builds', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    $id = $seed['site']['heroStudentId'] ?? '';
    ok($id !== '', 'no hero student is configured');

    $person = null;
    foreach ($seed['recipients'] as $r) {
        if ($r['id'] === $id) {
            $person = $r;
        }
    }
    ok($person !== null, 'site.heroStudentId points at ' . $id . ', which is not a recipient');
    ok(!empty($person['cutoutUrl']), 'the hero student carries no cutout');
    ok($person['cutoutUrl'] !== $person['photoUrl'], 'the cutout is the uncut photograph');
    ok(empty($person['draft']), 'the hero student is a draft');

    foreach (['public', 'php/public_html'] as $base) {
        ok(is_file($root . '/' . $base . $person['cutoutUrl']), 'missing ' . $base . $person['cutoutUrl']);
    }
});

// The resolver takes the *published* list, so a drafted or out-of-window
// student cannot reach the hero, and a hero with no usable student falls back
// to the deadline card rather than losing half of itself.
test('the hero student is resolved from the published list only', function () {
    $store = tempStore(['site' => ['heroStudentId' => 'rec-a']]);
    $published = [['id' => 'rec-a', 'name' => 'A', 'cutoutUrl' => '/img/a.png']];

    is_same(Content::heroStudent($store, $published)['name'], 'A', 'the configured student is not found');
    is_same(Content::heroStudent($store, []), null, 'an unpublished student still reached the hero');
    is_same(Content::heroStudent($store, [['id' => 'rec-b', 'cutoutUrl' => '/img/b.png']]), null, 'the wrong student was picked');
    is_same(Content::heroStudent($store, [['id' => 'rec-a']]), null, 'a student with no cutout reached the hero');
    is_same(Content::heroStudent(tempStore(['site' => []]), $published), null, 'the hero filled itself in unasked');
});

// The two builds ship their own copy of the client script and the stylesheet,
// the same way they ship their own copy of the seed. Nothing pinned them
// together, so a fix applied to one and forgotten in the other would run on the
// dev twin and not on the deployed site -- with both suites green, because
// neither reads them. Same failure mode as the seed drift, same remedy.
test('both builds ship the same client script and stylesheet', function () {
    $root = dirname(__DIR__, 2);
    foreach ([
        ['public/js/site.js', 'php/public_html/js/site.js'],
        ['public/css/site.css', 'php/public_html/css/site.css'],
    ] as [$a, $b]) {
        ok(
            file_get_contents($root . '/' . $a) === file_get_contents($root . '/' . $b),
            $a . ' and ' . $b . ' have drifted'
        );
    }
});

// A deploy replaced the stylesheet and the script on the server and a visitor
// kept being served the old pair: the URLs carried no version and the host
// sends no Cache-Control, so the browser cached them heuristically off
// Last-Modified and a file that had been a fortnight old stayed "fresh" for
// over a day. Nothing on the page could dislodge it.
//
// The version is the CONTENT hash rather than the mtime. A deploy rewrites
// every mtime whether or not the bytes changed, so an mtime would bust every
// cache on every deploy AND give the two builds different URLs for the same
// file -- which the cross-build render diff reads as a divergence.
test('asset_url versions a file by its content', function () {
    $root = dirname(__DIR__, 2);

    foreach (['/css/site.css', '/js/site.js'] as $urlPath) {
        $expected = substr(hash_file('sha256', $root . '/php/public_html' . $urlPath), 0, 10);
        is_same(asset_url($urlPath, ''), $urlPath . '?v=' . $expected, $urlPath . ' is not versioned by its bytes');
        // The mount point still has to come first, or the site breaks under a
        // base path the way every other link would.
        is_same(asset_url($urlPath, '/~leo'), '/~leo' . $urlPath . '?v=' . $expected, $urlPath . ' lost its base path');
    }

    // A missing file must not emit a bare '?v=' -- that is a cache key that
    // never changes, which is worse than no version at all.
    is_same(asset_url('/css/nope.css', ''), '/css/nope.css', 'a missing asset should carry no version');
});

// The templates are the other half: the helper is no use if a build stops
// calling it. Both have to, for both assets -- a view change made once is the
// drift this suite exists to catch.
test('both builds request the stylesheet and the script through the asset helper', function () {
    $root = dirname(__DIR__, 2);
    foreach ([
        ['views/partials/head.ejs', "assetUrl('/css/site.css')"],
        ['views/partials/foot.ejs', "assetUrl('/js/site.js')"],
        ['php/leo-app/views/partials/head.php', "asset_url('/css/site.css'"],
        ['php/leo-app/views/partials/foot.php', "asset_url('/js/site.js'"],
    ] as [$view, $needle]) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, $needle), $view . ' does not version its asset URL');
    }
});

// The client asked for the horizontal wordmark back in the footer, under the
// lion mark. It is a view change, so it had to be made twice, and a footer that
// silently lost it in one build only is exactly the drift the byte-identity
// test above exists to catch -- except that test covers the CSS and the script,
// not the templates. This covers the templates and the asset behind them.
test('both footers carry the wordmark under the lion mark', function () {
    $root = dirname(__DIR__, 2);
    foreach ([
        'views/partials/foot.ejs',
        'php/leo-app/views/partials/foot.php',
    ] as $view) {
        $src = file_get_contents($root . '/' . $view);
        // Cropped to the name and its two rules -- the strapline the artwork
        // used to carry is set in type below it now, the way the masthead does
        // it, so the raster is the name only.
        ok(str_contains($src, 'leo-wordmark-footer.png'), $view . ' lost the footer wordmark');
        ok(str_contains($src, 'foot-lockup'), $view . ' lost the foot-lockup class');
        ok(str_contains($src, 'class="foot-strap"'), $view . ' lost the typed strapline');
        ok(!str_contains($src, 'leo-lockup-footer.png'), $view . ' still ships the strapline baked into the raster');
        // The wordmark names the organisation, so the mark above it is
        // decorative -- otherwise a screen reader announces the org twice.
        // Matched with a bounded .* rather than [^>]*: the PHP template's src
        // is a short-echo tag, and the closing angle bracket of that tag would
        // end a [^>]* run early. (Do not write that tag literally in a comment
        // here -- its closing sequence would drop the parser out of PHP mode.)
        ok(
            preg_match('/class="foot-mark".{0,240}?aria-hidden="true"/s', $src) === 1,
            $view . ': the footer mark must be decorative beside the wordmark'
        );
    }
    // And the artwork itself has to be in both public trees, or one build
    // renders a broken image.
    foreach (['public/img/brand', 'php/public_html/img/brand'] as $dir) {
        ok(
            is_file($root . '/' . $dir . '/leo-wordmark-footer.png'),
            $dir . '/leo-wordmark-footer.png is missing'
        );
    }
    // Both copies have to be the same file, or the two builds render a
    // different wordmark and only the deployed one is wrong.
    is_same(
        md5_file($root . '/public/img/brand/leo-wordmark-footer.png'),
        md5_file($root . '/php/public_html/img/brand/leo-wordmark-footer.png'),
        'the two builds ship different wordmark artwork'
    );
});

// About is the live WHO WE ARE and WHAT WE DO pages combined, so it is checked
// the way the other transcribed pages are: pin the sentences, not a paraphrase.
//
// It used to quote the impact band's 5,685 / $6.9M and a test derived that
// expectation from the band. Neither live page states a figure in its copy --
// they carry the counters, whose real values sit in data-value attributes and
// are the same four numbers the band already renders -- so the transcription
// does not carry figures and there is nothing left to drift. The band is now the
// only place on the site that states them. The client chose to have them on this
// page once; if they want that back it is a line of copy they have to supply,
// not one to compose here. Mirrors test/content.test.js.
test('the About page carries the transcribed copy, and no figures to drift', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $about = null;
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'about') {
            $about = $page;
        }
    }
    ok($about !== null, 'the About page is missing');

    // WHO WE ARE's own headline is the page's lede.
    // Double quotes: \u{...} is only an escape in a double-quoted PHP string, and
    // the published sentence uses a right single quotation mark, not an apostrophe.
    ok(
        str_starts_with($about['summary'], "Leo Foundation\u{2019}s mission is to invest in future generations"),
        'the lede is not the transcribed mission sentence'
    );
    // WHAT WE DO's headline and body.
    ok(
        str_starts_with(
            $about['body'],
            'For nearly 20 years, the LEO Foundation, *formerly known as Grand Canyon University Scholarship Foundation*, has connected'
        ),
        'the opening sentence is not the transcribed one'
    );
    ok(str_contains($about['body'], 'Today, college has become out of reach for many aspiring students.'), 'the WHAT WE DO paragraph is gone');
    ok(str_contains($about['body'], 'LEO Foundation welcomes you to become a part of a growing, Christ-centered group'), 'the welcome line is gone');

    // The superseded pair must not come back, and nor must the governance
    // sentence that was never transcribed from the live charter.
    ok(preg_match('/3,000|\$5 million/', $about['body']) === 0, 'the old figures are back in the About copy');
    ok(!str_contains($about['body'], "select each year's recipients"), 'the untranscribed governance claim is back');
});

// LEO is an acronym and the live homepage publishes a write-up for each word.
// The words carry the brand, so a reordered or reworded set is a real change.
test('the LEO pillars spell out Leadership, Education, Opportunity', function () {
    $seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/data/content.json'), true);
    $words = array_map(fn ($p) => $p['word'], $seed['pillars']);
    is_same($words, ['Leadership', 'Education', 'Opportunity'], 'the pillars changed');
    is_same(array_map(fn ($p) => $p['order'], $seed['pillars']), [1, 2, 3], 'the pillars reordered');
    is_same(implode('', array_map(fn ($w) => $w[0], $words)), 'LEO', 'the pillars no longer spell LEO');
    foreach ($seed['pillars'] as $p) {
        is_same(array_keys($p), ['id', 'word', 'tagline', 'body', 'order'], $p['id'] . ' has the wrong keys');
        // The live site publishes the word and one paragraph, no tagline.
        ok(mb_strlen($p['body']) > 300, $p['word'] . ' lost its write-up');
    }
});

// The board page is off the main nav by design, so the contact page is the only
// way in. A reworded contact body must not quietly strip the link.
test('the contact page links to the board page', function () {
    $root = dirname(__DIR__, 2);
    $seed = json_decode(file_get_contents($root . '/data/content.json'), true);
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'contact') {
            ok(str_contains($page['body'], '](/board)'), 'the contact page no longer links to /board');
        }
    }
});

test('recipients with no published year group without a year heading', function () use ($base) {
    $store = tempStore(array_merge($base, ['recipients' => [
        ['id' => 'y1', 'name' => 'Sophia'],
        ['id' => 'y2', 'name' => 'Elijah'],
    ]]));
    $shown = Content::publicRecipients($store, OPEN_DAY);
    $groups = Content::groupRecipientsByYear($shown);
    is_same(count($groups), 1);
    is_same($groups[0]['year'], '');
    is_same(count($groups[0]['items']), 2);
    is_same(Content::awardStats($shown)['yearCount'], 0);
});

test('award totals count only published recipients', function () use ($base) {
    $store = tempStore($base);
    $stats = Content::awardStats(Content::publicRecipients($store, OPEN_DAY));
    is_same($stats['totalAwarded'], 1500);
    is_same($stats['recipientCount'], 2);
    is_same($stats['yearCount'], 2);
});

test('announcements swap over with the enrollment period', function () use ($base) {
    $store = tempStore($base);
    is_same(array_column(Content::activeAnnouncements($store, OPEN_DAY, 'open'), 'id'), ['n1', 'n3']);
    is_same(array_column(Content::activeAnnouncements($store, CLOSED_DAY, 'upcoming'), 'id'), ['n2', 'n3']);
});

test('money is formatted, and zero renders as nothing', function () {
    is_same(Content::formatMoney(225000), '$225,000');
    is_same(Content::formatMoney(0), '');
    is_same(Content::formatMoney('x'), '');
});

echo "\nAdmin forms\n";

test('nested fields use bracket names, because PHP rewrites dots to underscores', function () {
    // Regression: name="publish.showFrom" arrives as $_POST['publish_showFrom'],
    // so the value silently never saved.
    is_same(Admin::fieldName('publish.showFrom'), 'publish[showFrom]');
    is_same(Admin::fieldName('name'), 'name');
    is_same(Admin::fieldId('publish.showFrom'), 'publish-showFrom');
});

test('a submitted window is stored, not dropped', function () {
    $fields = [Admin::windowField()];
    $body = ['window' => ['type' => 'fixed', 'opensOn' => '2026-09-01', 'closesOn' => '2026-09-30']];
    $record = Admin::applyFields([], $body, $fields);

    is_same($record['window']['type'], 'fixed');
    is_same($record['window']['opensOn'], '2026-09-01');
    is_same($record['window']['closesOn'], '2026-09-30');
});

test('an inherited window keeps no stale dates', function () {
    $record = Admin::applyFields(
        ['window' => ['type' => 'fixed', 'opensOn' => '2026-09-01']],
        ['window' => ['type' => 'inherit']],
        [Admin::windowField()]
    );
    is_same($record['window'], ['type' => 'inherit']);
});

test('publish dates round-trip through nested arrays', function () {
    $record = Admin::applyFields([], ['publish' => ['showFrom' => '2026-04-01']], Admin::publishFields());
    is_same($record['publish']['showFrom'], '2026-04-01');
    is_same($record['publish']['showUntil'], '');
    is_same($record['draft'], false);
});

// The board roster rides on the page record but has no field in the page form,
// so a save rebuilds the record from the posted fields alone. applyFields()
// takes the existing record as its base, which is the only thing keeping an
// admin edit to the copy from silently deleting six people. Mirrors the same
// assertion in test/routes.test.js.
test('editing the board page in admin keeps the roster', function () {
    $existing = [
        'id' => 'page-board',
        'slug' => 'board',
        'title' => 'Board of Directors',
        'members' => [['name' => 'Madeline LoConti Winney', 'role' => 'Chief Executive Officer']],
    ];
    $fields = Admin::resources()['pages']['fields'];
    $record = Admin::applyFields($existing, [
        'title' => 'Board of Directors',
        'slug' => 'board',
        'summary' => 'Rewritten in the admin area.',
        'body' => 'Rewritten too.',
    ], $fields);

    is_same($record['summary'], 'Rewritten in the admin area.');
    is_same($record['members'], $existing['members'], 'an admin save dropped the roster');
});

test('field types coerce the way the store expects', function () {
    $fields = [
        ['key' => 'amount', 'type' => 'number', 'label' => 'Amount'],
        ['key' => 'essayPrompts', 'type' => 'lines', 'label' => 'Prompts'],
        ['key' => 'featured', 'type' => 'checkbox', 'label' => 'Featured'],
        ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
    ];
    $record = Admin::applyFields([], [
        'amount' => '1000',
        'essayPrompts' => "First prompt\n\n  Second prompt  \n",
        'featured' => 'on',
        'name' => '  Ada  ',
    ], $fields);

    is_same($record['amount'], 1000);
    is_same($record['essayPrompts'], ['First prompt', 'Second prompt']);
    is_same($record['featured'], true);
    is_same($record['name'], 'Ada');
});

echo "\nMount point\n";

/** Build an App as if index.php were served from $scriptName. */
function appAt(string $scriptName, array $config = []): Leo\App
{
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    return new Leo\App(
        __DIR__ . '/../leo-app/data/content.json',
        __DIR__ . '/../leo-app/views',
        $config
    );
}

/** path() is private; the request path is worth testing directly. */
function requestPath(Leo\App $app, string $requestUri): string
{
    $_SERVER['REQUEST_URI'] = $requestUri;
    $method = new \ReflectionMethod($app, 'path');
    return $method->invoke($app);
}

test('a domain root has no prefix', function () {
    $app = appAt('/index.php');
    is_same($app->basePath(), '');
    is_same($app->url('/scholarships'), '/scholarships');
    is_same(requestPath($app, '/scholarships'), '/scholarships');
    is_same(requestPath($app, '/'), '/');
});

test('a userdir mount prefixes links and strips itself from the request', function () {
    // cPanel's temporary URL serves the account from /~username/.
    $app = appAt('/~leofoundationusa/index.php');
    is_same($app->basePath(), '/~leofoundationusa');
    is_same($app->url('/scholarships'), '/~leofoundationusa/scholarships');
    is_same($app->url('/'), '/~leofoundationusa/');
    is_same(requestPath($app, '/~leofoundationusa/scholarships'), '/scholarships');
    is_same(requestPath($app, '/~leofoundationusa/'), '/');
    is_same(requestPath($app, '/~leofoundationusa'), '/');
});

test('a nested subdirectory works the same way', function () {
    $app = appAt('/staging/leo/index.php');
    is_same($app->basePath(), '/staging/leo');
    is_same(requestPath($app, '/staging/leo/recipients'), '/recipients');
});

test('config can override the derived mount point', function () {
    $app = appAt('/~leofoundationusa/index.php', ['base_path' => '/custom/']);
    is_same($app->basePath(), '/custom');
    is_same($app->url('/faq'), '/custom/faq');
});

test('url leaves anything that is not app-absolute alone', function () {
    $app = appAt('/~leofoundationusa/index.php');
    is_same($app->url('https://example.org'), 'https://example.org');
    is_same($app->url(''), '');
});

test('stored links are prefixed only when internal', function () {
    is_same(link_url('/recipients', '/~leofoundationusa'), '/~leofoundationusa/recipients');
    is_same(link_url('https://example.org/give', '/~leofoundationusa'), 'https://example.org/give');
    is_same(link_url('mailto:a@b.c', '/~leofoundationusa'), 'mailto:a@b.c');
    is_same(link_url('/recipients', ''), '/recipients');
    is_same(link_url(null, '/x'), '');
});

echo "\nMarkdown\n";

test('headings, lists and emphasis render', function () {
    ok(str_contains(Markdown::render('## Who can apply'), '<h2>Who can apply</h2>'));
    ok(str_contains(Markdown::render('### Deadlines'), '<h3>Deadlines</h3>'));
    ok(str_contains(Markdown::render("- One\n- Two"), '<ul>'));
    ok(str_contains(Markdown::render('**bold**'), '<strong>bold</strong>'));
    ok(str_contains(Markdown::render('Plain text'), '<p>Plain text</p>'));
});

// The page title is the only h1 on the page, so a lone '#' in admin copy is
// clamped up to h2. The Node build's render() does the same.
test('a body heading never renders as a second h1', function () {
    $rendered = Markdown::render('# Top level');
    ok(!str_contains($rendered, '<h1'), 'body copy produced an h1');
    ok(str_contains($rendered, '<h2>Top level</h2>'));
});

test('renderSections anchors each heading and lists them in order', function () {
    $result = Markdown::renderSections("## How do I apply?\n\nBody.\n\n## What next?\n\nMore.");
    is_same(array_column($result['headings'], 'id'), ['how-do-i-apply', 'what-next'], 'ids');
    ok(str_contains($result['html'], '<h2 id="how-do-i-apply">How do I apply?</h2>'));
    ok(str_contains($result['html'], '<h2 id="what-next">What next?</h2>'));
});

test('repeated headings get distinct ids', function () {
    $result = Markdown::renderSections("## Same\n\n## Same\n\n## Same");
    is_same(array_column($result['headings'], 'id'), ['same', 'same-2', 'same-3'], 'ids');
});

// The FAQ was transcribed verbatim from the live WordPress page, which carries
// thirteen questions. Both builds must anchor them identically or a link into
// one build's FAQ lands nowhere in the other.
test('the seeded FAQ carries every question, anchored', function () {
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    $faq = null;
    foreach ($seed['pages'] as $page) {
        if ($page['slug'] === 'faq') {
            $faq = $page;
        }
    }
    ok($faq !== null, 'no faq page in the seed');

    $result = Markdown::renderSections($faq['body']);
    is_same(count($result['headings']), 13, 'question count');
    is_same($result['headings'][0]['id'], 'how-do-i-know-if-i-am-eligible-to-apply', 'first anchor');
    is_same($result['headings'][12]['id'], 'how-is-my-scholarship-awarded', 'last anchor');
    foreach ($result['headings'] as $heading) {
        ok($heading['id'] !== '', 'a question produced an empty anchor');
    }
});

test('html in admin copy is escaped, not executed', function () {
    $rendered = Markdown::render('<script>alert(1)</script>');
    ok(!str_contains($rendered, '<script>'), 'script tag survived');
    ok(str_contains($rendered, '&lt;script&gt;'));
});

test('only safe link schemes are linkified', function () {
    ok(str_contains(Markdown::render('[apply](https://example.org/a)'), '<a href="https://example.org/a">apply</a>'));
    ok(!str_contains(Markdown::render('[x](javascript:alert(1))'), '<a href'), 'javascript: url became a link');
});

echo "\nSettings\n";

// The impact heading and supporting lines are easy to add to the form and
// forget in the save handler. That fails silently: the field renders, accepts
// text, and is dropped on submit. This drives the real handler.
test('a settings save keeps the impact heading and supporting lines', function () {
    $dir = sys_get_temp_dir() . '/leo-settings-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $file = $dir . '/content.json';
    file_put_contents($file, json_encode([
        'site' => ['name' => 'LEO Foundation', 'impact' => []],
        'enrollment' => ENROLLMENT,
    ]));

    $app = new \Leo\App($file, __DIR__ . '/../leo-app/views', ['admin_password' => 'x', 'base_path' => '']);

    $_POST = [
        'name' => 'LEO Foundation',
        'timezone' => 'America/Phoenix',
        'impactTitle' => 'A heading that must persist',
        'impact0value' => '$9M+',
        'impact0label' => 'in scholarships',
        'impact0detail' => 'A supporting line that must persist',
        'enrollmentType' => 'annual',
        'enrollmentOpensOn' => '11-01',
        'enrollmentClosesOn' => '03-31',
    ];

    $method = new \ReflectionMethod($app, 'saveSettings');
    $method->setAccessible(true);
    @$method->invoke($app);
    $_POST = [];

    $site = (new Store($file))->site();
    is_same($site['impactTitle'] ?? null, 'A heading that must persist', 'heading persisted');
    is_same($site['impact'][0]['detail'] ?? null, 'A supporting line that must persist', 'detail persisted');
    is_same($site['impact'][0]['value'] ?? null, '$9M+', 'figure persisted');
});

echo "\nAuth\n";

test('a signed token verifies and a tampered one does not', function () {
    $auth = new Leo\Auth('secret-password', 'signing-key');
    $token = $auth->issue();
    ok($auth->verify($token));
    ok(!$auth->verify($token . 'x'));
    ok(!$auth->verify('9999999999.notasignature'));
    ok(!$auth->verify(null));
});

test('an expired token is rejected', function () {
    $auth = new Leo\Auth('secret-password', 'signing-key');
    // Sign a timestamp already in the past.
    $expired = (string) (time() - 10);
    $reflection = new \ReflectionMethod($auth, 'sign');
    $signature = $reflection->invoke($auth, $expired);
    ok(!$auth->verify($expired . '.' . $signature));
});

test('the password check is exact', function () {
    $auth = new Leo\Auth('secret-password', 'signing-key');
    ok($auth->passwordMatches('secret-password'));
    ok(!$auth->passwordMatches('Secret-Password'));
    ok(!$auth->passwordMatches(''));
    ok(!(new Leo\Auth('', 'k'))->passwordMatches(''), 'an unset password must never match');
});


echo "\nLegal pages\n";

test('legalPages picks only the pages that opt in', function () {
    $pages = [
        ['slug' => 'about'],
        ['slug' => 'privacy', 'legal' => true],
        ['slug' => 'faq', 'legal' => false],
        ['slug' => 'terms', 'legal' => true],
    ];
    $legal = Content::legalPages($pages);
    is_same(count($legal), 2, 'wrong number of legal pages');
    is_same($legal[0]['slug'], 'privacy');
    is_same($legal[1]['slug'], 'terms');
    // A missing flag is not a legal page, and neither is a truthy string --
    // the footer list has to be opt-in and nothing else.
    is_same(count(Content::legalPages([['slug' => 'about'], ['slug' => 'x', 'legal' => 'yes']])), 0);
});

// The privacy policy is the live page verbatim, so the test pins the sentences
// that carry the legal weight rather than a word count. Nine bold section
// headings in the source became nine markdown headings, which is what earns the
// page its jump-to index.
test('the seeded privacy policy is the transcribed page', function () {
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    $privacy = null;
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'privacy') {
            $privacy = $page;
        }
    }
    ok($privacy !== null, 'no privacy page in the seed');
    is_same($privacy['legal'] ?? null, true, 'the page does not opt in to the footer');
    is_same($privacy['inNav'] ?? null, false, 'legal copy does not belong in the header');

    ok(str_contains($privacy['body'], 'Last Updated: May 5, 2025'), 'the policy lost its date');
    ok(
        str_contains($privacy['body'], 'Our Website is not intended for children under 13 years of age.'),
        "the children's clause was reworded"
    );

    $result = Markdown::renderSections($privacy['body']);
    is_same(count($result['headings']), 9, 'section count');
    is_same($result['headings'][0]['id'], '1-information-we-collect', 'first anchor');
    is_same($result['headings'][8]['id'], '9-changes-to-our-privacy-policy', 'last anchor');
});

// The footer list is the only place a legal page is linked, so a build that
// stops rendering it hides the policy entirely. A view change made once is the
// drift this suite exists to catch.
test('both footers render the legal list', function () {
    $root = dirname(__DIR__, 2);
    foreach ([
        ['views/partials/foot.ejs', 'legalPages'],
        ['php/leo-app/views/partials/foot.php', '$legalPages'],
    ] as [$view, $needle]) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, $needle), $view . ' does not render the legal pages');
        ok(str_contains($src, 'foot-legal-head'), $view . ' lost the Legal heading');
    }
});

// The privacy policy is the first page with no summary, and that exposed a
// divergence the seed had hidden: PHP's ?? falls back on null only, so the page
// emitted an empty meta description while the EJS twin fell back to the mission.
// Neither suite could see it -- only the rendered output differs.
test('a page with no summary still describes itself in both builds', function () {
    $root = dirname(__DIR__, 2);
    $php = file_get_contents($root . '/php/leo-app/views/partials/head.php');
    // Single-quoted: these needles contain PHP variables and must not interpolate.
    ok(
        !str_contains($php, 'e($description ?? ($site[\'mission\']'),
        'the PHP head still falls back on null only, so an empty summary blanks the description'
    );
    ok(
        str_contains($php, '($description ?? \'\') !== \'\''),
        'the PHP head does not test for an empty summary'
    );

    // The page that exposed it: no summary in the seed, by design.
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'privacy') {
            is_same($page['summary'] ?? null, '', 'the policy publishes no summary, so none is invented');
        }
    }
});

// The mark is centred over the wordmark by sharing a wrapper with it: the
// footer column is a 1fr track and centring in that would float the mark away
// from the name it belongs to. A build that loses the wrapper still renders
// both images, just ranged left -- no error, no failing route, which is exactly
// the kind of silent drift this suite exists to catch.
test('both footers stack the mark over the wordmark in one centred block', function () {
    $root = dirname(__DIR__, 2);
    foreach (['views/partials/foot.ejs', 'php/leo-app/views/partials/foot.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'class="foot-sign"'), $view . ' lost the sign-off wrapper');
        // Order matters: the mark reads as the first line of the pair.
        $mark = strpos($src, 'foot-mark');
        $lockup = strpos($src, 'foot-lockup');
        ok($mark !== false && $lockup !== false, $view . ' is missing one of the pair');
        ok($mark < $lockup, $view . ' puts the wordmark above the mark');
    }

    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        // Matched on the declarations rather than the whole rule, which has
        // grown comments and a container-type line. The width is deliberately
        // not pinned to a number here -- it is a design value that has already
        // moved twice -- only that the block is capped at all, since everything
        // inside it is sized as a share of that cap.
        ok(
            preg_match('/\.foot-sign \{[^}]*?width: min\(\d+px, 100%\);/s', $css) === 1,
            $sheet . ' does not cap the sign-off block'
        );
        ok(str_contains($css, 'container-type: inline-size;'), $sheet . ' does not scale the strapline with the block');
        // The strapline is centred under the wordmark, not ranged left with it.
        ok(
            preg_match('/\.foot-strap \{[^}]*?text-align: center;/s', $css) === 1,
            $sheet . ' does not centre the strapline'
        );
        ok(str_contains($css, 'margin: 0 auto 14px'), $sheet . ' does not centre the mark');
        // The nudge that ranged the mark left is gone; leaving it would pull the
        // centred mark off by its own left padding.
        ok(!str_contains($css, 'margin-left: -15px'), $sheet . ' still carries the left-alignment nudge');
    }
});

// The contact cards are sized from the email address, which is the widest thing
// in them and has no space to break on. Two numbers are load-bearing and both
// are easy to "tidy" back: the 330px minimum track is what makes auto-fit drop
// to two columns rather than squeeze three too narrow, and the container query
// is what stops three cards over two columns leaving a panel of the grid's own
// rule colour showing on the last row.
test('the contact cards are sized to hold the email on one line', function () {
    $root = dirname(__DIR__, 2);
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        $grid = null;
        if (preg_match('/\.contact-grid \{[^}]*\}/s', $css, $m)) {
            $grid = $m[0];
        }
        ok($grid !== null, $sheet . ' has no .contact-grid rule');
        ok(
            str_contains($grid, 'minmax(330px, 1fr)'),
            $sheet . ': the minimum track no longer holds the email on one line'
        );
        ok(
            preg_match('/max-width: (\d+)px/', $grid, $w) === 1 && (int) $w[1] >= 1000,
            $sheet . ': the grid is too narrow for three cards that each hold the address'
        );
        ok(
            str_contains($css, '@container (min-width: 661px) and (max-width: 991px)'),
            $sheet . ': nothing closes the half-empty last row at two columns'
        );
        // The safety net stays: a longer address entered in /admin must wrap
        // inside the card rather than overrun it.
        ok(str_contains($css, 'overflow-wrap: anywhere;'), $sheet . ' lost the wrap safety net');
    }
});

// Equal 1fr tracks are even by the ruler and not to the eye -- the menus do not
// fill their columns, so the footer read left-heavy with a ragged right edge.
// The spread that fixes it is three declarations working together and any one of
// them alone does nothing, which is exactly the kind of rule a later tidy-up
// removes without noticing.
test('the footer spreads its columns rather than sharing equal tracks', function () {
    $root = dirname(__DIR__, 2);
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        ok(
            preg_match('/@media \(min-width: 1080px\) \{\s*\.foot-grid \{(.*?)\}/s', $css, $m) === 1,
            $sheet . ' has no footer spread rule'
        );
        $rule = $m[1];
        ok(str_contains($rule, 'grid-template-columns: 320px max-content max-content;'), $sheet . ': the menu columns are not sized to their content');
        ok(str_contains($rule, 'justify-content: space-between;'), $sheet . ': the leftover width is not spread between the columns');
        // The fallback below the breakpoint has to stay, or a narrow window gets
        // three content-width columns bunched at the left.
        // Matched on the declaration inside the base rule, not on the whole
        // line: the rule is reformatted whenever it gains a property, and an
        // assertion that pins its exact text fails on formatting rather than on
        // behaviour. That is what it did when align-items was added to it.
        ok(
            preg_match('/\.foot-grid \{[^}]*grid-template-columns: repeat\(auto-fit, minmax\(220px, 1fr\)\);/s', $css) === 1,
            $sheet . ' lost the auto-fit fallback under the breakpoint'
        );
    }
});

// The menus sit on the brand column's middle. Three declarations do it and the
// two margin resets look like tidying rather than layout, so they are the ones a
// later pass would drop -- and dropping either leaves the menus a few pixels
// high with nothing failing.
test('the footer menus centre against the brand column', function () {
    $root = dirname(__DIR__, 2);
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        ok(
            preg_match('/\.foot-grid \{[^}]*align-items: center;/s', $css) === 1,
            $sheet . ': the footer columns are not centred against each other'
        );
        ok(
            str_contains($css, '.foot-grid > div > :last-child { margin-bottom: 0; }'),
            $sheet . ": the brand column's trailing margin is back, which pushes its box past its ink"
        );
        ok(
            str_contains($css, '.foot li:last-child { margin-bottom: 0; }'),
            $sheet . ': the last list item keeps its margin, which lifts the menus off centre'
        );
    }
});

// A checkbox the save handler does not read comes back false on the first admin
// edit, which would drop the page out of the footer silently. Both page forms
// have to declare it.
test('both page forms offer the legal checkbox', function () {
    $root = dirname(__DIR__, 2);
    foreach (['src/routes/admin.js', 'php/leo-app/src/Admin.php'] as $file) {
        $src = file_get_contents($root . '/' . $file);
        ok(str_contains($src, "'legal'"), $file . ' does not declare the legal field');
    }
});

echo "\n" . str_repeat('-', 46) . "\n";
echo ($failed === 0 ? "ALL PASSED" : "FAILURES") . ": $passed passed, $failed failed\n\n";
exit($failed === 0 ? 0 : 1);
