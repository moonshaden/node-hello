'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
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

// A deploy replaced the stylesheet and the script on the server and a visitor
// kept being served the old pair: the URLs carried no version and the host
// sends no Cache-Control, so the browser cached them heuristically off
// Last-Modified and a file that had been a fortnight old stayed "fresh" for
// over a day. Nothing on the page could dislodge it.
//
// The version is the CONTENT hash, not the mtime: a deploy rewrites every mtime
// whether or not the bytes changed, which would bust every cache on every
// deploy AND give the two builds different URLs for the same file, which the
// cross-build render diff reads as a divergence.
test('the stylesheet and the script are requested with a version of their content', async () => {
  await withServer(async (base) => {
    const html = await (await fetch(`${base}/`)).text();

    for (const [asset, urlPath] of [
      ['public/css/site.css', '/css/site.css'],
      ['public/js/site.js', '/js/site.js'],
    ]) {
      const expected = crypto
        .createHash('sha256')
        .update(fs.readFileSync(path.join(__dirname, '..', asset)))
        .digest('hex')
        .slice(0, 10);
      assert.ok(
        html.includes(`${urlPath}?v=${expected}`),
        `${urlPath} is not requested with the hash of its own bytes`,
      );
    }
  });
});

// Versioned asset URLs only work if the browser re-reads the HTML to see them.
// The host sends no Cache-Control on PHP output at all -- measured on the live
// subdomain, where the same `Header always set` block puts X-Frame-Options on
// /css/site.css and nothing on / -- so a held page goes on asking for the old
// hashes, which is how a replaced picture kept showing its previous version
// after a correct deploy. The header is therefore set by the app, not the
// server config, in both builds.
test('the generated HTML tells the browser to revalidate, and carries its own security headers', async () => {
  await withServer(async (base) => {
    for (const route of ['/', '/scholarships', '/scholarships/leo-foundation-scholarship']) {
      const res = await fetch(`${base}${route}`);
      assert.match(
        res.headers.get('cache-control') || '',
        /no-cache/,
        `${route}: the page may be held, so new asset hashes never reach a returning visitor`,
      );
      assert.equal(res.headers.get('x-frame-options'), 'SAMEORIGIN', route);
      assert.equal(res.headers.get('x-content-type-options'), 'nosniff', route);
      assert.equal(res.headers.get('referrer-policy'), 'strict-origin-when-cross-origin', route);
    }

    // The assets themselves must NOT be no-cache -- the whole point of hashing
    // their URLs is that they can be held for a long time.
    const css = await fetch(`${base}/css/site.css`);
    assert.doesNotMatch(css.headers.get('cache-control') || '', /no-cache/,
      'the stylesheet should be cacheable; its URL carries a hash');
  });
});

// The stylesheet and the script carry a content hash; images did not, and it
// reached the client. The lion mark was replaced in place -- same filename, navy
// plate swapped for a transparent one -- and they still saw the navy version,
// because nothing about the URL had changed and their browser served what it
// already had. The deployed bytes were right the whole time, which is exactly
// what makes this hard to spot: every server-side check passes.
//
// This reads the RENDERED pages, not the templates, because a template can be
// right while a route hands it a value that never went through the helper.
test('every image the site renders carries a content hash', async () => {
  await withServer(async (base) => {
    const routes = [
      '/', '/scholarships', '/recipients', '/about', '/faq', '/donate', '/contact',
      '/board', '/programs', '/community', '/privacy',
      '/scholarships/leo-foundation-scholarship',
      '/scholarships/gcu-guild-continuing-student-scholarship',
      '/scholarships/richard-mccurdy-club-sports-golf-scholarship',
      '/scholarships/evan-c-gary-memorial-scholarship',
    ];

    let checked = 0;
    for (const route of routes) {
      const html = await (await fetch(`${base}${route}`)).text();
      for (const [, src] of html.matchAll(/<img[^>]+src="([^"]+)"/g)) {
        if (!src.includes('/img/')) continue;   // not one of ours
        checked += 1;
        assert.ok(
          src.includes('?v='),
          `${route}: ${src} is served without a content hash, so replacing that file in place never reaches a returning visitor`,
        );
      }
    }
    assert.ok(checked >= 20, `expected to check a good number of images, saw ${checked}`);
  });
});

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
    assert.match(body, /<main>\s*<section class="hero hero-centred/,
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

// The photograph closing /programs. Two halves that fail differently: the
// picture has to be an <img> with a content hash rather than a CSS background,
// and it has to render inside the copy column -- outside it, it cannot end
// level with the card, which is the whole point of it.
test('the programs page renders its photograph inside the copy column', async () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const page = seed.pages.find((p) => p.slug === 'programs');
  assert.ok(page.picture && page.picture.src, 'the programs page carries no picture');

  await withServer(async (base) => {
    const body = await (await fetch(`${base}/programs`)).text();
    assert.match(body, /<figure class="page-picture">/, 'the picture is not rendered');
    // Versioned -- an unversioned src sits in a browser cache for a year, which
    // is how a deleted plate survived a deploy once already.
    const src = body.match(/<figure class="page-picture">[\s\S]*?<img src="([^"]+)"/);
    assert.ok(src, 'the figure has no image');
    assert.ok(src[1].startsWith(page.picture.src + '?v='),
      `the photograph is not content-hashed: ${src[1]}`);
    // And it must not have become a background on the way past: a url() in the
    // stylesheet carries no hash and this sheet would be the wrong place for it.
    const css = await (await fetch(`${base}/css/site.css`)).text();
    assert.doesNotMatch(css, /url\([^)]*quote-mlk/, 'the photograph is a CSS background');

    // It belongs INSIDE the copy column, under the last line of copy, the way
    // the community page's inline logo strip does -- not as a full-width block
    // of its own below the section. Checked structurally rather than by
    // indentation: nothing that closes the column or opens a new one may stand
    // between the column and the figure.
    const between = body.slice(body.indexOf('class="prose"'), body.indexOf('<figure class="page-picture">'));
    assert.ok(between.length > 0, 'the picture is rendered before the copy column');
    assert.doesNotMatch(between, /<div class="wrap"/, 'the picture sits in a wrap of its own, not in the copy');
    assert.doesNotMatch(between, /<\/section>/, 'the picture has left the copy section');
    // And the column it sits in has to be the one the picture can fill.
    assert.match(body, /class="wrap page-flow has-picture"/, 'the copy column is not laid out for the picture');
  });
});

