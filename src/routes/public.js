'use strict';

const express = require('express');
const content = require('../content');

const router = express.Router();

router.get('/healthz', (req, res) => {
  res.json({ ok: true, today: req.today });
});

router.get('/', (req, res) => {
  const { store, today, preview } = req;
  const scholarships = content.publicScholarships(store, today, { includeHidden: preview });
  const recipients = content.publicRecipients(store, today, { includeHidden: preview });

  res.render('home', {
    title: store.site.name,
    slides: store.list('slides'),
    pillars: store.list('pillars'),
    scholarships,
    openScholarships: content.openScholarships(scholarships),
    featuredRecipients: content.featuredRecipients(recipients, 3),
    // The landing page leads on awarded students, so it gets all of them,
    // not a sample. publicRecipients() already orders featured first.
    awardees: recipients,
    heroStudent: content.heroStudent(store, recipients),
    stats: content.awardStats(recipients),
  });
});

router.get('/scholarships', (req, res) => {
  const { store, today, preview } = req;
  const scholarships = content.publicScholarships(store, today, { includeHidden: preview });

  res.render('scholarships', {
    title: 'Available scholarships',
    scholarships,
    openScholarships: content.openScholarships(scholarships),
  });
});

router.get('/scholarships/:slug', (req, res, next) => {
  const { store, today, preview } = req;
  const record = store.findBySlug('scholarships', req.params.slug);
  if (!record) return next();

  const scholarship = content.resolveScholarship(record, store.enrollment, today);
  if (!scholarship.published && !preview) return next();

  const recipients = content
    .publicRecipients(store, today, { includeHidden: preview })
    .filter((item) => item.scholarship === scholarship.name);

  return res.render('scholarship', {
    title: scholarship.name,
    scholarship,
    recipients,
  });
});

router.get('/recipients', (req, res) => {
  const { store, today, preview } = req;
  const all = content.publicRecipients(store, today, { includeHidden: preview });
  const years = [...new Set(all.map((item) => item.year).filter(Boolean))].sort().reverse();
  const year = years.includes(req.query.year) ? req.query.year : null;
  const recipients = year ? all.filter((item) => item.year === year) : all;

  res.render('recipients', {
    title: 'Scholarship recipients',
    groups: content.groupRecipientsByYear(recipients),
    years,
    selectedYear: year,
    stats: content.awardStats(all),
  });
});

// Editable pages live at the site root (/about, /faq, ...), so this must stay
// the last route registered -- anything above it wins.
router.get('/:slug', (req, res, next) => {
  const { store, today, preview } = req;
  const page = store.findBySlug('pages', req.params.slug);
  if (!page) return next();
  if (!preview && !require('../schedule').isPublished(page, today)) return next();

  return res.render('page', { title: page.title, page });
});

module.exports = router;
