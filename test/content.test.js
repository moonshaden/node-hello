'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { Store } = require('../src/store');
const content = require('../src/content');

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

// The Node and PHP builds each carry their own copy of the seed content, and
// they have drifted before: a scholarship lost its summary and criteria in one
// copy only, so the card rendered blank on the deployed site and nowhere else.
test('both builds ship the same seed content', () => {
  const read = (file) => JSON.parse(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'));
  assert.deepEqual(read('data/content.json'), read('php/leo-app/data/content.json'));
});
