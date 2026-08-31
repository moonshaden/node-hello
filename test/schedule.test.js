'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const schedule = require('../src/schedule');

const ENROLLMENT = { type: 'annual', opensOn: '11-01', closesOn: '03-31' };

test('todayIn respects the site timezone', () => {
  // 06:00 UTC on Jan 2 is still Jan 1 in Phoenix (UTC-7, no DST).
  const instant = new Date('2026-01-02T06:00:00Z');
  assert.equal(schedule.todayIn('America/Phoenix', instant), '2026-01-01');
  assert.equal(schedule.todayIn('UTC', instant), '2026-01-02');
});

test('an annual window that wraps the year is open on both sides of New Year', () => {
  for (const today of ['2025-11-01', '2025-12-25', '2026-01-15', '2026-03-31']) {
    assert.equal(schedule.resolveWindow(ENROLLMENT, today).state, 'open', today);
  }
});

test('an annual window is closed between cycles and points at the next one', () => {
  const resolved = schedule.resolveWindow(ENROLLMENT, '2026-08-21');
  assert.equal(resolved.state, 'upcoming');
  assert.equal(resolved.opensOn, '2026-11-01');
  assert.equal(resolved.closesOn, '2027-03-31');
  assert.equal(resolved.previousClosedOn, '2026-03-31');
  assert.equal(resolved.daysUntilOpen, 72);
});

test('an annual window rolls into the next cycle without an edit', () => {
  // Same stored record, three years apart: still resolves to that year's cycle.
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2028-12-01').closesOn, '2029-03-31');
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2031-02-01').opensOn, '2030-11-01');
});

test('boundary days are inclusive at both ends', () => {
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2025-10-31').state, 'upcoming');
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2025-11-01').state, 'open');
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2026-03-31').state, 'open');
  assert.equal(schedule.resolveWindow(ENROLLMENT, '2026-04-01').state, 'upcoming');
});

test('a non-wrapping annual window works too', () => {
  const summer = { type: 'annual', opensOn: '06-01', closesOn: '08-31' };
  assert.equal(schedule.resolveWindow(summer, '2026-07-04').state, 'open');
  assert.equal(schedule.resolveWindow(summer, '2026-09-01').state, 'upcoming');
  assert.equal(schedule.resolveWindow(summer, '2026-09-01').opensOn, '2027-06-01');
});

test('fixed windows move through upcoming, open, then closed for good', () => {
  const fixed = { type: 'fixed', opensOn: '2026-09-01', closesOn: '2026-09-30' };
  assert.equal(schedule.resolveWindow(fixed, '2026-08-31').state, 'upcoming');
  assert.equal(schedule.resolveWindow(fixed, '2026-09-15').state, 'open');
  assert.equal(schedule.resolveWindow(fixed, '2026-10-01').state, 'closed');
});

test('a window with no dates is always open', () => {
  assert.equal(schedule.resolveWindow({ type: 'always' }, '2026-08-21').state, 'open');
  assert.equal(schedule.resolveWindow(undefined, '2026-08-21').state, 'open');
});

test('malformed dates fall back to always-open rather than throwing', () => {
  assert.equal(schedule.resolveWindow({ type: 'annual', opensOn: 'nov', closesOn: '' }, '2026-08-21').state, 'open');
  assert.equal(schedule.resolveWindow({ type: 'fixed', opensOn: 'soon' }, '2026-08-21').state, 'open');
});

test('publish windows gate visibility independently of applications', () => {
  const item = { publish: { showFrom: '2026-04-01', showUntil: '2026-06-30' } };
  assert.equal(schedule.isPublished(item, '2026-03-31'), false);
  assert.equal(schedule.isPublished(item, '2026-05-01'), true);
  assert.equal(schedule.isPublished(item, '2026-07-01'), false);
  assert.equal(schedule.isPublished({ draft: true }, '2026-05-01'), false);
  assert.equal(schedule.isPublished({ archived: true }, '2026-05-01'), false);
  assert.equal(schedule.isPublished({}, '2026-05-01'), true);
});

test('dates are formatted for humans', () => {
  assert.equal(schedule.formatDate('2026-11-01'), 'November 1, 2026');
  assert.equal(schedule.formatDate('2026-11-01', { short: true }), 'Nov 1, 2026');
  assert.equal(schedule.formatDate('garbage'), '');
  assert.equal(schedule.formatMonthDay('03-31'), 'March 31');
});

test('window descriptions read correctly in each state', () => {
  assert.match(schedule.describeWindow(schedule.resolveWindow(ENROLLMENT, '2026-01-15')), /through March 31, 2026/);
  assert.match(schedule.describeWindow(schedule.resolveWindow(ENROLLMENT, '2026-08-21')), /Reopens November 1, 2026/);
  assert.equal(schedule.describeWindow(schedule.resolveWindow({ type: 'always' }, '2026-08-21')), 'Open year-round');
});
