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

// Community Partnerships sits under About as a dropdown rather than as an
// eighth top-level item. The nesting is driven by navParent on the record.
test('navPages nests a child under its parent and leaves the rest flat', () => {
  const pages = [
    { slug: 'about', title: 'About', inNav: true },
    { slug: 'community', title: 'Community', inNav: true, navParent: 'about' },
    { slug: 'faq', title: 'FAQs', inNav: true },
    { slug: 'hidden', title: 'Hidden', inNav: false },
  ];
  const nav = content.navPages(pages);
  assert.deepEqual(nav.map((p) => p.slug), ['about', 'faq']);
  assert.deepEqual(nav[0].children.map((c) => c.slug), ['community']);
  assert.deepEqual(nav[1].children, []);
  // The footer needs every page, parent then child.
  assert.deepEqual(content.navFlat(pages).map((p) => p.slug), ['about', 'community', 'faq']);
});

// A child pointing at a parent that is not in the nav would otherwise vanish
// from the header entirely, which is worse than showing it at the top level.
test('a child with no visible parent falls back to the top level', () => {
  const orphan = content.navPages([
    { slug: 'community', title: 'Community', inNav: true, navParent: 'about' },
  ]);
  assert.deepEqual(orphan.map((p) => p.slug), ['community']);

  // ...and a page naming itself as its parent must not disappear either.
  const self = content.navPages([
    { slug: 'about', title: 'About', inNav: true, navParent: 'about' },
  ]);
  assert.deepEqual(self.map((p) => p.slug), ['about']);
});

test('the seeded nav puts community partnerships under about', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const nav = content.navPages(seed.pages);
  const about = nav.find((p) => p.slug === 'about');
  assert.ok(about, 'about is not in the nav');
  assert.deepEqual(about.children.map((c) => c.slug), ['community']);
  assert.ok(!nav.some((p) => p.slug === 'community'), 'community is still a top-level item');
});

// The Community Partnerships page is transcribed from /community-partnerships/
// on the live site: one partner, its copy word for word, and the event gallery.
test('the community page publishes the partner and its gallery', () => {
  const root = path.join(__dirname, '..');
  const seed = JSON.parse(fs.readFileSync(path.join(root, 'data', 'content.json'), 'utf8'));
  const page = seed.pages.find((p) => p.slug === 'community');
  assert.ok(page, 'no community page in the seed');
  assert.deepEqual(page.partners.map((p) => p.name), ['Alice Cooper’s Solid Rock Teen Center']);
  assert.equal(page.gallery.length, 4, 'the gallery changed');

  const images = [...page.partners.map((p) => p.photoUrl), ...page.gallery.map((g) => g.src)];
  for (const src of images) {
    assert.match(src, /^\/img\/partners\//, `${src} is not served locally`);
    for (const base of ['public', path.join('php', 'public_html')]) {
      assert.ok(fs.existsSync(path.join(root, base, src)), `missing ${base}${src}`);
    }
  }
  for (const g of page.gallery) assert.ok((g.alt || '').trim(), `${g.src} has no alt text`);
});

// The community page is off the main nav by design, so the programs page is the
// way in. A reworded programs body must not quietly strip the link.
test('the programs page links to the community page', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const programs = seed.pages.find((p) => p.slug === 'programs');
  assert.match(programs.body, /\]\(\/community\)/, 'the programs page no longer links to /community');
});

// The Programs & Partnerships page is transcribed from /programs-partnerships/
// on the live site: three programs, in the live order, copy word for word.
const PROGRAMS = ['Foster Youth Programs', 'Impact Leadership Program', 'Youth Development Academy'];

function seedPage(slug) {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  return seed.pages.find((p) => p.slug === slug);
}

test('the programs page publishes all three programs in the live order', () => {
  const page = seedPage('programs');
  assert.ok(page, 'no programs page in the seed');
  assert.deepEqual(page.programs.map((p) => p.name), PROGRAMS);
});

// Same failure mode as the recipient and board portraits: a wp-content URL
// would 404 the moment the new site took over the domain.
test('every program photo is served from this repo and exists', () => {
  const root = path.join(__dirname, '..');
  const programs = seedPage('programs').programs;
  for (const p of programs) {
    assert.match(p.photoUrl, /^\/img\/programs\//, `${p.name} is not served locally`);
    assert.ok((p.alt || '').trim(), `${p.name} has no alt text`);
    for (const base of ['public', path.join('php', 'public_html')]) {
      assert.ok(fs.existsSync(path.join(root, base, p.photoUrl)), `missing ${base}${p.photoUrl}`);
    }
  }
});

// The hero slide for this destination pointed nowhere until the page existed.
// A slide must never point at a path this site does not serve.
test('the programs hero slide points at the page that now exists', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const slide = seed.slides.find((s) => /programs/i.test(s.heading || ''));
  assert.ok(slide, 'no programs slide');
  assert.equal(slide.ctaUrl, '/programs', 'the slide still points nowhere');
});

