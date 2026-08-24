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
    ok(str_contains(Markdown::render('## Who can apply'), '<h3>Who can apply</h3>'));
    ok(str_contains(Markdown::render("- One\n- Two"), '<ul>'));
    ok(str_contains(Markdown::render('**bold**'), '<strong>bold</strong>'));
    ok(str_contains(Markdown::render('Plain text'), '<p>Plain text</p>'));
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

echo "\n" . str_repeat('-', 46) . "\n";
echo ($failed === 0 ? "ALL PASSED" : "FAILURES") . ": $passed passed, $failed failed\n\n";
exit($failed === 0 ? 0 : 1);
