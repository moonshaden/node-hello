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

// The two menus start on one line as each other, at the top of the row. They
// used to centre individually against the brand column, which put a 278px
// column of links and a 217px column of contact details 30px apart at the top --
// the client asked for them level. The margin resets keep each column's box
// tight to its ink, which is what stops the alignment being a few pixels out;
// they look like tidying rather than layout, so they are the ones a later pass
// would drop.
test('the footer menus start on one line as each other', function () {
    $root = dirname(__DIR__, 2);
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        // Every column ranges to the start of the row rather than centring by
        // its own height -- the menus so they line up with each other, and the
        // sign-off so the 100px below does not drift it down when the taller
        // menu outgrows it.
        ok(
            preg_match('/\.foot-grid > div \{[^}]*align-self: start;/s', $css) === 1,
            $sheet . ': the footer columns centre by their own height again, so they do not line up'
        );
        // The drop is on the menus only, and only where the three sit side by
        // side: below that the grid wraps them into a column, where the same
        // margin is 100px between every stacked block rather than a drop down
        // the row.
        ok(
            preg_match('/@media \(min-width: 861px\) \{[^}]*\.foot-grid > div \+ div \{[^}]*margin-top: 60px;/s', $css) === 1,
            $sheet . ': the menus have lost their drop, or it is no longer scoped to the side-by-side layout'
        );
        ok(
            str_contains($css, '.foot-grid > div > :last-child { margin-bottom: 0; }'),
            $sheet . ": the brand column's trailing margin is back, which pushes its box past its ink"
        );
        ok(
            str_contains($css, '.foot li:last-child { margin-bottom: 0; }'),
            $sheet . ': the last list item keeps its margin, which pads the menu column past its ink'
        );
    }
});

// marked renders `![alt](src)` as an image; the PHP Markdown here has no image
// rule at all, so its link rule matches the bracket pair and leaves the `!` in
// front of it. Checked, not assumed:
//
//   node  <p><img src="/x.jpg" alt="A"></p>
//   php   <p>!<a href="/x.jpg">A</a></p>
//
// So the deployed build shows an exclamation mark and a link where the dev twin
// shows a picture, with both suites green -- only the cross-build render diff
// catches it. Page bodies are editable in /admin, so this guards the copy rather
// than any particular page.
test('no page body contains a markdown image', function () {
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    foreach ($seed['pages'] as $page) {
        ok(
            preg_match('/!\[[^\]]*\]\(/', $page['body'] ?? '') === 0,
            ($page['slug'] ?? '?') . ' puts an image in its markdown body, which the two builds render differently'
        );
    }
});

// The donor strip is one partial shared by the homepage and by any page that
// opts in, so the two cannot drift. Before this it was the same twenty lines of
// markup written out twice per build -- four copies.
// The five memorial scholarships carry photographs transcribed from the live
// site. Two of the five publish a composite of several separate pictures, which
// are split apart here and stacked -- a three-up composite in a 344px column
// renders each face about 40px across. So the record holds an ARRAY, and this
// pins the data, the files and the CSS trap the stack walked into.
// The stylesheet and the script carry a content hash; images did not, and the
// consequence showed up on a real page. The lion mark was replaced in place --
// same filename, navy plate swapped for a transparent one -- and the client
// still saw the navy version, because nothing about the URL had changed and
// their browser served what it already had. The deployed bytes were correct the
// whole time, which is what makes this class hard to see from here.
//
// The rendered-output check lives in the node suite, which can serve a page;
// this is the template-level half, and the cross-build render diff ties the two
// builds together.
test('no view renders an image without a content hash', function () {
    $root = dirname(__DIR__, 2);
    $views = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/php/leo-app/views'));
    foreach ($it as $f) {
        if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
            $views[] = $f->getPathname();
        }
    }
    ok(count($views) > 5, 'expected to find the view files, saw ' . count($views));

    foreach ($views as $view) {
        foreach (file($view) as $i => $line) {
            if (strpos($line, '<img') === false && strpos($line, 'src=') === false) {
                continue;
            }
            ok(
                strpos($line, 'src="<?= e(link_url(') === false,
                str_replace($root . '/', '', $view) . ':' . ($i + 1) . ' builds an image src with link_url, which carries no content hash'
            );
        }
    }
});

