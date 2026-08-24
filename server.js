'use strict';

const { createApp } = require('./src/app');

const port = Number(process.env.PORT) || 3000;

createApp().listen(port, () => {
  console.log(`LEO Foundation site listening on http://localhost:${port}`);
  if (!process.env.ADMIN_PASSWORD) {
    console.log('ADMIN_PASSWORD is not set — the admin area at /admin is disabled.');
  }
});
