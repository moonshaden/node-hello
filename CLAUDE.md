# LEO Foundation website

Replaces the WordPress site at leofoundationusa.org. No WordPress, no database,
no build step. Read this before changing anything — most of it is here because
something bit us.

Branch for all work: `claude/leo-foundation-site-redesign-knr6vv`. PR #3 (draft).

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
npm test                                    # 70 tests
php php/test/run.php                        # 67 tests
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

**Recipient stories are excerpted at render time, not in the store.** The
published bios run 334-1,271 characters, which made one card in a row nearly
four times the height of its neighbour. `content.excerpt()` /
`Content::excerpt()` cut at a sentence end past the halfway mark (falling back
to a word break), at `EXCERPT_LIMIT = 520`. A sentence-end cut keeps its full
stop and takes no ellipsis; only a mid-sentence cut gets one. The full text
stays in the store and the card offers it behind a `<details>` — so editing a
bio in `/admin` re-excerpts by itself, and nothing published is unreachable.
Both builds must agree on where to cut; a test asserts it.

**An `img` width/height attribute overrides `aspect-ratio`.** The portraits
carry `width`/`height` so the card reserves space before the photo loads, but
that is a presentational hint applied as a real `height`, and the rule only set
`width` and `aspect-ratio`. Portraits rendered 1000px tall instead of 359px.
`height: auto` in the CSS is what makes the pair work.

**Both builds must map `##` to `<h2>`.** The PHP `Markdown` used to render a
heading one level down from marked (`##` → `<h3>`), so the About page's section
headings were unstyled on the deployed site and styled on the dev twin —
`.prose h2` is what carries the rule and the spacing. Both now clamp a lone `#`
up to `h2` (the page title is the only `h1`) and render `##` as `h2`. A test in
each suite pins it.

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

The **FAQ is the live page verbatim**, transcribed from
`/scholarship-faqs/` on 2026-08-29: thirteen questions, in the live order, with
the answers word for word. The two links in the answers point at `/scholarships`
rather than the old absolute WordPress URLs, so they work in both builds today
instead of depending on the DNS cutover and the redirect map. Thirteen questions
in one prose column read as a wall, so `page.ejs` / `page.php` give a page with
six or more headings a jump-to index and an anchor per heading
(`Markdown::renderSections()` / `markdown.renderSections()`); the id rules are
duplicated across the builds and a test asserts they agree.

