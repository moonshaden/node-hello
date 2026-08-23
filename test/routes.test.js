'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { Store } = require('../src/store');
const { createApp } = require('../src/app');
const schedule = require('../src/schedule');

process.env.ADMIN_PASSWORD = 'test-password';

/** Boot the real app on an ephemeral port against a throwaway content file. */
async function withServer(run) {
  const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'leo-routes-')), 'content.json');
  fs.copyFileSync(path.join(__dirname, '..', 'data', 'content.json'), file);

  const server = createApp({ store: new Store(file) }).listen(0);
  await new Promise((resolve) => server.once('listening', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;

  try {
    await run(base);
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
}

/** Sign in and return the session cookie. */
async function signIn(base) {
  const response = await fetch(`${base}/admin/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded', origin: base },
    body: 'password=test-password',
    redirect: 'manual',
  });
  return response.headers.getSetCookie().join('; ');
}

test('every public page renders', async () => {
  await withServer(async (base) => {
    for (const route of ['/', '/scholarships', '/recipients', '/about', '/faq', '/donate', '/contact']) {
      const response = await fetch(`${base}${route}`);
      assert.equal(response.status, 200, route);
      assert.match(await response.text(), /LEO Foundation/);
    }
  });
});

test('a scholarship detail page renders and unknown slugs 404', async () => {
  await withServer(async (base) => {
    const found = await fetch(`${base}/scholarships/leo-foundation-scholarship`);
    assert.equal(found.status, 200);
    assert.match(await found.text(), /LEO Foundation Scholarship/);

    assert.equal((await fetch(`${base}/scholarships/not-a-real-award`)).status, 404);
    assert.equal((await fetch(`${base}/nope`)).status, 404);
  });
});

test('draft recipients never reach an anonymous visitor', async () => {
  await withServer(async (base) => {
    const body = await (await fetch(`${base}/recipients`)).text();
    assert.doesNotMatch(body, /Placeholder record/);
    assert.match(body, /No recipients published yet/);
  });
});

test('the admin area is closed to anonymous visitors', async () => {
  await withServer(async (base) => {
    for (const route of ['/admin', '/admin/scholarships', '/admin/settings']) {
      const response = await fetch(`${base}${route}`, { redirect: 'manual' });
      assert.equal(response.status, 302, route);
      assert.match(response.headers.get('location'), /^\/admin\/login/);
    }
  });
});

test('a wrong password does not sign anyone in', async () => {
  await withServer(async (base) => {
    const response = await fetch(`${base}/admin/login`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', origin: base },
      body: 'password=wrong',
      redirect: 'manual',
    });
    assert.match(response.headers.get('location'), /error=1/);
    assert.equal(response.headers.getSetCookie().length, 0);
  });
});

test('signing in opens the admin area', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    const response = await fetch(`${base}/admin`, { headers: { cookie } });
    assert.equal(response.status, 200);
    assert.match(await response.text(), /Enrollment is/);
  });
});

test('editing a scholarship changes the public site immediately', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);

    // Give one award its own window that is open today, while the site-wide
    // enrollment period stays shut.
    //
    // "Today" has to be the site's today, not UTC's. Phoenix is UTC-7, so for
    // seven hours each evening the UTC date is already tomorrow — a window
    // opening on the UTC date reads as 'upcoming' to the app, and this test
    // failed only during those hours.
    const today = schedule.todayIn('America/Phoenix');
    await fetch(`${base}/admin/scholarships/sch-arvizu`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: base },
      body: new URLSearchParams({
        name: 'Arvizu Scholarship',
        slug: 'arvizu-scholarship',
        amount: '$2,500',
        'window.type': 'fixed',
        'window.opensOn': today,
        'window.closesOn': '2099-12-31',
      }).toString(),
      redirect: 'manual',
    });

    const body = await (await fetch(`${base}/scholarships/arvizu-scholarship`)).text();
    assert.match(body, /\$2,500/);
    assert.match(body, /Accepting applications/);
  });
});

test('cross-site posts are refused', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    const response = await fetch(`${base}/admin/scholarships/sch-arvizu`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: 'http://evil.example' },
      body: 'name=Hijacked',
    });
    assert.equal(response.status, 403);
  });
});

test('date preview is an admin-only capability', async () => {
  await withServer(async (base) => {
    const anonymous = await (await fetch(`${base}/?asOf=2026-01-15`)).text();
    assert.doesNotMatch(anonymous, /Exit preview/);

    const cookie = await signIn(base);
    const preview = await (await fetch(`${base}/?asOf=2026-01-15`, { headers: { cookie } })).text();
    assert.match(preview, /Exit preview/);
    assert.match(preview, /Applications open/);
  });
});
