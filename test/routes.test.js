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

/**
 * Boot the real app on an ephemeral port against a throwaway content file.
 *
 * `mutate` gets the parsed seed before the server starts, so a test can add the
 * record it needs (a draft, an expired window) instead of depending on the seed
 * happening to contain one.
 */
async function withServer(run, mutate) {
  const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'leo-routes-')), 'content.json');
  fs.copyFileSync(path.join(__dirname, '..', 'data', 'content.json'), file);

  if (mutate) {
    const content = JSON.parse(fs.readFileSync(file, 'utf8'));
    mutate(content);
    fs.writeFileSync(file, JSON.stringify(content, null, 2));
  }

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

// Thirteen questions in one prose column read as a wall, so the FAQ opens on a
// jump-to index. The index is only worth its space on a long page, so a short
// one — About has two headings — must not sprout one.
test('the FAQ carries a jump-to index and an anchor per question', async () => {
  await withServer(async (base) => {
    const faq = await (await fetch(`${base}/faq`)).text();
    assert.match(faq, /class="page-index"/);

    const ids = [...faq.matchAll(/<h2 id="([^"]+)">/g)].map((m) => m[1]);
    assert.equal(ids.length, 13);
    for (const id of ids) {
      assert.ok(faq.includes(`href="#${id}"`), `no index entry links to #${id}`);
    }

    const about = await (await fetch(`${base}/about`)).text();
    assert.ok(!about.includes('class="page-index"'), 'a short page grew an index');
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
  await withServer(
    async (base) => {
      const body = await (await fetch(`${base}/recipients`)).text();
      assert.match(body, /Sophia Hamre/);
      assert.doesNotMatch(body, /Hidden Draft Student/);
    },
    (content) => {
      content.recipients.push({
        id: 'rec-draft-test',
        name: 'Hidden Draft Student',
        scholarship: 'LEO Foundation Scholarship',
        draft: true,
      });
    }
  );
});

test('a withdrawn scholarship is not offered to the public', async () => {
  await withServer(async (base) => {
    const list = await (await fetch(`${base}/scholarships`)).text();
    assert.doesNotMatch(list, /Arvizu/);
    assert.doesNotMatch(list, /Tim Browning/);
    assert.equal((await fetch(`${base}/scholarships/arvizu-scholarship`)).status, 404);
    assert.equal((await fetch(`${base}/scholarships/tim-browning-memorial-scholarship`)).status, 404);
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
    await fetch(`${base}/admin/scholarships/sch-theatre`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: base },
      body: new URLSearchParams({
        name: 'Foundation Theatre Scholarship',
        slug: 'foundation-theatre-scholarship',
        amount: '$2,500',
        'window.type': 'fixed',
        'window.opensOn': today,
        'window.closesOn': '2099-12-31',
      }).toString(),
      redirect: 'manual',
    });

    const body = await (await fetch(`${base}/scholarships/foundation-theatre-scholarship`)).text();
    assert.match(body, /\$2,500/);
    assert.match(body, /Accepting applications/);
  });
});

// The board roster rides on the page record but has no field in the page form,
// so a save rebuilds the record from the posted fields alone. applyFields()
// spreads the existing record first, which is the only thing keeping an admin
// edit to the copy from silently deleting six people.
test('editing the board page in admin keeps the roster', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    await fetch(`${base}/admin/pages/page-board`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: base },
      body: new URLSearchParams({
        title: 'Board of Directors',
        slug: 'board',
        summary: 'Rewritten in the admin area.',
        body: '## LEO Leadership\n\nRewritten too.',
      }).toString(),
      redirect: 'manual',
    });

    const body = await (await fetch(`${base}/board`)).text();
    assert.match(body, /Rewritten in the admin area\./);
    for (const name of ['Madeline LoConti Winney', 'Darrin Anderson', 'Dr. Jennifer Billingsley']) {
      assert.ok(body.includes(name), `${name} was dropped by an admin save`);
    }
  });
});

// The lead row's spacing is applied by interpolating a style attribute, and
// EJS's escaping form turns its quotes into entities -- a broken attribute that
// drops the rule in the Node build only, while PHP renders it fine. The two
// builds looked identical in the seed and differed in the browser.
test('the board grid spacing renders as a real attribute', async () => {
  await withServer(async (base) => {
    const body = await (await fetch(`${base}/board`)).text();
    assert.match(body, /class="grid grid-3" style="margin-top:28px"/);
    assert.ok(!body.includes('style=&#34;'), 'an attribute was HTML-escaped into entities');
  });
});