test('the memorial scholarships carry their photographs, sized and described', function () {
    $root = dirname(__DIR__, 2);

    // slug => the pictures it publishes, each [file suffix, width, height]
    $expected = [
        'richard-mccurdy-club-sports-golf-scholarship' => [['-1', 227, 300], ['-2', 226, 300]],
        'lavern-sharky-and-leona-baker-educational-scholarship' => [['-1', 321, 410], ['-2', 327, 410], ['-3', 444, 390]],
        'joyce-k-smith-nursing-memorial-scholarship' => [['', 924, 300]],
        'tiffany-d-mealman-women-excellence-in-chemistry-and-christian-character-scholarship' => [['', 543, 700]],
        'evan-c-gary-memorial-scholarship' => [['', 250, 312]],
        // The other four published images are logos and award artwork rather
        // than photographs, and they are .png: flat colour and sharp type, which
        // JPEG rings around.
        'gcu-guild-continuing-student-scholarship' => [['', 700, 386, 'png']],
        'skw-play-it-forward-music-scholarship' => [['', 608, 500, 'png']],
        'bhhs-legacy-nursing-health-related-scholarship' => [['', 700, 190, 'png']],
    ];

    // The three LEO-branded awards carry no sponsor's logo of their own, so they
    // take the foundation's own lion -- the same mark the footer and the masthead
    // use, cropped to its own ink and left transparent. One shared file, not
    // three copies.
    //
    // It was matted on the brand navy first, on the reasoning that a white-and-
    // gold mark would vanish on the #fdfcfa band, and a pixel count agreed: 42%
    // of it composites to within 18/255 of the background. The count was
    // measuring the wrong thing. That 42% is the lion's white BODY, which is
    // drawn by its grey shading and bounded by the gold mane and arc -- rendered
    // on the band it reads perfectly well. A contrast metric cannot see line
    // work; look at the picture.
    $lion = '/img/scholarships/leo-lion-mark.png';
    $lionSlugs = [
        'leo-foundation-scholarship',
        'leo-foundation-entrepreneurial-scholarship',
        'leo-foundation-christian-studies-scholarship',
    ];

    // The live site publishes no image on these two and none was invented for
    // them either -- "all of them" has to mean all of them and no more.
    $withoutPhoto = [
        'foundation-theatre-scholarship',
        'foster-youth-scholarships',
    ];

    foreach (['data/content.json', 'php/leo-app/data/content.json'] as $store) {
        $seed = json_decode(file_get_contents($root . '/' . $store), true);
        $bySlug = [];
        foreach ($seed['scholarships'] as $record) {
            $bySlug[$record['slug']] = $record;
        }
        foreach ($expected as $slug => $pictures) {
            $record = $bySlug[$slug] ?? null;
            ok($record !== null, $store . ': ' . $slug . ' is missing');
            $photos = $record['photos'] ?? [];
            ok(count($photos) === count($pictures),
                $store . ': ' . $slug . ' has ' . count($photos) . ' photos, expected ' . count($pictures));
            foreach ($pictures as $i => $picture) {
                [$suffix, $w, $h] = $picture;
                $ext = $picture[3] ?? 'jpg';
                $photo = $photos[$i] ?? [];
                ok(($photo['src'] ?? '') === '/img/scholarships/' . $slug . $suffix . '.' . $ext,
                    $store . ': ' . $slug . ' photo ' . ($i + 1) . ' has the wrong src');
                ok((int) ($photo['width'] ?? 0) === $w && (int) ($photo['height'] ?? 0) === $h,
                    $store . ': ' . $slug . ' photo ' . ($i + 1) . ' is not ' . $w . 'x' . $h);
                // Alt text is not published anywhere live, so it was written
                // here -- but an empty one on a photograph of a named person is a
                // real failure, not a deliberate decorative choice like the logo
                // strip.
                ok(trim($photo['alt'] ?? '') !== '',
                    $store . ': ' . $slug . ' photo ' . ($i + 1) . ' has no description');
            }
        }
    }

    // Both builds must ship every file, or the deployed site shows a broken
    // image where the dev twin shows a photograph.
    foreach ($expected as $slug => $pictures) {
        foreach ($pictures as $picture) {
            $suffix = $picture[0];
            $ext = $picture[3] ?? 'jpg';
            foreach (['public/img/scholarships', 'php/public_html/img/scholarships'] as $dir) {
                $file = $root . '/' . $dir . '/' . $slug . $suffix . '.' . $ext;
                ok(is_file($file) && filesize($file) > 1024, $file . ' is missing or empty');
            }
        }
    }

    foreach (['data/content.json', 'php/leo-app/data/content.json'] as $store) {
        $seed = json_decode(file_get_contents($root . '/' . $store), true);
        foreach ($seed['scholarships'] as $record) {
            if (in_array($record['slug'], $withoutPhoto, true)) {
                ok(empty($record['photos']),
                    $store . ': ' . $record['slug'] . ' has a picture the live site does not publish');
            }
            if (in_array($record['slug'], $lionSlugs, true)) {
                $photos = $record['photos'] ?? [];
                ok(count($photos) === 1 && ($photos[0]['src'] ?? '') === $lion,
                    $store . ': ' . $record['slug'] . ' does not carry the lion mark');
            }
        }
    }
    foreach (['public/img/scholarships', 'php/public_html/img/scholarships'] as $dir) {
        $file = $root . '/' . $dir . '/leo-lion-mark.png';
        ok(is_file($file) && filesize($file) > 1024, $file . ' is missing or empty');
    }

    // No picture anywhere is drawn past its own pixels. The `fill` opt-in that
    // briefly carried the GCU Guild logo is gone, because the client supplied a
    // 1672x941 original in place of the 146x91 one the live site publishes.
    foreach (['data/content.json', 'php/leo-app/data/content.json'] as $store) {
        $seed = json_decode(file_get_contents($root . '/' . $store), true);
        $filled = [];
        foreach ($seed['scholarships'] as $record) {
            foreach ($record['photos'] ?? [] as $photo) {
                if (!empty($photo['fill'])) {
                    $filled[] = $record['slug'];
                }
            }
        }
        ok($filled === [],
            $store . ': ' . implode(', ', $filled) . ' asks to be drawn past its own pixels');
    }


    // .cpanel.yml has no --delete and creates each image directory by hand, so a
    // new one that is not listed simply never arrives on the server.
    $cpanel = file_get_contents($root . '/.cpanel.yml');
    ok(strpos($cpanel, 'public_html/img/scholarships') !== false,
        '.cpanel.yml does not create the scholarships image directory');

    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        // The column is 344px and the narrowest of these is 227px, so a
        // `width: 100%` would upscale it -- measured once already on a 463px
        // composite, which stretched to 688px. The picture caps, never stretches.
        ok(
            preg_match('/\.scholarship-photo img \{[^}]*max-width: 100%;/s', $css) === 1,
            $sheet . ': the scholarship photo does not cap at its own width'
        );
        // The lookbehind matters: `max-width: 100%` contains `width: 100%`, so
        // without it this assertion is satisfied by the very rule it is meant to
        // check is absent, and passes whatever the sheet says.
        ok(
            preg_match('/\.scholarship-photo img \{[^}]*(?<!max-)width: 100%;/s', $css) !== 1,
            $sheet . ': the scholarship photo stretches, which upscales the small ones'
        );
        // `.split` is a two-track grid. Without the wrapper the pictures are a
        // third child, which wraps to a new row and lands under the COPY rather
        // than under the award card.
        ok(
            preg_match('/\.split-side \{[^}]*display: grid;/s', $css) === 1,
            $sheet . ': the card and the photographs do not share the right column'
        );
        ok(
            preg_match('/\.scholarship-photos \{[^}]*flex-direction: column;/s', $css) === 1,
            $sheet . ': the photographs do not stack'
        );
        // Centred, because the pictures are not all as wide as the column.
        ok(
            preg_match('/\.scholarship-photos \{[^}]*align-items: center;/s', $css) === 1,
            $sheet . ': the photographs are not centred in the column'
        );
        // A flex item is stretched on the cross axis by default, which pulled a
        // short picture's BOX down to the min-height floor while the picture
        // itself stayed its own size inside it -- so the column ended on empty
        // space rather than on the picture.
        ok(
            preg_match('/\.scholarship-photo \{[^}]*align-items: center;/s', $css) === 1,
            $sheet . ': a short photograph is stretched to its box rather than centred in it'
        );
        // The stack ends level with the copy only if the COPY is the thing that
        // sizes the grid row. `minmax(0, 1fr)` is what does it: the pictures'
        // track contributes nothing to the column's intrinsic height, so they do
        // not get a vote, while the card sits in `auto` and does.
        //
        // That second half matters as much as the first. The column used to take
        // its whole contents out of flow, which stopped the pictures voting but
        // took the CARD with them -- and on the Foster Youth page, 240px of copy
        // against a 376px card, the card ran 71px past the band and was drawn
        // over the footer.
        ok(
            preg_match('/\.split-side \{[^}]*grid-template-rows: auto minmax\(0, 1fr\);/s', $css) === 1,
            $sheet . ': the side column does not size its row from the card alone, so a short page clips it'
        );
        ok(
            strpos($css, '.split-side-inner') === false,
            $sheet . ': the out-of-flow inner is back, which is how the card got clipped'
        );
        ok(
            preg_match('/\.split \{[^}]*align-items: stretch;/s', $css) === 1,
            $sheet . ': the right column does not stretch, so there is no bottom to reach'
        );
        // A lone picture takes no floor: the floor otherwise puts a 110px box
        // around a 71px-tall logo and the column ends 39px below the picture.
        ok(
            preg_match('/\.scholarship-photo:only-child \{[^}]*min-height: 0;/s', $css) === 1,
            $sheet . ': a lone short picture gets a box taller than itself'
        );
        // There is no longer ANY exception to "never draw a picture past its own
        // pixels". The one that existed was for a logo whose only published file
        // was 146x91; the client supplied a 1672x941 original and the opt-in went
        // with it. If this rule comes back, someone is papering over a small
        // source file instead of asking for a bigger one.
        ok(
            strpos($css, '.scholarship-photo.is-fill') === false,
            $sheet . ': the fill opt-in is back, which upscales a picture past its own pixels'
        );
        // The reserve, and it is the one rule here that prevents a visible
        // defect rather than an untidy one. The inner is out of flow, so the
        // band CANNOT grow to contain it and anything that does not fit paints
        // over what is below. On the SKW page it did: 406px of copy against a
        // 399px card is minus thirteen pixels of room, and the tile ran 231px
        // past the band and landed on the footer -- `elementFromPoint` returned
        // `.foot-grid` at the picture's own centre. Reserving card + gap +
        // 200px means a column that carries pictures always has room for them.
        ok(
            preg_match('/\.split-side\.has-photos \{[^}]*min-height: 649px;/s', $css) === 1,
            $sheet . ': a column with pictures reserves no room, so they can paint over the footer'
        );
        // The copy's last paragraph carries a bottom margin, so without this the
        // pictures finish a measured 18px below the last line of text.
        ok(
            preg_match('/\.split > div > :last-child > :last-child,\s*\.split > div > :last-child \{[^}]*margin-bottom: 0;/s', $css) === 1,
            $sheet . ': the copy column keeps its trailing margin, so the columns end 18px apart'
        );
    }
});