// The board is real named people transcribed from /leadership-2/ on the live
// site. A wrong name, a wrong office, or a reordered roster is worse than no
// page at all, so the seed is pinned here rather than left to drift.
const BOARD = [
  ['Madeline LoConti Winney', 'Chief Executive Officer'],
  ['Michele Simphoukham', 'Chief Financial Officer'],
  ['Greg Sharp', 'Board Member'],
  ['Robb Kottman', 'Board Member and Investment Advisor'],
  ['Dr. Jennifer Billingsley', 'Board Member'],
  ['Darrin Anderson', 'Board Member'],
];

function seedBoard() {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  return seed.pages.find((p) => p.slug === 'board');
}

test('the board page publishes every member in the live order', () => {
  const page = seedBoard();
  assert.ok(page, 'no board page in the seed');
  // The client asked for it reachable from Contact, not added to the header.
  assert.equal(page.inNav, false, 'the board page is in the main navigation');
  assert.deepEqual(page.members.map((m) => [m.name, m.role]), BOARD);
});

// Same failure mode as the recipient portraits: a wp-content URL would 404 the
// moment the new site took over the domain.
test('every board photo is served from this repo and exists', () => {
  const root = path.join(__dirname, '..');
  const members = seedBoard().members.filter((m) => m.photoUrl);
  assert.equal(members.length, BOARD.length, 'a board member lost their photo');

  for (const m of members) {
    assert.match(m.photoUrl, /^\/img\/board\//, `${m.name} is not served locally`);
    for (const base of ['public', path.join('php', 'public_html')]) {
      assert.ok(fs.existsSync(path.join(root, base, m.photoUrl)), `missing ${base}${m.photoUrl}`);
    }
  }
});

test('every seeded board bio fits a card once excerpted', () => {
  for (const m of seedBoard().members) {
    const e = content.excerpt(m.bio);
    assert.ok(e.text.length <= 521, `${m.name}: ${e.text.length}`);
    if (e.truncated) assert.equal(e.full, m.bio.replace(/\s+/g, ' ').trim());
  }
});

// The homepage hero is published twice on the live site: three full-bleed
// panels for desktop and a mobile-only LayerSlider. Both carry the same three
// destinations, so the seed is the union — the slider's fuller headings over
// the panels' larger photographs. Order is the live order.
const SLIDES = ['PROGRAMS & PARTNERSHIPS', "SCHOLARSHIP FAQ's", 'LEO FOUNDATION NEWS'];

function seedSlides() {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  return seed.slides;
}

test('the homepage slider carries every live slide, in the live order', () => {
  const slides = seedSlides();
  assert.deepEqual(slides.map((s) => s.heading), SLIDES);
  assert.deepEqual(slides.map((s) => s.order), [1, 2, 3]);
});

// A slider component reads the same keys off every slide. A slide that drops
// one because the live site published nothing there would read as undefined
// rather than empty, so the shape is uniform and the keys are always strings.
test('every slide carries the same keys', () => {
  const keys = ['id', 'image', 'alt', 'heading', 'subheading', 'body', 'ctaLabel', 'ctaUrl', 'order'];
  for (const s of seedSlides()) {
    assert.deepEqual(Object.keys(s), keys, s.id);
    for (const k of keys.filter((x) => x !== 'order')) {
      assert.equal(typeof s[k], 'string', `${s.id}.${k}`);
    }
    assert.ok(s.alt.length > 20, `${s.id} has no real alt text`);
  }
});

// Same failure mode as the recipient and board photos: a wp-content URL would
// 404 the moment the new site took over the domain.
test('every slide image is served from this repo and exists', () => {
  const root = path.join(__dirname, '..');
  const slides = seedSlides();
  assert.equal(slides.length, SLIDES.length, 'a slide lost its image');

  for (const s of slides) {
    assert.match(s.image, /^\/img\/slides\//, `${s.id} is not served locally`);
    for (const base of ['public', path.join('php', 'public_html')]) {
      assert.ok(fs.existsSync(path.join(root, base, s.image)), `missing ${base}${s.image}`);
    }
  }
});

// A slide may link nowhere — two of the three live destinations have no page on
// this site yet — but it must never link somewhere that 404s.
test('no slide links to a page this site does not serve', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const served = new Set(['/', '/scholarships', '/recipients', ...seed.pages.map((p) => `/${p.slug}`)]);
  for (const s of seed.slides) {
    if (!s.ctaUrl) continue;
    assert.ok(served.has(s.ctaUrl), `${s.id} links to ${s.ctaUrl}, which nothing serves`);
  }
});

// LEO is an acronym and the live homepage publishes a write-up for each word.
// The words carry the brand, so a reordered or reworded set is a real change.
test('the LEO pillars spell out Leadership, Education, Opportunity', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  assert.deepEqual(seed.pillars.map((p) => p.word), ['Leadership', 'Education', 'Opportunity']);
  assert.deepEqual(seed.pillars.map((p) => p.order), [1, 2, 3]);
  assert.equal(seed.pillars.map((p) => p.word[0]).join(''), 'LEO');
  for (const p of seed.pillars) {
    assert.deepEqual(Object.keys(p), ['id', 'word', 'tagline', 'body', 'order'], p.id);
    // The live site publishes the word and one paragraph, no tagline.
    assert.ok(p.body.length > 300, `${p.word} lost its write-up`);
  }
});

