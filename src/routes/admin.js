'use strict';

const express = require('express');
const auth = require('../auth');
const content = require('../content');
const schedule = require('../schedule');
const { slugify } = require('../store');

const router = express.Router();

/**
 * Admin area.
 *
 * The four content types differ only in their fields, so they share one set of
 * routes and one form template driven by the RESOURCES table below. Adding a
 * field is a one-line change here, not a new route and a new view.
 */

const WINDOW_FIELD = {
  key: 'window',
  type: 'window',
  label: 'Application period',
  help: 'Inherit the site-wide enrollment period, or give this award its own dates.',
};

const PUBLISH_FIELDS = [
  {
    key: 'publish.showFrom',
    type: 'date',
    label: 'Show on the site from',
    help: 'Leave blank to show as soon as it is published.',
  },
  {
    key: 'publish.showUntil',
    type: 'date',
    label: 'Hide after',
    help: 'Leave blank to keep it up indefinitely.',
  },
  { key: 'draft', type: 'checkbox', label: 'Draft — hide from the public site' },
];

const RESOURCES = {
  scholarships: {
    label: 'Scholarship',
    plural: 'Scholarships',
    titleKey: 'name',
    fields: [
      { key: 'name', type: 'text', label: 'Name', required: true },
      { key: 'slug', type: 'text', label: 'URL slug', help: 'Leave blank to generate from the name.' },
      { key: 'amount', type: 'text', label: 'Award amount', placeholder: '$1,000 or "Varies with available funds"' },
      { key: 'summary', type: 'textarea', label: 'Summary', help: 'One or two sentences, shown on cards and search results.' },
      { key: 'criteria', type: 'markdown', label: 'Who can apply', help: 'Markdown. A bulleted list works best.' },
      { key: 'essayPrompts', type: 'lines', label: 'Essay prompts', help: 'One prompt per line.' },
      { key: 'details', type: 'markdown', label: 'Additional detail' },
      { key: 'applyUrl', type: 'text', label: 'Application link' },
      WINDOW_FIELD,
      { key: 'hideWhenClosed', type: 'checkbox', label: 'Hide from the site while applications are closed' },
      { key: 'featured', type: 'checkbox', label: 'Feature on the homepage' },
      ...PUBLISH_FIELDS,
    ],
  },
  recipients: {
    label: 'Recipient',
    plural: 'Recipients',
    titleKey: 'name',
    fields: [
      { key: 'name', type: 'text', label: 'Student name', required: true },
      { key: 'year', type: 'text', label: 'Award year', placeholder: '2025' },
      { key: 'scholarship', type: 'scholarship', label: 'Scholarship awarded' },
      { key: 'school', type: 'text', label: 'School' },
      { key: 'major', type: 'text', label: 'Major' },
      { key: 'amount', type: 'number', label: 'Amount awarded', help: 'Numbers only. Used for the published totals.' },
      { key: 'quote', type: 'textarea', label: 'Quote' },
      { key: 'photoUrl', type: 'text', label: 'Photo URL' },
      { key: 'featured', type: 'checkbox', label: 'Feature on the homepage' },
      ...PUBLISH_FIELDS,
    ],
  },
  pages: {
    label: 'Page',
    plural: 'Pages',
    titleKey: 'title',
    fields: [
      { key: 'title', type: 'text', label: 'Title', required: true },
      { key: 'slug', type: 'text', label: 'URL slug', help: 'The page lives at /slug.' },
      { key: 'navLabel', type: 'text', label: 'Navigation label', help: 'Short version for the header. Defaults to the title.' },
      { key: 'summary', type: 'textarea', label: 'Summary' },
      { key: 'body', type: 'markdown', label: 'Body', rows: 18 },
      { key: 'inNav', type: 'checkbox', label: 'Show in the main navigation' },
      {
        key: 'navParent',
        type: 'text',
        label: 'Nest under (URL slug)',
        help: 'Leave blank for a top-level item. Give another page’s slug to make this a dropdown item under it.',
      },
      ...PUBLISH_FIELDS,
    ],
  },
  announcements: {
    label: 'Announcement',
    plural: 'Announcements',
    titleKey: 'title',
    fields: [
      { key: 'title', type: 'text', label: 'Title', required: true },
      { key: 'body', type: 'textarea', label: 'Message' },
      { key: 'ctaLabel', type: 'text', label: 'Button label' },
      { key: 'ctaUrl', type: 'text', label: 'Button link' },
      {
        key: 'showWhen',
        type: 'select',
        label: 'Show this announcement',
        options: [
          { value: '', label: 'Always' },
          { value: 'open', label: 'Only while applications are open' },
          { value: 'closed', label: 'Only while applications are closed' },
        ],
        help: 'Tie a notice to the enrollment period and it swaps itself over every year.',
      },
      ...PUBLISH_FIELDS,
    ],
  },
};