// navParent is what nests Community Partnerships under About. It is a real form
// field now, so it must survive a save -- and the partner and gallery arrays,
// which have no field, must survive alongside it.
test('editing the community page in admin keeps its nesting and its partners', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    await fetch(`${base}/admin/pages/page-community`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: base },
      body: new URLSearchParams({
        title: 'Community Partnerships',
        slug: 'community',
        navLabel: 'Community Partnerships',
        summary: 'Rewritten in the admin area.',
        body: 'Rewritten too.',
        inNav: 'on',
        navParent: 'about',
      }).toString(),
      redirect: 'manual',
    });

    const home = await (await fetch(`${base}/`)).text();
    assert.match(home, /nav-menu/, 'the dropdown is gone from the header');

    const page = await (await fetch(`${base}/community`)).text();
    assert.match(page, /Rewritten in the admin area\./);
    assert.ok(page.includes('Solid Rock Teen Center'), 'the partner was dropped by an admin save');
    assert.match(page, /img\/partners\/solid-rock-1\.jpg/, 'the gallery was dropped by an admin save');
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

// The impact band's heading and supporting lines are easy to add to the form
// and forget in the save handler, which fails silently: the field shows up,
// accepts text, and drops it on submit.
test('the impact heading and supporting lines survive a settings save', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    const body = new URLSearchParams({
      name: 'LEO Foundation',
      timezone: 'America/Phoenix',
      impactTitle: 'A heading that must persist',
      impact0value: '$9M+',
      impact0label: 'in scholarships',
      impact0detail: 'A supporting line that must persist',
      enrollmentType: 'annual',
      enrollmentOpensOn: '11-01',
      enrollmentClosesOn: '03-31',
    });

    const saved = await fetch(`${base}/admin/settings`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', origin: base, cookie },
      body,
      redirect: 'manual',
    });
    assert.equal(saved.status, 302);

    const home = await (await fetch(`${base}/`)).text();
    assert.match(home, /A heading that must persist/);
    assert.match(home, /A supporting line that must persist/);
    assert.match(home, /\$9M\+/);
  });
});

// The carousel that used to sit above the hero is gone, and the awarded student
// is the first thing on the page. The slides themselves are untouched in the
// store -- this pins that the homepage stops *rendering* them, so putting the
// carousel back is one include and not a re-transcription.
test('the homepage leads with the student, not a carousel', async () => {
  await withServer(async (base) => {
    const body = await (await fetch(`${base}/`)).text();

    assert.doesNotMatch(body, /data-slider/, 'no carousel on the homepage');
    assert.equal((body.match(/data-slide\b/g) || []).length, 0, 'and no slides');
    assert.doesNotMatch(body, /slider-arrow|slider-dots/, 'and none of its controls');

    // The hero is now the first thing inside main.
    assert.match(body, /<main>\s*<section class="hero hero-centred"/,
      'the hero opens the page');

    assert.equal((body.match(/class="pillar"/g) || []).length, 3);
    // site.js still ships -- the depth, staging and awardee modules all need it.
    assert.match(body, /js\/site\.js/, 'the site script is still loaded');
  });
});

// The slides are kept, not deleted: they stay in the store and stay editable in
// /admin, so this was a rendering decision rather than a loss of content.
test('the slides survive in the store even though nothing renders them', async () => {
  await withServer(async (base) => {
    const body = await (await fetch(`${base}/`)).text();
    assert.doesNotMatch(body, /data-slider/);
  });
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  assert.equal(seed.slides.length, 3, 'all three slides are still seeded');
});