test('the donor strip is one shared partial, opted into by the page record', function () {
    $root = dirname(__DIR__, 2);
    foreach (['views/partials/logo-strip.ejs', 'php/leo-app/views/partials/logo-strip.php'] as $partial) {
        ok(is_file($root . '/' . $partial), $partial . ' is missing');
    }
    // Neither homepage may carry its own copy of the markup.
    foreach (['views/home.ejs', 'php/leo-app/views/home.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'logo-strip'), $view . ' no longer renders the strip');
        ok(!str_contains($src, 'class="logo-track"'), $view . ' still inlines the strip markup');
    }
    // Both page templates render the large variant behind the record flag, so a
    // page opts in through content rather than through a slug in a template.
    foreach (['views/page.ejs', 'php/leo-app/views/page.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'logoStrip'), $view . ' does not read the record flag');
        ok(str_contains($src, "'large'"), $view . ' does not ask for the large variant');
        // Inline, and inside the prose column: the strip belongs directly under
        // the last line of copy, not in a band below the section. A template
        // that renders it outside .prose puts it back below the card.
        $prose = strpos($src, 'class="prose"');
        $strip = strpos($src, 'logo-strip');
        ok($prose !== false && $strip !== false && $strip > $prose, $view . ' renders the strip outside the prose column');
        ok(str_contains($src, 'stripInline'), $view . ' does not ask for the inline variant');
    }
    // And the page that asked for it has the flag.
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    $community = null;
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'community') {
            $community = $page;
        }
    }
    is_same($community['logoStrip'] ?? null, true, 'the community page no longer carries the strip');

    // The large variant's width cap is what keeps three or four marks on screen:
    // the widest wordmark is 5.88:1, so uncapped it is 694px at 118px tall.
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        ok(
            preg_match('/\.is-large \.logo-run img \{[^}]*max-width: 340px;/s', $css) === 1,
            $sheet . ': the large marks have no width cap, so one wordmark fills the row'
        );
        // The inline strip must NOT clear the float. overflow:hidden already
        // makes it a block formatting context, so it sits beside the card and
        // therefore directly under the copy; clearing it would drop it below the
        // card and re-open the 352px gap this was meant to close.
        ok(
            preg_match('/\\.logo-strip\\.is-inline \\{[^}]*clear:/s', $css) !== 1,
            $sheet . ': the inline strip clears the card, which puts it back below it'
        );
        ok(
            preg_match('/\\.logo-strip\\.is-inline \\.logo-run img \\{[^}]*max-width: 205px;/s', $css) === 1,
            $sheet . ': the inline marks are not sized for the column'
        );
        // The inline strip renders inside `.prose`, where `.prose ul li::before`
        // paints a 6px gold dot in front of every list item and indents it 22px.
        // The logos are list items, so each mark arrived with a gold dot beside
        // it. `list-style: none` on `.logo-run` does not stop it -- the dot is a
        // generated pseudo-element, not a list marker -- and the override needs
        // two classes (0,2,2) to outrank `.prose ul li::before` (0,1,3).
        ok(
            preg_match('/\\.prose \\.logo-run li::before \\{[^}]*content: none;/s', $css) === 1,
            $sheet . ': the prose bullet is back on the inline strip logos'
        );
        ok(
            preg_match('/\\.prose \\.logo-run li \\{[^}]*padding-left: 0;/s', $css) === 1,
            $sheet . ': the prose list indent is back on the inline strip logos'
        );
    }
});

