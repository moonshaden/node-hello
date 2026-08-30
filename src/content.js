'use strict';

const schedule = require('./schedule');

/**
 * View-model layer: turns stored records into what the templates render.
 *
 * Everything time-sensitive is resolved here against a single `today` value so
 * a page can never show one section as open and another as closed, and so the
 * whole site can be previewed at any date (see ?asOf= in the admin preview).
 */

/** A scholarship's own window, or the site-wide enrollment period it inherits. */
function windowFor(scholarship, enrollment) {
  const own = scholarship.window;
  if (!own || own.type === 'inherit' || !own.type) return enrollment;
  return own;
}

function resolveScholarship(scholarship, enrollment, today) {
  const spec = windowFor(scholarship, enrollment);
  const window = schedule.resolveWindow(spec, today);
  const inherits = !scholarship.window || scholarship.window.type === 'inherit' || !scholarship.window.type;
  return {
    ...scholarship,
    window,
    inheritsWindow: inherits,
    isOpen: window.state === 'open',
    statusLabel: schedule.describeWindow(window),
    published: schedule.isPublished(scholarship, today),
  };
}

/**
 * Scholarships for the public site.
 *
 * A scholarship stays listed year-round by default -- students research these
 * months before they can apply -- and only disappears if an admin ticks
 * "hide while closed" on that record.
 */
function publicScholarships(store, today, { includeHidden = false } = {}) {
  return store
    .list('scholarships')
    .map((item) => resolveScholarship(item, store.enrollment, today))
    .filter((item) => includeHidden || (item.published && (!item.hideWhenClosed || item.isOpen)))
    .sort((a, b) => {
      if (a.isOpen !== b.isOpen) return a.isOpen ? -1 : 1;
      if (Boolean(a.featured) !== Boolean(b.featured)) return a.featured ? -1 : 1;
      return 0;
    });
}

function openScholarships(scholarships) {
  return scholarships.filter((item) => item.isOpen);
}

/** Awarded scholarships, newest class first -- the site's headline content. */
function publicRecipients(store, today, { includeHidden = false } = {}) {
  return store
    .list('recipients')
    .filter((item) => includeHidden || schedule.isPublished(item, today))
    .sort((a, b) => {
      const yearDiff = String(b.year || '').localeCompare(String(a.year || ''));
      if (yearDiff !== 0) return yearDiff;
      if (Boolean(a.featured) !== Boolean(b.featured)) return a.featured ? -1 : 1;
      return String(a.name || '').localeCompare(String(b.name || ''));
    });
}

function groupRecipientsByYear(recipients) {
  const groups = new Map();
  for (const recipient of recipients) {
    const year = recipient.year || '';
    if (!groups.has(year)) groups.set(year, []);
    groups.get(year).push(recipient);
  }
  return [...groups.entries()].map(([year, items]) => ({ year, items }));
}

function featuredRecipients(recipients, limit = 3) {
  const featured = recipients.filter((item) => item.featured);
  return (featured.length ? featured : recipients).slice(0, limit);
}

/**
 * Announcements visible right now.
 *
 * Besides the usual show-from/show-until dates, an announcement can be tied to
 * the enrollment period itself (`showWhen: 'open' | 'closed'`), so the "apply
 * now" banner and the "applications reopen in November" banner swap themselves
 * over every year with nothing for staff to remember.
 */
function activeAnnouncements(store, today, enrollmentState) {
  return store.list('announcements').filter((item) => {
    if (!schedule.isPublished(item, today)) return false;
    if (item.showWhen === 'open') return enrollmentState === 'open';
    if (item.showWhen === 'closed') return enrollmentState !== 'open';
    return true;
  });
}

function publicPages(store, today, { includeHidden = false } = {}) {
  return store
    .list('pages')
    .filter((item) => includeHidden || schedule.isPublished(item, today));
}

/**
 * Header navigation, as a one-level tree.
 *
 * A page names its parent by slug in `navParent`, which is what puts Community
 * Partnerships under About rather than adding an eighth top-level item. A child
 * whose parent is not itself in the nav would otherwise disappear, so it falls
 * back to sitting at the top level. Mirrored in Content::navPages().
 */
function navPages(pages) {
  const inNav = pages.filter((page) => page.inNav);
  const slugs = new Set(inNav.map((page) => page.slug));
  const children = new Map();
  const top = [];

  for (const page of inNav) {
    const parent = page.navParent;
    if (parent && parent !== page.slug && slugs.has(parent)) {
      if (!children.has(parent)) children.set(parent, []);
      children.get(parent).push(page);
    } else {
      top.push(page);
    }
  }

  return top.map((page) => ({ ...page, children: children.get(page.slug) || [] }));
}

/** The same nav flattened, parent then its children -- for the footer column. */
function navFlat(pages) {
  return navPages(pages).flatMap((page) => [page, ...page.children]);
}

/** Totals for the impact band. Computed, so they can never drift from the data. */
function awardStats(recipients) {
  const total = recipients.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
  const years = new Set(recipients.map((item) => item.year).filter(Boolean));
  return {
    recipientCount: recipients.length,
    totalAwarded: total,
    yearCount: years.size,
    latestYear: [...years].sort().pop() || null,
  };
}

/**
 * Trim a recipient story down to a card-sized excerpt.
 *
 * The published bios run from 334 to 1,271 characters, which left one card in a
 * row roughly four times the height of its neighbour. Cutting at a sentence end
 * keeps the excerpt readable; the full text is still in the store, and the card
 * offers it behind a disclosure so nothing published is lost.
 */
const EXCERPT_LIMIT = 520;

function excerpt(text, limit = EXCERPT_LIMIT) {
  const clean = String(text || '').replace(/\s+/g, ' ').trim();
  if (clean.length <= limit) return { text: clean, full: clean, truncated: false };

  const head = clean.slice(0, limit);
  const sentence = Math.max(head.lastIndexOf('. '), head.lastIndexOf('! '), head.lastIndexOf('? '));
  // Only respect a sentence end past the halfway mark, or a single long opening
  // sentence would cut the excerpt down to almost nothing.
  const atSentence = sentence > limit / 2;
  const cut = atSentence ? sentence + 1 : head.lastIndexOf(' ');
  const trimmed = clean.slice(0, cut > 0 ? cut : limit).replace(/[\s,;:]+$/, '');

  // A sentence end already closes the excerpt; an ellipsis after the full stop
  // just reads as ".…". Only a mid-sentence cut needs one.
  return { text: atSentence ? trimmed : `${trimmed}…`, full: clean, truncated: true };
}

function formatMoney(value) {
  const number = Number(value);
  if (!Number.isFinite(number) || number === 0) return '';
  return `$${number.toLocaleString('en-US')}`;
}

module.exports = {
  windowFor,
  resolveScholarship,
  publicScholarships,
  openScholarships,
  publicRecipients,
  groupRecipientsByYear,
  featuredRecipients,
  activeAnnouncements,
  publicPages,
  navPages,
  navFlat,
  awardStats,
  formatMoney,
  excerpt,
};