The **Giving page is the live `/ways-to-give/` page**, transcribed on
2026-08-29: eleven ways to give, in the live order, with the copy word for word.
The live site publishes them twice, as two Avada tab widgets on the same page —
one desktop, one `fusion-no-medium-visibility fusion-no-large-visibility`
mobile-only. The two are *not* the same: the desktop copy carries a check-payable
line under Annual funds and an estate-plans paragraph under Set up scholarship
fund; the mobile copy carries the per-way Aplos donate buttons and drops both
paragraphs. The seeded page is the **union**, so nothing the site publishes is
lost. `/donate/` (the header's Donate link) is a third, staler copy of the same
eleven tabs with no donate buttons; `/give/` and `/leo-events/` are untouched
Avada demo boilerplate, Lorem ipsum and all — do not seed from them.

**The live donate processor is Aplos, not Mightycause.** `site.donateUrl` pointed
at `mightycause.com/organization/LEOFoundation`, which appears nowhere on the live
site; it is now `https://www.aplos.com/aws/give/LEOFoundation/Donation`. Three ways
to give have their own Aplos endpoints, carried in the body: `/donate-now`
(existing scholarship), `/fieldorstudy` (field or study) and `/donate` (set up a
fund). Re-check these with the client before cutover — they are the money path.

**EIN 20-4879525 is confirmed** against the live footer, which reads "Leo
Foundation is a 501(c)3 organization. Federal Identification Number: (EIN)
20-4879525". The giving page's own tax claim is the unqualified "And, it's
tax-deductible", so the page says that rather than "to the extent allowed by law".

**The live site publishes no award year and no dollar amount for any
recipient.** Both fields are deliberately empty on all 15 records; the views
omit them rather than showing a guess. If the client wants "Class of 2025" or a
per-student amount, that data has to come from them. Four bios name no specific
award, so those recipients point at the general LEO Foundation Scholarship.

The **Programs & Partnerships page is the live `/programs-partnerships/` page**,
transcribed on 2026-08-30: three programs, in the live order, copy word for
word — Foster Youth Programs, Impact Leadership Program, Youth Development
Academy. It is seeded as a page with slug `programs` (short slugs are the
convention here: the FAQ is `/faq`, not `/scholarship-faqs`) and `inNav: true`,
matching the live nav.

- The programs live in a `programs` array **on the page record**, the same shape
  the board roster uses, and survive an admin save for the same reason
  (`applyFields()` spreads the existing record first). Each carries markdown
  `body`, so the two Arizona Basketball Coaches Association links stay editable
  in `/admin` rather than being baked into the template.
- Photos are rehosted under `public/img/programs/` and
  `php/public_html/img/programs/` as `/img/programs/<slug>.jpg`, capped at 800px
  and re-encoded as JPEG (6.5 MB → 0.28 MB). The live media library publishes no
  alt text, so the alt was written here from the photograph.
- **Not carried across:** the four-button nav strip (APPLICATION DATES /
  SCHOLARSHIP FAQ's / SCHOLARSHIP RECIPIENTS / AVAILABLE SCHOLARSHIPS) is Avada
  chrome duplicating this site's own header; the impact counters, which are the
  same 20 / $8.5M / 5,685 / $6.9M figures the impact band already carries; and
  the contact form, which needs a mail handler this build does not have — the
  page body links to `/contact` instead.

The **Board of Directors page is the live `/leadership-2/` page** ("LEADERSHIP"),
transcribed on 2026-08-29: six members, in the live order, with each office and
bio word for word. It is seeded as a page with slug `board` and `inNav: false` —
the client asked for it reachable from Contact, not added to the header — and
the contact page body carries the link so `/admin` can edit it like any other
copy. Two spots to know about:

- The roster lives in a `members` array **on the page record**, not as its own
  content type. Nothing in the page form edits it, so it survives an admin save
  only because `applyFields()` spreads the existing record first. A test in each
  suite posts the page form and asserts the six people are still there.
- Michele Simphoukham's published office is **Chief Financial Officer** while her
  own bio calls her Treasurer, and **Robb** Kottman's bio spells him Rob
  throughout. Both are as published; do not "fix" them. The one deviation from
  verbatim is a dropped letter in Darrin Anderson's bio ("mproving" → improving).

**Board photos are served from this repo**, the same way the recipient photos
are. All six are committed under `public/img/board/` and
`php/public_html/img/board/` as `/img/board/<slug>.jpg`. The published portraits
are circular gold-ringed badges matted on white at four different aspect ratios
and with different amounts of padding, so each was cropped to the ring's
bounding box, squared, capped at 800px and re-encoded as JPEG (1.8 MB → 0.19 MB).
That crop is what makes the cards frame consistently — scaling the originals
as-is left one portrait visibly smaller than its neighbour. Greg Sharp's
published photo is a **group photo, not a headshot**; it is carried as published
and is the obvious thing to ask the client to replace.

**Recipient photos are served from this repo.** All 15 are committed under
`public/img/recipients/` and `php/public_html/img/recipients/`, downscaled to
800px wide and re-encoded as JPEG (5.4 MB → 1.0 MB). `photoUrl` is an
app-absolute `/img/recipients/<slug>.jpg`, which `link_url()` prefixes with the
base path so it survives the `/~leofoundationusa` temporary URL. `.cpanel.yml`
copies the directory on deploy — if you add a photo, check it still does.
Nothing on the site loads an image from the WordPress host any more.

**The homepage hero is a `slides` array, seeded from the live hero on
2026-08-29.** The live homepage publishes the same three-item hero *twice*, the
way `/ways-to-give/` publishes its tabs twice:

- `fusion-builder-row-4`, three full-bleed panels, desktop only
  (`fusion-no-small-visibility`). Headings PROGRAMS / SCHOLARSHIP FAQ's / LEO
  NEWS over `pexels-jeswin-5265284-scaled.jpg`, `students-in-library.jpg`,
  `graduation.jpg`.
- `fusion-builder-row-5`, a **LayerSlider** (`ls-wp-container`, 240×300,
  `fusion-no-medium-visibility fusion-no-large-visibility`), mobile only. Same
  three destinations, fuller headings, and two different photographs —
  `Basketball.png` (children with a basketball, square) and
  `pexels-pixabay-267885-1.jpg` (an alternate mortarboard-toss).

There is **no Avada fusion-slider and no Revolution slider**: `#sliders-container`
renders empty. The LayerSlider is the only slider on the page.

The seed is the union: the slider's fuller headings over the panels' larger
full-bleed photographs. Nothing published has body copy, a subheading, or a
button — the whole slide is the link — so `subheading`, `body` and `ctaLabel` are
empty strings on every slide and a component should make the slide itself
clickable. **`ctaUrl` is set only where this site has somewhere to send you:**
`/scholarship-faqs/` maps to `/faq`, but `/programs-partnerships/` and
`/mission-moments-newsletter/` are real live pages that have not been
transcribed, so those two slides link nowhere rather than 404. A test in each
suite asserts no slide points at a path this site does not serve.

Slide images are committed to `public/img/slides/` and
`php/public_html/img/slides/` as `/img/slides/<slug>.jpg`, capped at 2000px wide
and re-encoded as JPEG — 0.78 MB of originals down to **0.61 MB** for all three.
The live media library publishes **no alt text on any image**, so the alt in the
seed was written here from the photograph.

**The three LEO write-ups are a `pillars` array.** The name is an acronym and
`fusion-builder-row-21` on the homepage publishes a paragraph for each word —
Leadership, Education, Opportunity, in that order. That row is the *only* place
they appear; `/who-we-are-2/` and `/what-we-do/` do not carry them. Each has a
circled Font Awesome icon (`fa-user-tie`, `fa-book-reader`, `fa-hands-helping`)
and **no tagline** — the live markup is icon, word, one paragraph — so `tagline`
is an empty string on all three. Do not compose one.

## Open work

1. ~~Recipients are empty~~ — **done.** All 15 published recipients are loaded,
   with photos and verbatim bios; the homepage features three.
2. ~~Verify seeded scholarship copy against the real site~~ — **done.** See
   *Content accuracy*.
3. ~~Rehost the recipient photos~~ — **done.** See *Content accuracy*.
4. Confirm **Save works in `/admin`** on the server — depends on file
   permissions for `leo-app/data/content.json`, untestable from here.
5. **The About page's figures disagree with the impact band, and the live site
   disagrees with itself.** About says "more than 3,000 young people to over
   $5 million"; the impact band says 5,685 students and $6.9M. Neither is what
   the live site publishes. Its homepage prose reads "over 4,500 youth to over
   $6M"; its counter widgets (on nine inner pages) read 5,685 students /
   $6.9M awarded / $8.5M raised — except `/ways-to-give` ($8.9M raised) and
   `/financial-statements` (4,500 students / $6M / $8M, evidently stale). The
   "3,000 / $5 million" wording appears nowhere. Left unchanged deliberately:
   picking one would be guessing which set the client considers current. Ask
   them, then make the About copy and the impact band agree.
6. **Confirm the four Aplos giving links with the client.** The live site sends
   donors to `aplos.com/aws/give/LEOFoundation/{Donation,donate-now,fieldorstudy,donate}`.
   Those are transcribed as published, but a dead giving link is the one bug that
   costs money, so have the client click each before cutover.
7. **One homepage slide still links nowhere.** `/programs-partnerships/` is
   done — transcribed, seeded as `/programs`, and the slide's `ctaUrl` now
   points at it. `/mission-moments-newsletter/` is still untranscribed, so that
   slide's `ctaUrl` stays empty. Seed it the way the others were seeded, then
   fill the slide in.
8. Old-URL redirect map verification before cutover. The scholarship slugs now
   match the WordPress ones, so most of the map should be one-to-one.
9. ~~No CI~~ — **done.** `.github/workflows/deploy.yml` runs both suites and the
   PHP lint on every push, which catches the seed-drift class of bug.

## Deploying

**Deploying works. It has been done, verified against the live account, and can
be repeated from a session here.** The repo of record is the private
`moonshaden/leo-foundation-site`; `moonshaden/node-hello` is the old public copy
and should not be developed against.

`.github/workflows/deploy.yml` uploads the PHP build over FTPS from a GitHub
runner. It runs there rather than from an agent session because **the sandbox
reaches port 443 only** — ports 21, 990 and 2083 are all refused from it, so no
credential makes a direct upload possible. Tested, not assumed.

- Needs three repository secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.
  Without all three the deploy job skips on a push and fails loudly on a manual
  run. They are never in the repo.
- **`FTP_SERVER` must be the IP `160.153.181.93`, not the domain.** DNS has not
  been cut over: `leofoundationusa.org` and `www` both resolve to
  **198.12.238.168**, which is the old WordPress host. Pointing the deploy at
  the domain uploads the new site to the wrong server. Checked with a lookup,
  not assumed — re-check it after cutover, when the domain becomes correct and
  preferable.
- FTPS against a bare IP will fail certificate validation, because the
  certificate is issued for a hostname. Set the repo variable
  `FTP_VERIFY_CERT` to `false` for the IP, or use the VPS's own hostname (which
  its certificate does cover) and leave verification on.
- Two optional repo variables: `FTP_REMOTE_ROOT` (default `.`) for where the FTP
  account lands, and `FTP_VERIFY_CERT` (default `true`).
- Run it by hand with `dry_run` left ticked the first time. It lists the remote
  tree and transfers nothing, which is how to confirm `FTP_REMOTE_ROOT` before
  writing to a live site.
- Code only. `leo-app/data/content.json` and `config.php` are excluded, and
  neither mirror uses `--delete`.
- **The seeded `content.json` still has to be uploaded by hand once**, or the
  site goes live with the placeholder drafts.

### Confirmed remote layout (probed, not assumed)

The `leo@leofoundationusa.org` FTP account is **chrooted**: listing `..` returns
byte-identical output to `.`, so there is no directory above it. It lands on the
**document root**, which is why:

- `FTP_PUBLIC_DIR` is `.` — public files go straight into the document root.
- `FTP_APP_DIR` is `./leo-app/` — `leo-app` cannot be placed above the web root
  on this account, so it sits inside it. That is the fallback
  `public_html/index.php` explicitly tolerates, and `leo-app/.htaccess`
  (`Require all denied`) is then the **only** thing keeping `config.php` and the
  content store from being served over HTTP. If the VPS ever runs
  `AllowOverride None`, that protection silently stops working — re-check it
  after any server change.

An earlier manual extraction left junk in the document root that the deploy does
not remove (there is no `--delete`): a nested `public_html/`, plus `router.php`
(dev-only), `test/`, `README.md` and `leofoundationphp.zip`. Clear those by hand
before launch.

### Triggering a deploy

`mcp__github__actions_run_trigger` with `run_workflow` on `deploy.yml` works from
a session — the workflow can be dispatched even though secrets cannot be written
from here. Inputs: `dry_run` (defaults **true**; a manual run never writes unless
this is explicitly false) and `probe_path` (lists one remote path and stops,
transferring nothing — how the layout above was established).

`DEPLOY_ON_PUSH` is a repository variable, currently unset, so pushes do **not**
deploy. Leave it that way unless someone asks: a push then writes to a live site
with no confirmation step.

## Client context

Windows and Mac, no sudo on the Mac. Works through cPanel/WHM in Chrome.
Prefers being handed a file to double-click over a command to type. Their live
cPanel password appeared in a screenshot early on and should be rotated.