// The header carried a CSS placeholder mark for months. Now that real artwork
// is in the repo, nothing should render the site's identity from type again.
test('the real lockup and favicons are served, not a placeholder', async () => {
  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();
    // The lion mark alone. The full crest carried its own LEO FOUNDATION, which
    // at masthead size was an illegible smudge beside the same words in type.
    assert.match(home, /class="wordmark-lion"[^>]*leo-mark-lion\.png/, 'the lion is the masthead mark');
    assert.match(home, /class="wordmark-name"/, 'the name is set in type, not shipped as a raster');
    // The footer carries the same lion. The foundation's name and EIN are set
    // in type in the legal line below it, so a lion-only mark loses nothing.
    assert.match(home, /foot-mark[^>]*leo-mark-lion\.png/, 'the footer carries the lion mark');
    // The client asked for the horizontal wordmark back underneath it. The two
    // are one sign-off, so the mark is decorative and the wordmark carries the
    // accessible name -- otherwise a screen reader announces the org twice.
    assert.match(home, /foot-lockup[^>]*leo-lockup-footer\.png/, 'the footer lost the wordmark');
    assert.match(home, /class="foot-mark"[^>]*aria-hidden="true"/,
      'the footer mark must be decorative now that the wordmark names the org');
    const lockupAlt = home.match(/class="foot-lockup"[\s\S]*?alt="([^"]*)"/);
    assert.ok(lockupAlt, 'the footer wordmark has no alt text');
    assert.match(lockupAlt[1], /LEO Foundation/, 'the wordmark alt must name the organisation');
    assert.match(home, /apple-touch-icon/, 'apple touch icon');
    assert.match(home, /favicon-32\.png/, 'png favicon');
    assert.doesNotMatch(home, /<span class="mark">LEO<\/span>/, 'placeholder is gone');

    for (const asset of ['/img/brand/leo-mark-lion.png',
                         '/img/brand/leo-crest-white.png',
                         '/img/brand/leo-crest.png',
                         '/img/brand/leo-lockup-footer.png',
                         '/img/brand/leo-lion-white.png',
                         '/img/brand/favicon-180.png']) {
      assert.equal((await fetch(`${base}${asset}`)).status, 200, asset);
    }
  });
});

// ---------------------------------------------------------------------------
// The split hero.
//
// The client's fifth ask was to keep the scholarships awarded as the focus, so
// half the hero is an awarded student, cut free of the background of their own
// photograph. Three things can go wrong quietly, and each has a test: the
// student can be a draft and appear anyway, the quote can drift away from the
// bio it was lifted from, and the deadline -- which is what applicants actually
// come here for -- can be lost when the card it lived in is replaced.
// ---------------------------------------------------------------------------

test('the hero centres on an awarded student, with no headline above them', async () => {
  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();

    assert.match(home, /class="hero hero-centred"/, 'the hero centres on the student');
    // The visible headline is gone, but the page must still have a heading.
    assert.match(home, /<h1 class="visually-hidden">Every scholarship is a door someone walks through\.<\/h1>/,
      'the h1 is hidden, not deleted');
    assert.doesNotMatch(home, /<h1>Every scholarship/, 'no visible hero headline');
    assert.match(home, /class="hero-student-cut"[^>]*\/img\/recipients\/keian-cutout\.png/,
      'the cutout, not the uncut photograph');
    assert.match(home, /class="hero-student-who"[\s\S]*?<strong>Keian<\/strong>/,
      'the student is named');
    assert.match(home, /thank you, thank you, THANK YOU!/, 'their own words, in the hero');

    // The figure must not float clear of its panel: the source crop runs off at
    // the shoulder, so the image carries no max-width of its own.
    assert.equal((await fetch(`${base}/img/recipients/keian-cutout.png`)).status, 200);
  });
});

test('the hero keeps the deadline when the deadline card gives way to a student', async () => {
  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();
    assert.match(home, /class="hero-deadline"/, 'the deadline stays in the hero');
    assert.match(home, /class="hero-deadline-count"/, 'and keeps its figure');
    assert.doesNotMatch(home, /<aside class="deadline">/, 'the card it replaces is gone');
  });
});

test('a hero student who is not published does not reach the hero', async () => {
  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();
    assert.doesNotMatch(home, /hero-centred/, 'a drafted student takes the hero with them');
    assert.match(home, /<h1>Every scholarship/, 'the visible headline comes back with the fallback');
    assert.doesNotMatch(home, /keian-cutout\.png/, 'and their cutout with them');
    // The deadline card comes back rather than the hero losing half of itself.
    assert.match(home, /<aside class="deadline">/, 'the hero falls back to the card');
  }, (content) => {
    for (const person of content.recipients) {
      if (person.id === 'rec-keian') person.draft = true;
    }
  });
});

test('an unset or unresolvable hero student leaves the hero as it was', async () => {
  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();
    assert.doesNotMatch(home, /hero-centred/);
    assert.match(home, /<aside class="deadline">/);
  }, (content) => { content.site.heroStudentId = 'rec-nobody'; });
});
