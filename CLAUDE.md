# LEO Foundation website

Replaces the WordPress site at leofoundationusa.org. No WordPress, no database,
no build step. Read this before changing anything — most of it is here because
something bit us.

Branch for all work: `claude/leo-foundation-site-redesign-knr6vv`. PR #1 (draft).

## What the client asked for

In their words, from the opening request:

1. Get rid of WordPress
2. Something **simple and dynamic** to manage the site
3. Account for **scholarships that will be awarded**
4. Account for **pages that display being time-limited within periods through
   the year**
5. **Highlight scholarships awarded, and keep that as a focus**

(5) is the one to protect. It is currently the weakest part of the site — see
*Open work*.

## Two builds, one content format

| | Runtime | Role |
| --- | --- | --- |
| `php/` | PHP 8, zero dependencies | **The deployed one.** Shared cPanel hosting, no Composer, no shell. |
| root | Node 20 + express/ejs/marked | Local development twin. |

They render identically. **Each carries its own copy of the seed content** —
`data/content.json` and `php/leo-app/data/content.json`. They are *not* one
file. They have drifted before (a scholarship lost its summary and rendered
blank on the deployed site only). A test now asserts they match; keep both in
step in the same commit.

Any change to a view, stylesheet, or route generally needs doing **twice** —
once in `php/leo-app/views` + `php/public_html/css`, once in `views/` +
`public/css`.

## Commands

```bash
npm test                                    # 40 tests
php php/test/run.php                        # 45 tests
find php -name '*.php' -exec php -l {} \;   # lint

ADMIN_PASSWORD='...' npm start              # Node build, :3000
cd php && php -S 127.0.0.1:8787 -t public_html router.php
```

The PHP dev server **must** have `-t public_html` or CSS 404s. `router.php` is
dev-only; production uses `public_html/.htaccess`.

Screenshots: Chromium is at `/opt/pw-browsers`, Playwright at
`/opt/node22/lib/node_modules/playwright`. Do not run `playwright install`.

## The core idea

`src/schedule.js` / `php/leo-app/src/Schedule.php` is the heart. Everything
else is CRUD around it.

- Windows are **annual and wrap the year**: enrollment is stored as month-day
  pairs (`11-01` → `03-31`). A close date earlier than the open date means the
  cycle crosses New Year. It rolls over by itself; nobody edits anything in
  November.
- Calendar **dates**, not timestamps, resolved in `America/Phoenix` (no DST).
- Three independent controls: site-wide enrollment period, a per-award window
  override, and a show-from/hide-after publish gate.
- One date is resolved per request (`src/app.js`, `App::run()`). Signed-in
  admins get `?asOf=YYYY-MM-DD` date travel for checking how a page will look
  in February.

Content lives in one JSON file written atomically (temp file + rename). Admin
is a config table (`RESOURCES` in `src/routes/admin.js`) so four content types
share one route set and one form template.

## Gotchas that cost real time

**PHP rewrites `.` to `_` in POST field names.** `publish.showFrom` arrives as
`publish_showFrom`, so the field saves silently as nothing. Use bracket
notation via `Admin::fieldName()` / `fieldId()`. Regression test exists.

**PHP turns numeric array keys into ints.** `'2025'` !== `2025` under `===`
broke the recipients year filter silently. Use `array_map(strval(...), ...)`
and `SORT_STRING`.

**`array +` keeps the left operand.** `$scholarship + [...]` returned the raw
window spec instead of the resolved one. Use `array_merge`.

**PHP partials do not inherit caller scope.** `App::render()` tracks
`$this->current`; `App::partial()` merges it.

**Tests must not read the clock in UTC.** Use
`schedule.todayIn('America/Phoenix')`, never `new Date().toISOString()` — for
seven hours each evening UTC is already tomorrow and the window reads
"upcoming".

**Form field added, save handler not updated = silent data loss.** The field
renders, accepts text, drops it on submit. Both suites have a settings
round-trip test; extend it when adding fields.

**`ch` resolves against the element's own font.** A `max-width` in `ch` on a
sans container does not constrain a serif `h2` the way you expect. Put the
constraint on the element whose font it should measure.

**Cards are flex columns**, so children inherit `align-self: stretch`.
`display: inline-flex` will not stop a pill filling the card width;
`align-self: flex-start` will.

## Design system

Navy `--ink: #10263d`, gold `--gold: #b8862b` / `--gold-bright: #d9a441`,
Georgia serif headings, sans body, 1120px `.wrap`. One stylesheet, no
framework.

The homepage impact band deliberately continues the hero's navy rather than
dropping to white — gold numerals need the contrast, and its panelled grid
echoes the hero's deadline card (same `#2c4f70` border, same 16px radius).
Everything belongs inside `.wrap`; an edge-to-edge element misaligns with the
rest of the page.

