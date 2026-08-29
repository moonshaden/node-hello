'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { Store } = require('../src/store');
const content = require('../src/content');
const markdown = require('../src/markdown');

const OPEN_DAY = '2026-01-15';
const CLOSED_DAY = '2026-08-21';

function makeStore(seed) {
  const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'leo-')), 'content.json');
  fs.writeFileSync(file, JSON.stringify(seed));
  return new Store(file);
}

const BASE = {
  site: { name: 'LEO Foundation', timezone: 'America/Phoenix' },
  enrollment: { type: 'annual', opensOn: '11-01', closesOn: '03-31' },
  scholarships: [
    { id: 'a', slug: 'a', name: 'Inherits the site period' },
    { id: 'b', slug: 'b', name: 'Hides when closed', hideWhenClosed: true },
    { id: 'c', slug: 'c', name: 'Always open', window: { type: 'always' } },
  ],
  recipients: [
    { id: 'r1', name: 'Ada', year: '2025', amount: 1000, featured: true },
    { id: 'r2', name: 'Grace', year: '2024', amount: 500 },
    { id: 'r3', name: 'Draft person', year: '2025', amount: 9999, draft: true },
  ],
  pages: [{ id: 'p1', slug: 'about', title: 'About', inNav: true }],
  announcements: [
    { id: 'n1', title: 'Apply now', showWhen: 'open' },
    { id: 'n2', title: 'Reopens soon', showWhen: 'closed' },
    { id: 'n3', title: 'Always visible' },
  ],
};

test('scholarships inherit the site enrollment period', () => {
  const store = makeStore(BASE);
  const open = content.publicScholarships(store, OPEN_DAY);
  const closed = content.publicScholarships(store, CLOSED_DAY);

  assert.equal(open.find((s) => s.id === 'a').isOpen, true);
  assert.equal(closed.find((s) => s.id === 'a').isOpen, false);
  assert.equal(open.find((s) => s.id === 'a').inheritsWindow, true);
});

test('a scholarship can override the site period', () => {
  const store = makeStore(BASE);
  const closed = content.publicScholarships(store, CLOSED_DAY);
  assert.equal(closed.find((s) => s.id === 'c').isOpen, true);
  assert.equal(content.openScholarships(closed).length, 1);
});

test('hideWhenClosed removes a scholarship from the listing out of season', () => {
  const store = makeStore(BASE);
  assert.ok(content.publicScholarships(store, OPEN_DAY).some((s) => s.id === 'b'));
  assert.ok(!content.publicScholarships(store, CLOSED_DAY).some((s) => s.id === 'b'));
});

test('scholarships still listed while closed keep their status label', () => {
  const store = makeStore(BASE);
  const a = content.publicScholarships(store, CLOSED_DAY).find((s) => s.id === 'a');
  assert.match(a.statusLabel, /Reopens November 1, 2026/);
});

test('open scholarships sort ahead of closed ones', () => {
  const store = makeStore(BASE);
  assert.equal(content.publicScholarships(store, CLOSED_DAY)[0].id, 'c');
});

test('draft recipients stay off the public site but appear in preview', () => {
  const store = makeStore(BASE);
  const shown = content.publicRecipients(store, OPEN_DAY);
  assert.deepEqual(shown.map((r) => r.name), ['Ada', 'Grace']);
  assert.equal(content.publicRecipients(store, OPEN_DAY, { includeHidden: true }).length, 3);
});

test('recipients group newest year first, featured leading each year', () => {
  const store = makeStore(BASE);
  const groups = content.groupRecipientsByYear(content.publicRecipients(store, OPEN_DAY));
  assert.deepEqual(groups.map((g) => g.year), ['2025', '2024']);
  assert.equal(groups[0].items[0].name, 'Ada');
});

// The live site publishes no award year for anyone, so every recipient can
// arrive with year empty. That used to bucket them all under a literal "Other"
// heading and report "across 0 years".
test('recipients with no published year group without a year heading', () => {
  const store = makeStore({
    ...BASE,
    recipients: [{ id: 'y1', name: 'Sophia' }, { id: 'y2', name: 'Elijah' }],
  });
  const shown = content.publicRecipients(store, OPEN_DAY);
  const groups = content.groupRecipientsByYear(shown);
  assert.equal(groups.length, 1);
  assert.equal(groups[0].year, '');
  assert.equal(groups[0].items.length, 2);
  assert.equal(content.awardStats(shown).yearCount, 0);
});

test('award totals count only published recipients', () => {
  const store = makeStore(BASE);
  const stats = content.awardStats(content.publicRecipients(store, OPEN_DAY));
  assert.equal(stats.totalAwarded, 1500);
  assert.equal(stats.recipientCount, 2);
  assert.equal(stats.yearCount, 2);
});

test('announcements swap over with the enrollment period', () => {
  const store = makeStore(BASE);
  assert.deepEqual(
    content.activeAnnouncements(store, OPEN_DAY, 'open').map((n) => n.id),
    ['n1', 'n3']
  );
  assert.deepEqual(
    content.activeAnnouncements(store, CLOSED_DAY, 'upcoming').map((n) => n.id),
    ['n2', 'n3']
  );
});