// The impact figures appear on the homepage and, since the client asked for
// them there, under the copy on /board. One partial feeds both, so this reads
// the RENDERED pages: a template can be right while a route hands it a
// different array, and two sets of numbers on one site is exactly the drift the
// live WordPress site already has between its own pages.
test('the impact figures read the same on the homepage and on /board', async () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const board = seed.pages.find((p) => p.slug === 'board');
  assert.ok(board.impactFigures, 'the board page does not ask for the impact figures');

  await withServer(async (base) => {
    const home = await (await fetch(`${base}/`)).text();
    const page = await (await fetch(`${base}/board`)).text();
    assert.match(page, /class="impact impact-inline"/, 'the board page renders the grid unstyled');
    for (const item of seed.site.impact) {
      assert.ok(home.includes(`<div class="value">${item.value}</div>`), `the homepage lost ${item.value}`);
      assert.ok(page.includes(`<div class="value">${item.value}</div>`), `/board lost ${item.value}`);
      assert.ok(page.includes(`<div class="label">${item.label}</div>`), `/board lost the label for ${item.value}`);
    }
    // The band's heading belongs to the homepage; the page carries the panels
    // only, which is what was asked for.
    assert.doesNotMatch(page, /impact-head/, '/board carries the homepage band heading too');
  });
});

