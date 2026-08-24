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
    const year = recipient.year || 'Other';
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

function navPages(pages) {
  return pages.filter((page) => page.inNav);
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
  awardStats,
  formatMoney,
};