test('money is formatted, and zero renders as nothing', () => {
  assert.equal(content.formatMoney(225000), '$225,000');
  assert.equal(content.formatMoney(0), '');
  assert.equal(content.formatMoney('x'), '');
});

test('a short story is left alone, a long one is cut at a sentence end', () => {
  const short = 'I am grateful for the scholarship. It changed things.';
  assert.deepEqual(content.excerpt(short), { text: short, full: short, truncated: false });

  const long = ('This is a sentence that runs on for a while. ').repeat(40);
  const cut = content.excerpt(long);
  assert.equal(cut.truncated, true);
  assert.ok(cut.text.length <= 521, `excerpt was ${cut.text.length}`);
  assert.equal(cut.full, long.replace(/\s+/g, ' ').trim());
  // cut at a sentence end, so it closes with the full stop and takes no ellipsis
  assert.match(cut.text, /\.$/);
  assert.doesNotMatch(cut.text, /…/);
});

test('a single long opening sentence still yields a usable excerpt', () => {
  // No sentence end before the halfway mark, so it falls back to a word break
  // rather than returning almost nothing.
  const cut = content.excerpt('word '.repeat(200) + '. Then more.');
  assert.equal(cut.truncated, true);
  assert.ok(cut.text.length > 400, `excerpt collapsed to ${cut.text.length}`);
  // mid-sentence, so this one does get an ellipsis, with no space before it
  assert.match(cut.text, /[^\s]…$/);
});

test('every seeded recipient story fits a card once excerpted', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  for (const r of seed.recipients.filter((x) => x.quote)) {
    const e = content.excerpt(r.quote);
    assert.ok(e.text.length <= 521, `${r.name}: ${e.text.length}`);
    if (e.truncated) assert.equal(e.full, r.quote.replace(/\s+/g, ' ').trim());
  }
});

// The recipient photos used to be hot-linked from the WordPress media library,
// which would have 404'd the moment the new site took over the domain. Every
// portrait must resolve inside this repo, in both builds.
test('every recipient photo is served from this repo and exists', () => {
  const root = path.join(__dirname, '..');
  const seed = JSON.parse(fs.readFileSync(path.join(root, 'data', 'content.json'), 'utf8'));
  const withPhotos = seed.recipients.filter((r) => r.photoUrl);
  assert.ok(withPhotos.length > 0, 'no recipient has a photo');

  for (const r of withPhotos) {
    assert.match(r.photoUrl, /^\/img\/recipients\//, `${r.name} is not served locally`);
    for (const base of ['public', path.join('php', 'public_html')]) {
      const file = path.join(root, base, r.photoUrl);
      assert.ok(fs.existsSync(file), `missing ${base}${r.photoUrl}`);
    }
  }
});

// The page title is the only h1 on the page, so a lone '#' in admin copy is
// clamped up to h2. Markdown::render() in the PHP build does the same.
test('a body heading never renders as a second h1', () => {
  const html = markdown.render('# Top level');
  assert.ok(!html.includes('<h1'), 'body copy produced an h1');
  assert.match(html, /<h2>Top level<\/h2>/);
});

test('renderSections anchors each heading and lists them in order', () => {
  const { html, headings } = markdown.renderSections('## How do I apply?\n\nBody.\n\n## What next?\n\nMore.');
  assert.deepEqual(headings.map((h) => h.id), ['how-do-i-apply', 'what-next']);
  assert.match(html, /<h2 id="how-do-i-apply">How do I apply\?<\/h2>/);
  assert.match(html, /<h2 id="what-next">What next\?<\/h2>/);
});

test('repeated headings get distinct ids', () => {
  const { headings } = markdown.renderSections('## Same\n\n## Same\n\n## Same');
  assert.deepEqual(headings.map((h) => h.id), ['same', 'same-2', 'same-3']);
});

// The FAQ was transcribed verbatim from the live WordPress page, which carries
// thirteen questions. The jump-to index in page.ejs/page.php is built from
// these, so a lost heading silently loses an entry from the index too.
test('the seeded FAQ carries every question, anchored', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const faq = seed.pages.find((p) => p.slug === 'faq');
  assert.ok(faq, 'no faq page in the seed');

  const { headings } = markdown.renderSections(faq.body);
  assert.equal(headings.length, 13);
  assert.equal(headings[0].id, 'how-do-i-know-if-i-am-eligible-to-apply');
  assert.equal(headings[12].id, 'how-is-my-scholarship-awarded');
  for (const h of headings) assert.notEqual(h.id, '', 'a question produced an empty anchor');
});

// The Node and PHP builds each carry their own copy of the seed content, and
// they have drifted before: a scholarship lost its summary and criteria in one
// copy only, so the card rendered blank on the deployed site and nowhere else.
test('both builds ship the same seed content', () => {
  const read = (file) => JSON.parse(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'));
  assert.deepEqual(read('data/content.json'), read('php/leo-app/data/content.json'));
});