// The board page is off the main nav by design, so the contact page is the only
// way in. A reworded contact body must not quietly strip the link.
test('the contact page links to the board page', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const contact = seed.pages.find((p) => p.slug === 'contact');
  assert.match(contact.body, /\]\(\/board\)/, 'the contact page no longer links to /board');
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

// The live FAQ is explicit: "Students are limited to one scholarship
// application submission." Reconstructed copy told applicants the opposite in
// ten places across both builds, which would have cost real applicants a real
// award. Nothing published may say it again.
test('no published copy tells applicants to apply for several awards', () => {
  const root = path.join(__dirname, '..');
  const files = [
    'data/content.json', 'php/leo-app/data/content.json',
    'views/home.ejs', 'views/scholarships.ejs', 'views/partials/page-cta.ejs',
    'php/leo-app/views/home.php', 'php/leo-app/views/scholarships.php',
    'php/leo-app/views/partials/page-cta.php',
  ];
  for (const file of files) {
    const text = fs.readFileSync(path.join(root, file), 'utf8');
    assert.doesNotMatch(text, /apply for every|every award you qualify/i, file);
  }
});

// The slider is the one part of the site that ships JavaScript to the public,
// and a slide whose link goes nowhere is worse than a slide with no link, so
// both halves of that contract are pinned.
test('every slide has an image and a heading, and links only where a page exists', () => {
  const store = new Store(path.join(__dirname, '..', 'data', 'content.json'));
  const slides = store.list('slides');
  assert.ok(slides.length > 0, 'slides are seeded');

  const routes = new Set(['/faq', '/about', '/donate', '/contact', '/board',
                          '/programs', '/scholarships', '/recipients']);
  for (const slide of slides) {
    assert.match(slide.image, /^\/img\/slides\/.+\.jpg$/, 'app-absolute image');
    assert.ok(slide.heading.trim(), 'heading');
    assert.ok(slide.alt.trim(), 'alt text');
    for (const key of ['subheading', 'body', 'ctaLabel', 'ctaUrl']) {
      assert.equal(typeof slide[key], 'string', `${key} is always a string`);
    }
    if (slide.ctaUrl) {
      assert.ok(routes.has(slide.ctaUrl), `${slide.ctaUrl} is a page this site serves`);
    }
  }
});

test('the LEO pillars are the three words of the name', () => {
  const store = new Store(path.join(__dirname, '..', 'data', 'content.json'));
  const words = store.list('pillars').map((p) => p.word);
  assert.deepEqual(words, ['Leadership', 'Education', 'Opportunity']);
  for (const pillar of store.list('pillars')) {
    assert.ok(pillar.body.trim().length > 80, `${pillar.word} has its write-up`);
  }
});

// A hero quote is an excerpt, never a paraphrase. Names, figures and body copy
// on this site are transcribed from what the foundation publishes or they do
// not ship, and a short line pulled out for the hero is no exception -- so it
// has to be a literal run of characters out of the bio the student published.
test('every hero quote is verbatim from the bio it was lifted from', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const withQuote = seed.recipients.filter((person) => person.heroQuote);
  assert.ok(withQuote.length >= 1, 'at least one student fronts the hero');

  for (const person of withQuote) {
    assert.ok(
      String(person.quote).includes(person.heroQuote),
      `${person.name}'s hero quote is not a literal substring of their bio`,
    );
  }
});

// The hero stands the figure free of its background, which only works if the
// cutout is a real transparent image next to the ordinary portrait.
test('the hero student has a cutout in both builds', () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const id = seed.site.heroStudentId;
  assert.ok(id, 'a hero student is configured');

  const person = seed.recipients.find((item) => item.id === id);
  assert.ok(person, `site.heroStudentId points at ${id}, which is not a recipient`);
  assert.ok(person.cutoutUrl, 'the hero student carries a cutout');
  assert.notEqual(person.cutoutUrl, person.photoUrl, 'the cutout is not the uncut photograph');
  assert.ok(!person.draft, 'the hero student is published');

  for (const dir of ['public', 'php/public_html']) {
    const file = path.join(__dirname, '..', dir, person.cutoutUrl.replace(/^\//, ''));
    assert.ok(fs.existsSync(file), `${file} is missing`);
  }
});

// The two builds ship their own copy of the client script, the same way they
// ship their own copy of the seed. Nothing pinned them together, so a fix
// applied to one and forgotten in the other would run on the dev twin and not
// on the deployed site -- with both suites green, because neither reads it.
// Same failure mode as the seed drift, and the same remedy.
test('both builds ship the same client script and stylesheet', () => {
  const root = path.join(__dirname, '..');
  for (const [a, b] of [
    ['public/js/site.js', 'php/public_html/js/site.js'],
    ['public/css/site.css', 'php/public_html/css/site.css'],
  ]) {
    assert.equal(
      fs.readFileSync(path.join(root, a), 'utf8'),
      fs.readFileSync(path.join(root, b), 'utf8'),
      `${a} and ${b} have drifted`,
    );
  }
});