/** Reject cross-site form posts. Cheap, and enough for a cookie-auth admin. */
function sameOrigin(req, res, next) {
  if (req.method !== 'POST') return next();
  const source = req.get('origin') || req.get('referer');
  if (!source) return next();
  try {
    if (new URL(source).host === req.get('host')) return next();
  } catch {
    /* fall through */
  }
  return res.status(403).send('Cross-site request blocked.');
}

function setPath(target, path, value) {
  const parts = path.split('.');
  let node = target;
  while (parts.length > 1) {
    const key = parts.shift();
    if (typeof node[key] !== 'object' || node[key] === null) node[key] = {};
    node = node[key];
  }
  node[parts[0]] = value;
}

function getPath(source, path) {
  return path.split('.').reduce((node, key) => (node == null ? undefined : node[key]), source);
}

/** Turn submitted form values into stored values, one field type at a time. */
function applyFields(record, body, fields) {
  for (const field of fields) {
    if (field.type === 'window') {
      const type = body['window.type'] || 'inherit';
      const window = { type };
      if (type === 'annual') {
        window.opensOn = body['window.annualOpensOn'] || '';
        window.closesOn = body['window.annualClosesOn'] || '';
      } else if (type === 'fixed') {
        window.opensOn = body['window.opensOn'] || '';
        window.closesOn = body['window.closesOn'] || '';
      }
      record.window = window;
      continue;
    }

    const raw = body[field.key];
    if (field.type === 'checkbox') {
      setPath(record, field.key, raw === 'on' || raw === 'true');
    } else if (field.type === 'number') {
      const value = Number(raw);
      setPath(record, field.key, Number.isFinite(value) && raw !== '' ? value : '');
    } else if (field.type === 'lines') {
      setPath(
        record,
        field.key,
        String(raw || '')
          .split('\n')
          .map((line) => line.trim())
          .filter(Boolean)
      );
    } else {
      setPath(record, field.key, typeof raw === 'string' ? raw.trim() : '');
    }
  }
  return record;
}

router.use(sameOrigin);

router.get('/login', (req, res) => {
  if (req.isAdmin) return res.redirect('/admin');
  return res.render('admin/login', {
    title: 'Staff sign in',
    error: req.query.error ? 'That password did not match. Try again.' : null,
    configured: Boolean(process.env.ADMIN_PASSWORD),
    next: req.query.next || '/admin',
  });
});

router.post('/login', (req, res) => {
  if (!auth.passwordMatches(req.body.password)) {
    return res.redirect(`/admin/login?error=1&next=${encodeURIComponent(req.body.next || '/admin')}`);
  }
  auth.login(res);
  const target = String(req.body.next || '/admin');
  return res.redirect(target.startsWith('/') ? target : '/admin');
});

router.post('/logout', (req, res) => {
  auth.logout(res);
  res.redirect('/');
});

router.use(auth.requireAdmin);