// The recipient card is shared by /recipients, the homepage and a scholarship's
// own page. Only the scholarship page asks for the horizontal variant, so this
// pins BOTH halves: that the scholarship template asks for it, and that the
// shared partial still renders the stacked card when nobody does.
test('the recipient card lays out horizontally only where it is asked to', function () {
    $root = dirname(__DIR__, 2);
    foreach (['views/scholarship.ejs', 'php/leo-app/views/scholarship.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, "'row'"), $view . ' does not ask for the row layout');
    }
    foreach (['views/partials/recipient-card.ejs', 'php/leo-app/views/partials/recipient-card.php'] as $partial) {
        $src = file_get_contents($root . '/' . $partial);
        ok(str_contains($src, 'cardLayout'), $partial . ' does not read the layout flag');
        ok(str_contains($src, 'recipient-row'), $partial . ' cannot render the row variant');
    }
    // The pages that did not ask for it must not have it applied by a stray
    // selector: the variant is a class on the card, never a page-level rule.
    foreach (['views/recipients.ejs', 'php/leo-app/views/recipients.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(!str_contains($src, "'row'"), $view . ' asks for the row layout, which it should not');
    }

    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        // The portrait is the whole point: unconstrained it filled the 690px
        // column at 4:5 and stood 808px tall.
        ok(
            preg_match('/\\.recipient-row \\.portrait \\{[^}]*width: 265px;/s', $css) === 1,
            $sheet . ': the row portrait has no width, so it fills the column again'
        );
        // The portrait's height is the text's height. That needs BOTH the grid
        // item stretched and a real height on the image -- stretching alone
        // gives the box the height and leaves the picture drawing at its own
        // ratio inside it.
        ok(
            preg_match('/\\.recipient-row > \\.portrait \\{[^}]*align-self: stretch;/s', $css) === 1,
            $sheet . ': the row portrait does not stretch to the text'
        );
        ok(
            preg_match('/\\.recipient-row \\.portrait \\{[^}]*height: 100%;/s', $css) === 1,
            $sheet . ': the row portrait has no height, so it will not match the text'
        );
        // Stacking is keyed off the list's own width, not the viewport: this
        // column is a .split track, so the card is 532px at a 900px viewport and
        // 732px at 780px once the sidebar drops out.
        ok(
            str_contains($css, '@container (max-width: 600px)'),
            $sheet . ': the row stacks on a viewport query, which gets this column backwards'
        );
        // And the text has to be pinned to column two, or it auto-places under
        // the portrait and the row is a stack with a small photo.
        ok(
            preg_match('/\\.recipient-row > :not\\(\\.portrait\\) \\{[^}]*grid-column: 2;/s', $css) === 1,
            $sheet . ': the row text is not pinned beside the portrait'
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

// The programs page closes its copy with a photograph that ends level with the
// bottom of the aside card, and the three program rows run their picture the
// full height of the text beside it. Both are CSS that measures right and fails
// silently, so both are pinned here.
test('the page picture and the program rows are built the way they measure', function () {
    $root = dirname(__DIR__, 2);

    // A real <img>, not a CSS background: the src then goes through asset_url()
    // and carries a content hash, which a url() in the stylesheet would not.
    foreach ([
        ['views/partials/page-picture.ejs', 'assetUrl(picture.src)'],
        ['php/leo-app/views/partials/page-picture.php', 'asset_url($picture[\'src\']'],
    ] as [$partial, $call]) {
        $src = file_get_contents($root . '/' . $partial);
        // The class prefix, not the whole attribute: the figure gains
        // `has-caption` when the record carries a verse.
        ok(str_contains($src, 'class="page-picture'), $partial . ' does not render the figure');
        ok(str_contains($src, $call), $partial . ' builds the photograph src without a content hash');
    }
    // The class on `.page-flow` is what swaps the float for the grid, so a page
    // that renders a picture must set it -- without it the picture keeps its own
    // ratio and stops short of the card.
    foreach (['views/page.ejs', 'php/leo-app/views/page.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'page-picture'), $view . ' never renders the picture');
        ok(str_contains($src, 'has-picture'), $view . ' never asks for the layout the picture needs');
    }

    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);

        // The programs' photographs are SQUARE, which is the whole picture:
        // the live site publishes all three at 1200x1200. A tall frame crops
        // them, and on the Impact Leadership one -- which has the word
        // LEADERSHIP set into the artwork -- it cut the word to "ADERSH".
        ok(
            preg_match('/^\\.program-photo \\{[^}]*aspect-ratio: 1 \\/ 1;/ms', $css) === 1,
            $sheet . ': the program photo is not square, so it crops its own picture'
        );
        ok(
            preg_match('/\\.program > \\.program-photo \\{[^}]*align-self: start;/s', $css) === 1,
            $sheet . ': the program photo stretches, which crops it to the text'
        );
        // `height: auto` is load-bearing with the width/height attributes: they
        // reserve space before the photo loads, and without it they apply as a
        // real height and beat aspect-ratio (portraits rendered 1000px tall).
        ok(
            preg_match('/^\\.program-photo \\{[^}]*height: auto;/ms', $css) === 1,
            $sheet . ': the width/height attributes will beat the ratio'
        );

        // The caption sits above the scrim: grid items paint in DOM order and a
        // generated ::after is the last of them, so without the z-index the
        // tint covers the verse.
        ok(
            preg_match('/\\.page-picture figcaption \\{[^}]*z-index: 1;/s', $css) === 1,
            $sheet . ': the scrim paints over the verse'
        );
        ok(
            preg_match('/\\.page-picture\\.has-caption::after \\{[^}]*background:/s', $css) === 1,
            $sheet . ': the verse has no scrim under it'
        );
        // Inside `.prose` the verse inherits `.prose blockquote` -- a gold left
        // bar and 18px of indent -- which pushes a centred line off centre. Two
        // classes to outrank it; one only ties and loses on source order.
        ok(
            preg_match('/\\.prose \\.page-picture blockquote \\{[^}]*border-left: 0;/s', $css) === 1,
            $sheet . ': the prose blockquote bar is back on the verse'
        );

        // The picture ends where the card does, and that needs all three of
        // these. The copy column has to be a flex column, the picture has to
        // take the slack in it, and the card has to be a grid item that does
        // NOT stretch -- a stretched card ends level by growing, which is the
        // mistake the scholarship column already made once.
        ok(
            preg_match('/\\.page-flow\\.has-picture > \\.prose \\{[^}]*flex-direction: column;/s', $css) === 1,
            $sheet . ': the copy column is not a flex column, so nothing can take the slack'
        );
        // The picture is the height of the impact panel block that closes the
        // copy on /board -- 181px, measured at the widths where its four panels
        // sit on one row. `flex: 0 0 auto` so the flex column takes that height
        // literally instead of growing or shrinking it.
        ok(
            preg_match('/\\.page-flow\\.has-picture \\.page-picture \\{[^}]*height: 181px;/s', $css) === 1,
            $sheet . ': the picture is not the height of the impact block it is meant to match'
        );
        ok(
            preg_match('/\\.page-flow\\.has-picture \\.page-picture \\{[^}]*flex: 0 0 auto;/s', $css) === 1,
            $sheet . ': the flex column will grow or shrink the picture off its height'
        );
        // Margins do not collapse in a flex column, so without this the copy's
        // last paragraph adds its 18px to the picture's 50 and the gap reads 68
        // against the inline strip's 50.
        ok(
            preg_match('/\\.page-flow\\.has-picture > \\.prose > :nth-last-child\\(2\\) \\{[^}]*margin-bottom: 0;/s', $css) === 1,
            $sheet . ': the gap under the copy is the strip\'s 50px plus an uncollapsed paragraph margin'
        );
        ok(
            preg_match('/\\.page-flow\\.has-picture > \\.sidebar-card \\{[^}]*align-self: start;/s', $css) === 1,
            $sheet . ': the card stretches, so it ends level by growing rather than the picture filling'
        );
        // `.page-flow::after` is a clearfix for the float. In the grid it would
        // be a third grid item and take a row of its own.
        ok(
            preg_match('/\\.page-flow\\.has-picture::after \\{[^}]*content: none;/s', $css) === 1,
            $sheet . ': the clearfix is still a grid item on a page with a picture'
        );
        // `height: 100%` is what makes the picture take the height it is handed
        // rather than drawing at its own ratio inside a taller box -- the same
        // pair the recipient rows and the program photos need.
        ok(
            preg_match('/\\.page-picture img \\{[^}]*height: 100%;/s', $css) === 1,
            $sheet . ': the picture will letterbox instead of filling its box'
        );
        ok(
            preg_match('/\\.page-picture img \\{[^}]*object-fit: cover;/s', $css) === 1,
            $sheet . ': the picture will distort when it is cropped'
        );
        // And it must NOT clear the float -- it sits beside the card by design,
        // and clearing it drops it below the card. Same note as the strip.
        ok(
            preg_match('/^\\.page-picture \\{[^}]*clear:/ms', $css) !== 1,
            $sheet . ': the picture clears the card, which puts it back below it'
        );
    }
});

// The impact figures render in two places now -- the homepage band and, for a
// page record that asks for them, under that page's copy. One partial, so the
// numbers cannot drift; and a variant whose every rule needs TWO classes,
// because a single one only ties with the band rules it is undoing.
test('the impact figures are one component in both places', function () {
    $root = dirname(__DIR__, 2);

    // Neither homepage may keep a second copy of the grid markup.
    foreach ([
        ['views/home.ejs', "include('partials/impact-figures'"],
        ['php/leo-app/views/home.php', "partial('impact-figures'"],
        ['views/page.ejs', "include('partials/impact-figures'"],
        ['php/leo-app/views/page.php', "partial('impact-figures'"],
    ] as [$view, $call]) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, $call), $view . ' does not use the shared impact partial');
        ok(
            !preg_match('/<div class="value">/', $src),
            $view . ' carries its own copy of the impact markup, which will drift'
        );
    }

    // The page variant needs the `.impact` ancestor -- the gold numerals and the
    // white labels are scoped to it, so the grid alone renders unstyled.
    foreach (['views/page.ejs', 'php/leo-app/views/page.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'impact impact-inline'), $view . ' renders the grid without the styling it is scoped to');
    }

    // The board page is the one that asks for them today.
    $seed = json_decode(file_get_contents(__DIR__ . '/../leo-app/data/content.json'), true);
    $board = null;
    foreach ($seed['pages'] as $page) {
        if (($page['slug'] ?? '') === 'board') {
            $board = $page;
        }
    }
    ok(!empty($board['impactFigures']), 'the board page no longer asks for the impact figures');
    ok(count($seed['site']['impact'] ?? []) === 4, 'the four impact figures are not in settings');

    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);

        // EVERY inline rule needs both classes. One is (0,1,0) -- a tie with the
        // `.impact` rules it undoes -- and source order then decides, which put
        // the band's 44px of padding back above the panels below 861px and left
        // the numerals at 51px in a 157px panel with `$6.9M` past its own box.
        ok(
            preg_match('/\\.impact\\.impact-inline \\{[^}]*padding: 0;/s', $css) === 1,
            $sheet . ': the inline figures do not outrank the band padding'
        );
        ok(
            preg_match('/\\.impact\\.impact-inline \\.value \\{[^}]*font-size:/s', $css) === 1,
            $sheet . ': the inline numerals do not outrank the band numerals'
        );
        ok(
            preg_match('/^\\.impact-inline[ .:]/m', $css) !== 1,
            $sheet . ': an inline rule carries one class, so it only ties with the band'
        );
        // The panel is the container, not the viewport: this sits in a
        // `.page-flow` column, where the two diverge -- 181px at a 1280 viewport
        // and 155px at 862.
        ok(
            preg_match('/\\.impact\\.impact-inline \\.impact-grid > div \\{[^}]*container-type: inline-size;/s', $css) === 1,
            $sheet . ': the panels are not containers, so nothing inside can size off them'
        );
        ok(
            preg_match('/\\.impact\\.impact-inline \\.value \\{[^}]*vw/s', $css) !== 1,
            $sheet . ': the inline numerals size off the viewport, which is not this column'
        );
    }
});

