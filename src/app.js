'use strict';

const path = require('node:path');
const express = require('express');

const { Store } = require('./store');
const schedule = require('./schedule');
const content = require('./content');
const markdown = require('./markdown');
const auth = require('./auth');
const publicRoutes = require('./routes/public');
const adminRoutes = require('./routes/admin');

function createApp({ store = new Store() } = {}) {
  const app = express();

  app.set('view engine', 'ejs');
  app.set('views', path.join(__dirname, '..', 'views'));
  app.locals.markdown = markdown;
  app.locals.schedule = schedule;
  app.locals.formatMoney = content.formatMoney;
  app.locals.excerpt = content.excerpt;

  app.use(express.static(path.join(__dirname, '..', 'public'), { maxAge: '1h' }));
  app.use(express.urlencoded({ extended: false, limit: '256kb' }));
  app.use(auth.session);

  app.use((req, res, next) => {
    req.store = store;

    // Admins can view the whole site as it will look on any date, and can see
    // drafts in place. Both are ignored for everyone else, so a shared link
    // can never expose unpublished content.
    const asOf = req.isAdmin && schedule.isDate(req.query.asOf) ? req.query.asOf : null;
    req.today = asOf || schedule.todayIn(store.site.timezone || 'America/Phoenix');
    req.preview = Boolean(req.isAdmin && (asOf || req.query.preview === '1'));

    const enrollment = schedule.resolveWindow(store.enrollment, req.today);

    res.locals.site = store.site;
    res.locals.today = req.today;
    res.locals.asOf = asOf;
    res.locals.preview = req.preview;
    res.locals.isAdmin = req.isAdmin;
    res.locals.enrollment = enrollment;
    res.locals.enrollmentLabel = schedule.describeWindow(enrollment);
    res.locals.enrollmentSettings = store.enrollment;
    res.locals.navPages = content.navPages(
      content.publicPages(store, req.today, { includeHidden: req.preview })
    );
    res.locals.announcements = content.activeAnnouncements(store, req.today, enrollment.state);
    res.locals.scholarshipNames = store.list('scholarships').map((item) => item.name);
    res.locals.currentPath = req.path;
    next();
  });

  app.use('/admin', adminRoutes);
  app.use('/', publicRoutes);

  app.use((req, res) => {
    res.status(404).render('404', { title: 'Page not found' });
  });

  // eslint-disable-next-line no-unused-vars -- Express identifies error handlers by arity.
  app.use((err, req, res, next) => {
    console.error(err);
    res.status(500).render('error', { title: 'Something went wrong', message: err.message });
  });

  return app;
}

module.exports = { createApp };