## Deployment

GoDaddy VPS, cPanel account `leofoundationusa`, IP `160.153.181.93`. Shell
access removed, so no Composer, no Node Selector, no SSH.

- `leo-app/` goes **above** the web root. `public_html/index.php` also tolerates
  it being extracted *inside* `public_html` (the client did that once) and
  prints where it looked if it cannot find `boot.php`.
- Temp URL `http://160.153.181.93/~leofoundationusa/` returns **"Not
  supported"** until WHM → Security Center → **Apache mod_userdir Tweak** has
  *Exclude Protection* ticked for that account. As of last check it still
  returns that.
- `.cpanel.yml` supports cPanel Git Version Control deployment. It copies code
  only — it never writes `leo-app/data/` (content edited through `/admin`) or
  `leo-app/config.php` (admin password).
- Before DNS cutover: run AutoSSL, uncomment the HTTPS redirect at the top of
  `public_html/.htaccess`, verify the old-URL redirects in `docs/MIGRATION.md`.

**Never ship `config.php`** — it holds the admin password.

## This sandbox cannot reach the client's server

Outbound traffic goes through an egress proxy that allows **port 443 only**,
against a host allowlist. Confirmed by testing, not assumed:

- `leofoundationusa.org` and `www.leofoundationusa.org` are now **allowed** (the
  client opened them up). Its WordPress REST API is public and unauthenticated:
  `/wp-json/wp/v2/pages?per_page=100` lists every page, and
  `/wp-json/wp/v2/pages/<id>` returns rendered content. Far cleaner than parsing
  the theme's HTML. Note the impact counters render as `0` in the markup — the
  real figures are in the `data-value` attributes.
- `160.153.181.93` (the client's server) → still 403 `host_not_allowed`
- port 2083 (cPanel) and 21 (FTP) → connection reset even for allowed hosts
- `claude.ai` → 403

So: **you cannot upload to the server or verify the deployed site.** Reading the
old WordPress site does now work. Do not promise otherwise, and do not ask
for cPanel credentials — the port is closed regardless of credentials. Hand the
client a zip and File Manager steps, or use the cPanel Git deployment.

## Content accuracy

Scholarships, recipients, and the impact figures were **transcribed from the
live WordPress site on 2026-08-24** and verified against the published pages.
`leo-foundation/FINDINGS.md` records the audit, and `leo-foundation/data/`
keeps the raw scrape as the reference copy.

- 13 published scholarships, each with the amount and qualifying criteria as
  worded on its own page.
- Tim Browning Memorial and Arvizu are **not currently being issued** — the
  client's word. They are kept as `draft: true` so the copy survives without
  rendering, and a test asserts they stay off the public site. Alex Acosta is
  the third withdrawn award; it is *not* seeded, because the live site publishes
  no amount or criteria for it.
- "Twenty years" / founded 2006 — confirmed correct by the client.
- The impact figures (20 years, 5,685 students, $6.9M awarded, $8.5M raised)
  are the counter targets on the live site, replacing earlier reconstructions.

**The live site publishes no award year and no dollar amount for any
recipient.** Both fields are deliberately empty on all 15 records; the views
omit them rather than showing a guess. If the client wants "Class of 2025" or a
per-student amount, that data has to come from them. Four bios name no specific
award, so those recipients point at the general LEO Foundation Scholarship.

**Recipient photos are still hosted on the WordPress site**
(`leofoundationusa.org/wp-content/uploads/...`). Those URLs die the moment
WordPress is replaced at that domain. Before cutover, either copy the uploads
directory onto the new server and rewrite `photoUrl`, or preserve
`/wp-content/uploads/` as a static path. This is the one loose end that would
visibly break the recipients page.

## Open work

1. ~~Recipients are empty~~ — **done.** All 15 published recipients are loaded,
   with photos and verbatim bios; the homepage features three.
2. ~~Verify seeded scholarship copy against the real site~~ — **done.** See
   *Content accuracy*.
3. **Rehost the recipient photos** before cutover — see *Content accuracy*.
4. Confirm **Save works in `/admin`** on the server — depends on file
   permissions for `leo-app/data/content.json`, untestable from here.
5. Old-URL redirect map verification before cutover. The scholarship slugs now
   match the WordPress ones, so most of the map should be one-to-one.
6. No CI. A workflow running both suites would catch the seed-drift class of
   bug on push.

## Client context

Windows and Mac, no sudo on the Mac. Works through cPanel/WHM in Chrome.
Prefers being handed a file to double-click over a command to type. Their live
cPanel password appeared in a screenshot early on and should be rotated.