// The callout at the top of a page's copy. It is a `notice` string on the
// record, rendered in the same panel the scholarships page uses.
test('a page can open its copy with a callout', function () {
    $root = dirname(__DIR__, 2);
    foreach (['views/page.ejs', 'php/leo-app/views/page.php'] as $view) {
        $src = file_get_contents($root . '/' . $view);
        ok(str_contains($src, 'page-notice'), $view . ' never renders the callout');
        // Two halves: the bold line is an h3, the way the scholarships panel
        // sets it, and the sentence under it is a paragraph.
        ok(str_contains($src, '<h3>'), $view . ' renders the callout without its bold line');
    }
    foreach (['public/css/site.css', 'php/public_html/css/site.css'] as $sheet) {
        $css = file_get_contents($root . '/' . $sheet);
        // No top margin, 30px below: the callout is the first thing in the
        // column, so its top edge lines up with the top of the aside card, and
        // it sits close to the index because the two read as a pair at the head
        // of the column. A test that only looked for `margin:` passed with the
        // gap flipped to the top, so this pins the declaration.
        ok(
            preg_match('/^\\.page-notice \\{[^}]*margin: 0 0 30px;/m', $css) === 1,
            $sheet . ': the callout is not spaced for the top of the column'
        );
        // And 50px under the index -- the break before the copy, the same gap
        // the strip and the programs picture sit on. It was 6px, which read as
        // the index and the copy touching. This is the shared component, so the
        // FAQ and the privacy policy carry it too.
        ok(
            preg_match('/^\\.page-index \\{[^}]*margin-bottom: 50px;/m', $css) === 1,
            $sheet . ': the jump index is back to touching the copy under it'
        );
        // It needs no `clear` and no `flow-root`: `.notice` is a flex container,
        // which already establishes its own formatting context, so it sits
        // beside the floated card. Clearing it would drop it below the card --
        // the note the inline logo strip carries too.
        ok(
            preg_match('/^\\.page-notice \\{[^}]*clear:/m', $css) !== 1,
            $sheet . ': the callout clears the card, which drops it below it'
        );
    }
    // Both stores carry the giving callout, and neither leaves the sentence in
    // the body as well -- it was moved out of the copy, not copied.
    foreach (['data/content.json', 'php/leo-app/data/content.json'] as $store) {
        $seed = json_decode(file_get_contents($root . '/' . $store), true);
        $giving = null;
        foreach ($seed['pages'] as $page) {
            if (($page['slug'] ?? '') === 'donate') {
                $giving = $page;
            }
        }
        ok(!empty($giving['notice']['heading']), $store . ': the giving callout has no bold line');
        ok(!empty($giving['notice']['body']), $store . ': the giving callout has no body');
        foreach ([$giving['notice']['heading'], $giving['notice']['body']] as $line) {
            ok(
                !str_contains($giving['body'], $line),
                $store . ': a callout line is in the body as well, so the page says it twice'
            );
        }
    }
});