// The giving page opens with a callout under its jump index: the first sentence
// of the copy in bold, the second under it. Both were MOVED out of the
// paragraph, not copied, so the thing worth pinning is that the page does not
// say either of them twice -- and that it still says them at all, since this
// copy is transcribed and every word the live page publishes has to stay here.
test('the giving callout carries its two sentences once each, above the copy', async () => {
  const seed = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'data', 'content.json'), 'utf8'));
  const giving = seed.pages.find((p) => p.slug === 'donate');
  assert.ok(giving.notice && giving.notice.heading, 'the giving callout has no bold line');
  assert.ok(giving.notice.body, 'the giving callout has no body');
  for (const line of [giving.notice.heading, giving.notice.body]) {
    assert.ok(!giving.body.includes(line),
      `the callout line is still in the body as well, so the page says it twice: ${line}`);
  }

  await withServer(async (base) => {
    const body = await (await fetch(`${base}/donate`)).text();
    assert.match(body, /<div class="notice page-notice">/, 'the callout is not rendered');
    // The bold half is an h3, the way the scholarships panel sets it.
    assert.ok(body.includes(`<h3>${giving.notice.heading}</h3>`), 'the first sentence is not the bold line');
    assert.ok(body.includes(`<p>${giving.notice.body}</p>`), 'the second sentence is not under it');
    for (const line of [giving.notice.heading, giving.notice.body]) {
      const hits = body.split(line).length - 1;
      assert.equal(hits, 1, `the giving page renders "${line.slice(0, 30)}..." ${hits} times`);
    }
    // Above the copy, under the index -- the callout has to come first in the
    // column or it is just another paragraph.
    const at = body.indexOf('page-notice');
    assert.ok(at > body.indexOf('page-index'), 'the callout is above the jump index');
    // Anchored on the copy's own first words. It used to be anchored on
    // "tax-deductible", which moved INTO the callout when the third sentence
    // did -- the assertion would then have been comparing the callout with
    // itself and passing for the wrong reason.
    assert.ok(at < body.indexOf('Scholarship assistance can be designated'),
      'the callout is below the copy it introduces');
  });
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
    // Cropped to the name and its two rules: the strapline the artwork used to
    // carry is set in type below, so the raster is the name only.
    assert.match(home, /foot-lockup[^>]*leo-wordmark-footer\.png/, 'the footer lost the wordmark');
    assert.match(home, /<span class="foot-strap">Leadership/, 'the strapline is not set in type');
    assert.match(home, /class="foot-mark"[^>]*aria-hidden="true"/,
      'the footer mark must be decorative now that the wordmark names the org');
    const lockupAlt = home.match(/class="foot-lockup"[\s\S]*?alt="([^"]*)"/);
    assert.ok(lockupAlt, 'the footer wordmark has no alt text');
    assert.match(lockupAlt[1], /LEO Foundation/, 'the wordmark alt must name the organisation');
    // The strapline is real text now, so the alt must not repeat it -- a screen
    // reader would otherwise read the three words twice in a row.
    assert.doesNotMatch(lockupAlt[1], /Leadership/, 'the alt repeats the strapline that is now type');
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

    assert.match(home, /class="hero hero-centred/, 'the hero centres on the student');
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

// A `<%=` interpolation that BUILDS an attribute escapes the quotes it is meant
// to emit, which leaves a broken attribute here and valid markup in the PHP
// twin -- with both suites green, because both read the same seed and the same
// source. It has happened twice (a `style` attribute, then `loading="lazy"` on
// the hero rail), and the only check that catches it is looking at the output.
// An escaped quote in rendered markup is always that mistake: real body copy
// carrying a quotation mark comes out as `&#34;` inside a text node, never
// as part of an attribute, and nothing seeded does even that.
test('no rendered page escapes the quotes of an attribute it is building', async () => {
  const paths = ['/', '/scholarships', '/recipients', '/faq', '/about', '/donate',
    '/contact', '/programs', '/community', '/board'];
  await withServer(async (base) => {
    for (const p of paths) {
      const res = await fetch(`${base}${p}`);
      assert.equal(res.status, 200, `${p} renders`);
      const html = await res.text();
      assert.doesNotMatch(html, /=&#34;/, `${p} builds its attributes with the raw tag`);
      // A template that fails to parse renders as a page, not as an error, so
      // the status code above does not notice. EJS says so in the body.
      assert.doesNotMatch(html, /Could not find matching close tag/, `${p} parses`);
    }
  });
});

// The privacy policy is the live page verbatim. The test pins the sentences
// that carry the legal weight -- a "we may collect" list and the children's
// clause -- so a well-meaning rewrite in /admin shows up here rather than on
// the site.
test('the privacy policy renders as transcribed', async () => {
  await withServer(async (base) => {
    const res = await fetch(`${base}/privacy`);
    assert.equal(res.status, 200);
    const body = await res.text();
    assert.match(body, /Last Updated: May 5, 2025/);
    assert.match(body, /Our Website is not intended for children under 13 years of age\./);
    assert.match(body, /Children/, 'section 7 is present');
    // Nine bold section headings in the source become nine anchored h2s, which
    // is what earns the page its jump-to index.
    assert.equal((body.match(/<h2 id=/g) || []).length, 9);
    assert.match(body, /class="page-index"/);
  });
});

// Legal pages are linked from the footer and deliberately kept out of the
// header. The footer list is driven by `legal: true` rather than by slugs, so
// this also covers the terms of service arriving later.
test('a legal page is linked in the footer and stays out of the main nav', async () => {
  await withServer(async (base) => {
    // Checked from an unrelated page: the footer is on every route.
    const body = await (await fetch(`${base}/scholarships`)).text();
    assert.match(body, /foot-legal-head">Legal<\/h4>/);
    assert.match(body, /href="\/privacy">Privacy<\/a>/);

    const nav = body.slice(body.indexOf('<nav'), body.indexOf('</nav>'));
    assert.ok(!nav.includes('/privacy'), 'legal copy does not belong in the header');
  });
});

// `legal` is a real checkbox in the page form, and a checkbox that the save
// handler does not read comes back false on the first admin edit -- which would
// silently drop the page out of the footer. Same class as the settings
// round-trip tests.
test('the legal flag survives an admin save of the page', async () => {
  await withServer(async (base) => {
    const cookie = await signIn(base);
    await fetch(`${base}/admin/pages/page-privacy`, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie, origin: base },
      body: new URLSearchParams({
        title: 'Privacy Policy',
        slug: 'privacy',
        navLabel: 'Privacy',
        body: 'Rewritten in the admin area.',
        legal: 'on',
      }).toString(),
      redirect: 'manual',
    });

    const body = await (await fetch(`${base}/scholarships`)).text();
    assert.match(body, /href="\/privacy">Privacy<\/a>/, 'the footer link did not survive the save');
  });
});
