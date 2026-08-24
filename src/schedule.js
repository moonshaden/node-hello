'use strict';

/**
 * Date + time-window logic for the site.
 *
 * Every date in the content store is a plain calendar date ('YYYY-MM-DD')
 * interpreted in the site's timezone (America/Phoenix by default). Working in
 * calendar dates rather than timestamps keeps the admin UI honest -- an
 * application window that "closes March 31" closes at the end of March 31
 * locally, no matter where the server runs or whether DST is in effect.
 */

const MS_PER_DAY = 86400000;
const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const MONTH_DAY_RE = /^\d{2}-\d{2}$/;

/** Today's calendar date in `timeZone`, as 'YYYY-MM-DD'. */
function todayIn(timeZone, now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(now);
  return parts; // en-CA formats as YYYY-MM-DD
}

function isDate(value) {
  return typeof value === 'string' && DATE_RE.test(value);
}

function isMonthDay(value) {
  return typeof value === 'string' && MONTH_DAY_RE.test(value);
}

/** Days from `from` to `to`; negative when `to` is in the past. */
function daysBetween(from, to) {
  if (!isDate(from) || !isDate(to)) return null;
  return Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / MS_PER_DAY);
}

/** 'November 1, 2026'. Returns '' for anything unparseable. */
function formatDate(date, { short = false } = {}) {
  if (!isDate(date)) return '';
  const [year, month, day] = date.split('-').map(Number);
  const name = MONTHS[month - 1];
  if (!name) return '';
  return `${short ? name.slice(0, 3) : name} ${day}, ${year}`;
}

/** 'November 1' -- for describing a recurring window with no year attached. */
function formatMonthDay(monthDay) {
  if (!isMonthDay(monthDay)) return '';
  const [month, day] = monthDay.split('-').map(Number);
  const name = MONTHS[month - 1];
  return name ? `${name} ${day}` : '';
}

/**
 * Resolve an annual (recurring) window against `today`.
 *
 * Windows may wrap the year boundary -- the foundation's enrollment period runs
 * Nov 1 through Mar 31, so a single cycle spans two calendar years. We build
 * candidate cycles anchored on the years around today and pick the one that
 * contains today, falling back to the next one that starts.
 */
function resolveAnnual(opensOn, closesOn, today) {
  const year = Number(today.slice(0, 4));
  const wraps = closesOn < opensOn;
  const cycles = [];
  for (const anchor of [year - 1, year, year + 1]) {
    cycles.push({
      opensOn: `${anchor}-${opensOn}`,
      closesOn: `${wraps ? anchor + 1 : anchor}-${closesOn}`,
    });
  }
  cycles.sort((a, b) => (a.opensOn < b.opensOn ? -1 : 1));

  const current = cycles.find((c) => c.opensOn <= today && today <= c.closesOn);
  if (current) {
    return {
      state: 'open',
      opensOn: current.opensOn,
      closesOn: current.closesOn,
      daysUntilOpen: 0,
      daysUntilClose: daysBetween(today, current.closesOn),
      recurring: true,
    };
  }

  const next = cycles.find((c) => c.opensOn > today);
  const previous = [...cycles].reverse().find((c) => c.closesOn < today);
  return {
    state: 'upcoming',
    opensOn: next ? next.opensOn : null,
    closesOn: next ? next.closesOn : null,
    daysUntilOpen: next ? daysBetween(today, next.opensOn) : null,
    daysUntilClose: null,
    previousClosedOn: previous ? previous.closesOn : null,
    recurring: true,
  };
}

/**
 * Resolve any window shape into a state the templates can render directly.
 *
 * Shapes:
 *   { type: 'always' }
 *   { type: 'fixed',  opensOn: 'YYYY-MM-DD', closesOn: 'YYYY-MM-DD' }  (both optional)
 *   { type: 'annual', opensOn: 'MM-DD',      closesOn: 'MM-DD' }        (may wrap the year)
 *
 * Returns { state: 'open' | 'upcoming' | 'closed', opensOn, closesOn, ... }.
 */
function resolveWindow(window, today) {
  const spec = window || { type: 'always' };

  if (spec.type === 'annual' && isMonthDay(spec.opensOn) && isMonthDay(spec.closesOn)) {
    return resolveAnnual(spec.opensOn, spec.closesOn, today);
  }

  if (spec.type === 'fixed') {
    const opensOn = isDate(spec.opensOn) ? spec.opensOn : null;
    const closesOn = isDate(spec.closesOn) ? spec.closesOn : null;
    if (opensOn && today < opensOn) {
      return {
        state: 'upcoming',
        opensOn,
        closesOn,
        daysUntilOpen: daysBetween(today, opensOn),
        daysUntilClose: null,
        recurring: false,
      };
    }
    if (closesOn && today > closesOn) {
      return {
        state: 'closed',
        opensOn,
        closesOn,
        daysUntilOpen: null,
        daysUntilClose: null,
        recurring: false,
      };
    }
    return {
      state: 'open',
      opensOn,
      closesOn,
      daysUntilOpen: 0,
      daysUntilClose: closesOn ? daysBetween(today, closesOn) : null,
      recurring: false,
    };
  }

  return {
    state: 'open',
    opensOn: null,
    closesOn: null,
    daysUntilOpen: 0,
    daysUntilClose: null,
    recurring: false,
    always: true,
  };
}

/**
 * Is an item allowed on the public site today?
 *
 * `publish.showFrom` / `publish.showUntil` are a hard gate used for pages that
 * should only exist for part of the year (an awards-night page, a matching-gift
 * campaign). They are independent of the application window: a scholarship can
 * stay listed year-round while only accepting applications for five months.
 */
function isPublished(item, today) {
  if (!item || item.archived) return false;
  if (item.draft) return false;
  const publish = item.publish || {};
  if (isDate(publish.showFrom) && today < publish.showFrom) return false;
  if (isDate(publish.showUntil) && today > publish.showUntil) return false;
  return true;
}

/** Human-readable summary of a resolved window, e.g. for a status pill. */
function describeWindow(resolved) {
  if (!resolved) return '';
  if (resolved.always) return 'Open year-round';
  if (resolved.state === 'open') {
    return resolved.closesOn
      ? `Accepting applications through ${formatDate(resolved.closesOn)}`
      : 'Accepting applications';
  }
  if (resolved.state === 'upcoming') {
    return resolved.opensOn
      ? `${resolved.recurring ? 'Reopens' : 'Opens'} ${formatDate(resolved.opensOn)}`
      : 'Opening soon';
  }
  return resolved.closesOn
    ? `Closed ${formatDate(resolved.closesOn)}`
    : 'Closed';
}

module.exports = {
  todayIn,
  isDate,
  isMonthDay,
  daysBetween,
  formatDate,
  formatMonthDay,
  resolveWindow,
  isPublished,
  describeWindow,
};