// Every image ships twice -- once to the dev twin, once to the build that is
// actually deployed -- and they are copied by hand. A slip leaves the deployed
// site with the old file while the twin shows the new one, which no suite, no
// lint and no cross-build render diff would notice: the diff compares MARKUP,
// and both builds reference `/img/<same path>`. Same class as the content store
// drifting, which is already pinned.
test('every image is byte-identical in both builds', function () {
    $root = dirname(__DIR__, 2);
    $scan = function (string $base): array {
        $out = [];
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($dir as $file) {
            if ($file->isFile()) {
                $out[substr($file->getPathname(), strlen($base) + 1)] = md5_file($file->getPathname());
            }
        }
        ksort($out);
        return $out;
    };
    $node = $scan($root . '/public/img');
    $php = $scan($root . '/php/public_html/img');

    $onlyNode = array_diff_key($node, $php);
    $onlyPhp = array_diff_key($php, $node);
    ok($onlyNode === [], 'images only in the dev twin: ' . implode(', ', array_keys($onlyNode)));
    ok($onlyPhp === [], 'images only in the deployed build: ' . implode(', ', array_keys($onlyPhp)));

    $differ = [];
    foreach (array_intersect_key($node, $php) as $path => $sum) {
        if ($php[$path] !== $sum) {
            $differ[] = $path;
        }
    }
    ok($differ === [], 'these images differ between the builds: ' . implode(', ', $differ));
    ok(count($node) > 50, 'the image scan found almost nothing, so it is not checking anything');
});

echo "\n" . str_repeat('-', 46) . "\n";
echo ($failed === 0 ? "ALL PASSED" : "FAILURES") . ": $passed passed, $failed failed\n\n";
exit($failed === 0 ? 0 : 1);