router.get('/', (req, res) => {
  const { store, today } = req;
  const scholarships = store
    .list('scholarships')
    .map((item) => content.resolveScholarship(item, store.enrollment, today));
  const recipients = store.list('recipients');

  res.render('admin/dashboard', {
    title: 'Dashboard',
    scholarships,
    openCount: scholarships.filter((item) => item.isOpen).length,
    recipients,
    publishedRecipients: recipients.filter((item) => schedule.isPublished(item, today)),
    stats: content.awardStats(recipients.filter((item) => schedule.isPublished(item, today))),
    pageCount: store.list('pages').length,
    announcementCount: store.list('announcements').length,
  });
});

router.get('/settings', (req, res) => {
  res.render('admin/settings', { title: 'Site settings', saved: req.query.saved === '1' });
});

router.post('/settings', (req, res) => {
  const { store, body } = req;
  store.updateSite({
    name: body.name?.trim() || store.site.name,
    legalName: body.legalName?.trim() || '',
    tagline: body.tagline?.trim() || '',
    mission: body.mission?.trim() || '',
    timezone: body.timezone?.trim() || 'America/Phoenix',
    email: body.email?.trim() || '',
    phone: body.phone?.trim() || '',
    location: body.location?.trim() || '',
    ein: body.ein?.trim() || '',
    donateUrl: body.donateUrl?.trim() || '',
    facebookUrl: body.facebookUrl?.trim() || '',
    impactTitle: body.impactTitle?.trim() || '',
    impact: [0, 1, 2]
      .map((index) => ({
        value: (body[`impact${index}value`] || '').trim(),
        label: (body[`impact${index}label`] || '').trim(),
        detail: (body[`impact${index}detail`] || '').trim(),
      }))
      .filter((item) => item.value || item.label),
  });
  store.updateEnrollment({
    type: body.enrollmentType === 'fixed' ? 'fixed' : 'annual',
    opensOn: body.enrollmentOpensOn?.trim() || '',
    closesOn: body.enrollmentClosesOn?.trim() || '',
    instructions: body.instructions?.trim() || '',
    awardedNote: body.awardedNote?.trim() || '',
  });
  res.redirect('/admin/settings?saved=1');
});

/** Shared list / edit / save / delete routes for every content type. */
for (const [name, config] of Object.entries(RESOURCES)) {
  router.get(`/${name}`, (req, res) => {
    const items = req.store.list(name).map((item) => ({
      ...item,
      published: schedule.isPublished(item, req.today),
      resolved: name === 'scholarships'
        ? content.resolveScholarship(item, req.store.enrollment, req.today)
        : null,
    }));
    res.render('admin/list', { title: config.plural, name, config, items });
  });

  router.get(`/${name}/new`, (req, res) => {
    res.render('admin/form', {
      title: `New ${config.label.toLowerCase()}`,
      name,
      config,
      record: { window: { type: 'inherit' }, publish: {} },
      isNew: true,
      saved: false,
      getPath,
    });
  });

  router.get(`/${name}/:id`, (req, res, next) => {
    const record = req.store.find(name, req.params.id);
    if (!record) return next();
    return res.render('admin/form', {
      title: record[config.titleKey] || config.label,
      name,
      config,
      record,
      isNew: false,
      saved: req.query.saved === '1',
      getPath,
    });
  });

  router.post(`/${name}/:id`, (req, res) => {
    const store = req.store;
    const existing = req.params.id === 'new' ? {} : store.find(name, req.params.id) || {};
    const record = applyFields({ ...existing }, req.body, config.fields);

    if (config.fields.some((field) => field.key === 'slug')) {
      record.slug = slugify(record.slug || record[config.titleKey]);
    }
    if (req.params.id !== 'new') record.id = req.params.id;

    const saved = store.upsert(name, record);
    res.redirect(`/admin/${name}/${saved.id}?saved=1`);
  });

  router.post(`/${name}/:id/delete`, (req, res) => {
    req.store.remove(name, req.params.id);
    res.redirect(`/admin/${name}`);
  });

  router.post(`/${name}/:id/move`, (req, res) => {
    req.store.reorder(name, req.params.id, req.body.direction === 'up' ? 'up' : 'down');
    res.redirect(`/admin/${name}`);
  });
}

module.exports = router;
