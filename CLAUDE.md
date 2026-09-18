# LEO Foundation website

Replaces the WordPress site at leofoundationusa.org. No WordPress, no database,
no build step. Read this before changing anything — most of it is here because
something bit us.

Branch for all work: `claude/leo-foundation-site-redesign-knr6vv`. PR #3 is merged;
PR #4 (draft) is the open one.

## How to work on this — the standing rule

**Never guess. Verify. And never report something as working because it is the
answer someone wants.**

This is the client's own site, and a wrong name, a dead link or a broken page
reaches real students and real donors. So:

- **Exercise the thing, do not just load it.** "No errors" is not "it works". A
  preview was handed over as verified when its hero slider was completely dead:
  the page loaded, every image resolved, nothing threw — and the site's own
  `site.js` had simply not been included. A dead slider raises no error. The
  client spotted it, which is the wrong way to find out. Click the control, take
  the screenshot, assert the state changed.
- **Prove a fix fails without itself.** Before claiming a regression test covers
  something, revert the fix and watch the test go red, then restore it. That was
  done for the EJS escaping bug; do it every time.
- **Read the primary source.** Do not infer that a secret is set because a job
  was skipped, or that a page is current because the branch looks right. The
  preflight log said the FTP secrets were empty; the skipped job alone would
  have supported the opposite guess.
- **Never invent content.** Names, offices, figures and body copy are
  transcribed from the live site or they do not ship. If the live site does not
  publish it, say so and leave it out rather than composing something plausible
  — see *Content accuracy* and the About-page governance sentence in *Where this
  left off*.
- **Say what is actually true**, including when it is unwelcome: that a deploy
  cannot run, that a link is stale, that a check went red because of something
  you did. Report the failure with the evidence, not a reassuring summary.


## Where this left off — 2026-09-18, head `f8e924e`

Several sessions work this branch at once. Pull before starting, and expect the
head to have moved mid-task. PR #3 is **merged**; the open one is **PR #4**
(draft), which carries everything below and whose body is current as of this
head.

`docs/sessions/` holds a log per working session — what was asked, what was tried
and rejected, and what went wrong. This file is the state; those are the reasons.
The most recent is
`docs/sessions/2026-09-18-lion-link-and-back-to-top.md`; the four threads before
it in the same session are `2026-09-18-board-rings-and-footer.md`,
`2026-09-18-programs-cards-and-verse.md`, `2026-09-18-giving-callout.md` and
`2026-09-18-programs-picture-and-program-rows.md`.

**Landed and verified** (97 node / 100 PHP tests, PHP lint clean, cross-build
render diff zero on **twenty-four** paths — the eleven public pages plus **all
thirteen** scholarship detail pages — and every change byte-compared against the
deployed build subdomain):

- `/board`, `/programs`, `/community` — all three transcribed pages, shipped in
  #3. See *Content accuracy*.
- **The homepage hero is a student, not a carousel.** It centres on Keian's
  cutout (`site.heroStudentId` → a recipient's `cutoutUrl`) with a `heroQuote`
  that must stay a literal substring of the published bio. The slides are kept
  in the store and editable in `/admin` on purpose; the homepage simply stops
  rendering them. The `h1` is hidden, not deleted.
- **Two more awarded students rotate beside the lead.** `.hero-rail`,
  `data-hero-visible="2"`, `data-hero-interval="5000"`, cycling all 15 published
  recipients. One card fades at a time (the tick picks slot `tick % PER_VIEW`),
  600ms, opacity only; a forward cursor refuses any card already on screen so
  nobody is skipped. The line under each name comes from `heroLine()` /
  `Content::heroLine()` — a 40–150 character sentence lifted verbatim from that
  recipient's own published bio, quoted **only** when it is first person (7 of
  the 15 are). Nothing there is composed.
- **A 26-logo donor and partner strip** sits between the hero and the impact
  band. Pure CSS marquee, no JavaScript. See *Content accuracy* for where the
  files came from and why they carry no alt text.
- **The motion layer is gone.** Both systems — the scroll-reveal pass
  (`.is-deep` / `.is-risen`) and `body.is-staged` — were removed at the client's
  request, 1,070 lines out against 52 in. If you are reading an older note that
  says to check the staged sections reveal, they no longer exist.
- **Every stylesheet, script AND image URL carries a content hash**
  (`asset_url()` / `assetUrl()`, first ten hex of SHA-256), and **the HTML is
  served `no-cache, must-revalidate` by the app itself**. Both halves are needed
  and both were learned the hard way — see *Gotchas*. Without the first, a
  correct deploy lands and a returning visitor is still served the old file;
  without the second, the browser holds the page and goes on asking for the old
  hashes. The client reported "still showing old" twice while the server bytes
  were provably correct.
- **The ribbon and the masthead each hold one line** at every width, by dropping
  elements rather than wrapping. See *Gotchas*.
- **Page copy runs the full width** on the editable-page template, with the card
  floated into it (`.page-flow`). `.split` is still what the scholarship page
  uses.
- **The client's own artwork is now the site's identity.** The lion mark
  (`/img/brand/leo-mark-lion.png`) is in the masthead at 42px, in the footer at
  84px, and behind all five favicons — the favicons are matted on the brand
  navy, not transparent. It replaced the derived `leo-lockup-footer.png`, which
  is kept but no longer referenced.
- **The line-art lion is a trace on four surfaces**, at a deliberate scale:
  footer `.14` gold, impact band `.10` white, page heads `.08` white, light
  tinted bands `.05` gold. All four use `aspect-ratio: 448 / 520` and all four
  disappear under 900px. The light-band trace sits on the *left* so the traces
  do not stack down one edge.
- **About is the live WHO WE ARE and WHAT WE DO pages, combined and
  transcribed.** It replaced composed copy, and with it went both the
  untranscribed board-governance sentence and the "5,685 students to $6.9
  million" pair the client had picked — neither live page states a figure. See
  *Content accuracy*, and item 3 below.
- **`/privacy` is the live privacy policy, verbatim** — 1,030 words, diffed word
  for word against the live page. It is linked from the footer under Contact.
  **There is no terms of service to transcribe**; see *Content accuracy*.
- **The footer is a stacked sign-off over three spread columns.** The lion
  (128px) sits centred over the wordmark and **is a link home**, the strapline
  under it is type rather than baked into the raster, and the menus are sized to
  their content with the leftover width spread between the columns. **Every
  column ranges to the start of the row** and the two menus then take a **60px**
  drop together, so their headings line up with each other rather than each
  centring by its own height — and the sign-off does not drift when the taller
  menu outgrows it. The legal line reserves **66px** on its right (56 under
  560px) so the back-to-top control never lands on its sign-in link. Every
  number in there was measured; see *Gotchas* before changing one.
- **Every page carries a back-to-top control** on a document taller than the
  window — a fixed disc bottom-right that appears at whichever comes first of
  half a screen down or halfway down the page, because a single threshold is
  wrong at both ends. It ships `hidden` and only the script takes that off, so
  a page that cannot scroll has none and JavaScript off shows nothing. See
  *Gotchas* for the three traps in it.
- **The contact cards are sized from the email address**, which is the widest
  thing in them and has no space to break on.
- **The donor strip is one shared partial** with two variants. The homepage
  carries the full-bleed band at 58px; a page with `logoStrip: true` gets the
  large *inline* variant, which sits in the prose column 50px under the last line
  of copy at 128px, three marks on screen and half the band's scroll speed.
  `/community` is the one that uses it. See *Gotchas* — the inline one must NOT
  clear the floated card.
- **A scholarship's past recipients are horizontal rows.** The card is shared
  with `/recipients` and only the scholarship page passes `cardLayout: 'row'`,
  which drops the portrait from filling the column (646x808) to a **265px**
  column beside the text. The four-card block went 4,663px to 1,540px. The
  portrait runs the **full height of the text** — `align-self: stretch` plus
  `height: 100%` plus `object-fit: cover`, measured at 0px difference on all
  four cards. Stacking is keyed to the **list's own width**, not the viewport:
  `@container (max-width: 600px)` on `.recipient-rows`. See *Gotchas* for both.
- **A page can open its copy with a callout.** `notice` on the page record,
  `{ heading, body }`, rendered in the same `.notice` panel the scholarships
  page uses for its enrolment instructions -- white card, gold left bar -- as
  the FIRST thing in the copy column, above the jump index, which is what lines
  its top edge up with the top of the aside card (measured 0px). `/donate` is
  the one that carries it, and the three sentences in it were **moved** out of
  the copy rather than copied, so the page does not say them twice; a test
  asserts each appears exactly once. The head of a copy column is now 30px
  between the two boxes and 50px under the index -- and that second number is
  the shared `.page-index` rule, so `/faq` and `/privacy` moved with it.
- **The impact figures are one component in two places.** The four panels are
  `partials/impact-figures`, included by both homepages inside the navy band and
  by a page that sets `impactFigures: true` on its record -- `/board` is the one
  that does, at the client's word, under its copy and without the band's eyebrow
  and heading. Both read the same `site.impact`, so the numbers cannot drift the
  way the live WordPress site's do between its own pages. **Every rule in the
  `.impact-inline` variant carries two classes on purpose**; see *Gotchas*.
- **`/programs` closes its copy with a photograph carrying a verse.** The
  `picture` object on the page record holds `{ src, alt, width, height, text,
  cite }`, rendered inside the copy column 50px under the last line -- the
  placement the inline logo strip has on `/community`. The words are real text
  in a grid cell over the picture with a scrim between, never baked into the
  raster. It is **181px tall, which is the height of the impact panel block on
  `/board`** at the widths where those four panels sit on one row; the client
  asked for the two to match, so the number is measured from the thing it
  matches. It briefly ended level with the bottom of the aside card instead --
  `flex: 1 1 0` in the grid variant -- and that is one line away in the history
  of that rule if it is ever wanted back. The card is in a grid column rather
  than a float on a page with a picture (`.page-flow.has-picture`), which is
  what guarantees the copy column's width.
- **Each program shows enough copy to fill its card, then opens the rest behind
  "Read more".** Same `details` disclosure the recipient stories and board bios
  use. Two rules in the partial, both with a reason: the split is on PARAGRAPH
  boundaries, never a character count, because these bodies carry markdown links
  and a count cuts one in half; and paragraphs are added until the fill budget is
  PASSED rather than approached, or the Impact Leadership card stops at its
  174-character opener. Under 240 characters left over the whole body renders
  instead -- `/community` shares this partial for its one partner and had 116
  characters behind a toggle, which is more friction than the text it saves.
- **The three program photographs are square, which is the whole picture.** The
  live site publishes all three at 1200x1200. They ran the full height of their
  text for a few hours, and the crop cut the word LEADERSHIP -- set into the
  Impact Leadership artwork -- to "ADERSH". Two asks that cannot both hold; the
  later one won.
- **Twelve of the thirteen scholarships carry a picture**, in the right column
  under the award card, centred and stacked. `photos` is an array on the record,
  so an award with several pictures stacks them; two of the five memorial awards
  publish a composite that was split into its own files, because a three-up
  composite in this narrow a column draws each face about 40px across. Nine pictures
  come from the live site; the three LEO-branded awards carry the foundation's
  own lion instead. Only Foundation Theatre and Foster Youth have none. A stack
  ends level with the bottom of the copy — it shrinks to fit and never grows.
  The column is **413px** (the right grid track is `1.5fr`, widened from `2fr`
  because that track is what caps every picture) with a **50px** gap under the
  card. **Nothing on the site is drawn past its own pixels**; two logos that were
  are now client-supplied originals. See *Content accuracy* for where the files
  came from and *Gotchas* for the traps — the side column has put something on
  top of the footer twice.

**The debt this branch carries, stated plainly:** *nothing in either suite covers
the hero rail or the logo strip.* The rotation, the one-at-a-time slot logic, the
no-skip cursor and the `heroLine` first-person rule were all verified by
measurement in-session and on the deployed site, not by anything that will catch
a regression next month. They are the newest and most moving parts of the
homepage. If you are looking for the most valuable thing to do here that does not
need the client, it is this.

**Waiting on the client or the site owner — do not decide these unilaterally:**

1. **The giving links move to QuixChex.** Every giving link on the site still
   points at Aplos, and the client has said they are switching. These are live
   money paths that are known to be wrong — the highest-value open item.
2. **The 26 logos have no alt text.** Nothing the live site publishes names
   those organisations, so they ship decorative rather than being given invented
   names. The client is the only source.
3. **About no longer states the impact figures.** It is now the two live pages
   transcribed, and neither states a figure in its copy. The client had chosen
   "5,685 students to $6.9 million" for this page; that choice is reversed by the
   transcription, and the impact band is now the only place the numbers appear.
   Confirm that is what they want. (The untranscribed board-governance sentence
   that used to sit here went with the same rewrite.)
4. Two `/community` calls: the YouTube video is a link rather than an iframe,
   and two of the four event photographs are candid shots of unnamed people.
5. Whether `.05` on the light tinted bands is the right weight — the one trace
   value most likely to want tuning.
6. The supplied artwork appears nowhere on the live site, so confirm the client
   considers it current before cutover.
7. **The review site is indexable.** <https://build.leofoundationusa.org> serves
   no `robots.txt` and no `X-Robots-Tag`, so a public duplicate of the client's
   site can be crawled and a donor could land on it. It cannot be fixed by adding
   `robots.txt` to `php/public_html/` — that ships to production and would
   deindex the real site — so it needs to be host-conditional or placed on the
   subdomain by hand. See *Deploying*.
8. ~~The security headers reach static assets but not the PHP-generated HTML~~ —
   **done.** `.htaccess` `Header always set` does not reach PHP output on this
   host (measured: 1 header on `/css/site.css`, 0 on `/`), so the HTML now sends
   its own from `App::sendHeaders()`, along with the `no-cache` that makes the
   hashed asset URLs work. See *Gotchas*.

(The three FTP secrets and the first `seed_content` run were items 2 and 3 here
until 2026-08-31. Both are done — the secrets are set and the store has been
seeded on the build subdomain, including the logos. A further `seed_content` run
on a server whose content has been edited through `/admin` still overwrites those
edits, so still ask first.)

**Known and unfixed, deliberately:** the hero cutout is 607KB, the heaviest
asset on the site; on a 390px phone the masthead and ribbon take ~448px before
the hero begins (pre-existing — fixing it reworks the mobile header on all ten
pages); below the floated card on a wide screen a line of page copy runs ~128
characters, which is the direct cost of filling the width and is one `85ch` rule
away from being capped; and `7d5258d`'s own commit message garbles the footer
opacity change (it went `.25` → `.14`, and `.14` is not shared with the other
surfaces). The PR body carries the correct table; the message was not
force-pushed over an open PR.

**The build subdomain is the review surface now, not a static preview.**
<https://build.leofoundationusa.org> is deployed from this branch and can be
fetched from this sandbox over 443, so a change is confirmed by hashing the
deployed bytes against the local build — see *Deploying*. There is an older
artifact snapshot at
<https://claude.ai/code/artifact/b52ed21b-3e3d-4d72-83cc-b71302700dda>; **it is
stale** — it predates the motion removal, the hero rail, the logo strip and the
header work. Do not hand it to anyone as current. If it is ever rebuilt, note
that the scroll-staging it was told to verify no longer exists, and that there
are four `url('../img/...')` watermarks to resolve, not two.
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
npm test                                    # 97 tests
php php/test/run.php                        # 100 tests
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

**Green suites do not prove the two builds agree.** Both suites read the same
seed and the same source, so a divergence that only appears in rendered output
sails past them. A real one did: an EJS `<%=` interpolation building a `style`
attribute emitted `style=&#34;…&#34;`, a broken attribute, while PHP's `<?=`
emitted valid markup — so the rule applied on the deployed build and not on the
dev twin, with every test green. The check that catches this class is a
**cross-build render diff**: serve both builds, fetch each public path from each,
and diff. Normalise first or it drowns in false positives — strip `<!-- -->`
comments, and fold `&#39;` and `&#039;` together, since EJS and
`htmlspecialchars` encode an apostrophe differently and both render identically.
Worth running on any commit touching a view, a stylesheet or a content store.

**Interpolating an HTML attribute needs the raw form, not the escaping one.**
That is what bit above: `<%= %>` escapes the quotes it is supposed to emit. Use
`<%- %>` when the interpolation *builds* an attribute, and say why in a comment.

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

**A class with `display` beats the browser's own `[hidden]` rule.**
`.hero-rail-card { display: grid }` outranks the user-agent `[hidden] { display:
none }`, so the rail's hidden cards were all visible and the hero stood 3,107px
tall. `.hero-rail-card[hidden] { display: none }` is what makes `hidden` mean
anything here. Any component that sets `display` on a class and then toggles
`hidden` in script needs the same line.

**That warning has now been collected on.** `.to-top` (the back-to-top control)
sets `display: grid` and ships with the `hidden` attribute, so without
`.to-top[hidden] { display: none }` it is on screen on every page from the first
paint, before the script has judged whether the page even scrolls. Second
component, same line. Related, and the reason the control does NOT use `display`
for its own shown/hidden state: `visibility: hidden` takes an element out of the
tab order and the accessibility tree the way `display: none` does, and unlike
`display` it can be transitioned — so a fade needs no two-frame dance. The nav
dropdown is hidden the same way for the same focus reason.

**A float shortens line boxes, never block boxes.** The page card floats right
and the copy wraps beside it — but a block with a background (the `.page-index`
jump list) ran its full width straight *under* the card, 300px past its left
edge, because it had no right edge of its own. `display: flow-root` gives such an
element its own block formatting context so it sits beside the float instead,
with the float's own margin as the gap. Applies to anything boxed that lands
next to a float: panels, tables, headings with a rule.

**An EJS comment must not contain an EJS open tag.** Writing an output tag inside
`<%# … %>` makes the tokenizer read it as a real tag and the template stops
parsing — and the page then renders EJS's error text *as a page*, with status
200. A route check that only looks at the status code will not notice. The test
added for this looks at the body.

**`section.band.logo-strip`, not `.logo-strip`.** `section.band` is specificity
(0,1,1) and a bare class is (0,1,0), so a background set on the class alone loses
to the band it sits in. Several bands on this site are `section.band`.

**A stale dev server serves stale everything.** EJS re-reads its templates, but
route modules, and now the asset-version `Map`, are read once at boot. An old
`node server.js` left running on :3000 holds the port, so a fresh `npm start`
dies with `EADDRINUSE` into a log nobody reads and the diff you then run compares
the *old* build. It cost a false "10 of 10 pages differ" this session. Find it
with `/proc/*/cmdline` and kill by PID — `pkill -f` matches the invoking bash
command and kills the caller.

Two further faces of the same thing, both cost a session each:

- **The asset-version `Map` is the newest way it bites.** After editing the
  stylesheet, a node server left running goes on serving the OLD `?v=` hash while
  PHP computes the new one per request, so the cross-build diff comes back
  **24 of 24 differing** on one attribute. Restart the node twin after any asset
  change, not only after a route change. Gate on the hash: compare
  `sha256sum public/css/site.css` against what each build renders before
  believing a diff.
- **A stale `python3 -m http.server` does the same thing to a mirror.** One left
  holding the port meant a fresh one could not bind, every page under test was
  the 404 page, and the measurements were of nothing -- `.band` simply missing.
  And a mirror of a deployed page must be served AT THE ROOT: the pages use
  root-absolute `src`s, so mirroring two pages into `p/` and `b/` 404s every
  asset and renders both unstyled, with numbers that look real (`[1184, 18]`).
- **A `/proc` kill loop can kill its own shell.** Matching `server.js` anywhere
  in a command line matches the invoking bash command, which contains the loop's
  own text — the `pkill -f` trap in a new guise. Match on the executable
  (`readlink /proc/$p/exe`) as well, and skip `$$`.

**Size the header for headroom, not for a measured fit.** The client works
full-screen on a Mac, which renders these strings roughly 12% wider than this
sandbox's headless Chromium, and `.wrap` caps at 1120px so a wider screen never
buys room. The ribbon and masthead therefore drop elements at deliberately early
breakpoints (ribbon: org line 1040, pill 860, deadline sentence 680; masthead:
three tiers at ≥1101, 901–1100, ≤900) rather than at the width where *this*
browser starts to wrap. A `nowrap` that fits here will wrap there.

**A logo's file size is not its optical size.** The strip's images are cropped to
their own ink bounding box, so `height: 58px` is the height of the mark. Left
uncropped, a wide wordmark in a padded square (Nippon's is 200×34) renders ~32px
of visible ink next to a 58px roundel, and `object-fit: contain` with a
`max-width` makes it worse by letterboxing. Measure the ink, crop to it, then
size.

**Never put an image in a markdown body.** marked renders `![alt](src)` as an
`<img>`; the PHP `Markdown` has no image rule at all, so its link rule matches
the bracket pair and leaves the `!` in front of it. Checked, not assumed:

    node  <p><img src="/img/pages/about-students.jpg" alt="Six students"></p>
    php   <p>!<a href="/img/pages/about-students.jpg">Six students</a></p>

So the deployed build shows an exclamation mark and a link to a JPEG where the
dev twin shows a photograph, with both suites green — only the cross-build render
diff catches it. A test asserts no page body contains a markdown image, and page
bodies are editable in `/admin`, so that guard matters even with no images in
the seed today. **If a page ever needs a picture**, put it on the page record and
render it in `page.ejs` / `page.php`, the way the board roster and the programs
list already work — `271c13a` ("Carry the What We Do photograph onto the About
page") did exactly that, and the commit after it took it out again — a commit
cannot name its own hash, so look for the pair by message rather than by SHA.

**marked autolinks a bare email address and the PHP `Markdown` does not.** Same
class as the bare URL already on record, and it had been live: five scholarship
pages carried `mwinney@leofoundationusa.org` as plain text in their `criteria`, so
the dev twin rendered a `mailto:` link and **the deployed site rendered an
unclickable address**. Both suites green, because neither reads the rendered
output. Write it as an explicit `[address](mailto:address)` link in the store; a
test asserts no markdown field carries a bare one.

The reason it survived so long is worth more than the bug: **the cross-build diff
only covered one scholarship page.** Five pages had never been diffed at all. When
a route is a template with many records behind it, diffing one record proves the
template, not the content — the set now holds all eight scholarship pages, and
that is what found this.

**A block box in the prose runs under the floated card, and the right answer is
not always the same.** The float rule that bit the jump index bites anything
boxed, but what you want differs per element and all three cases are now in the
sheet:

- the **jump index** wants to sit beside the card → `display: flow-root`;
- a **photograph** wants one consistent width rather than shrinking whenever it
  lands level with the card → `clear: right`, measured on a 720px figure where
  the overlap showed at 1024px only;
- the **inline logo strip** wants to be directly under the last line of copy →
  nothing at all. Its own `overflow: hidden` already makes it a formatting
  context, so it sits beside the card by itself. Adding `clear` there put it
  below the card and opened a 352px gap — the gap the move was meant to close.
  A test asserts that rule has no `clear`.

**Putting a component inside `.prose` hands it every rule `.prose` has.** The
inline logo strip is a `ul` of `li`, and `.prose ul li::before` paints a 6px gold
dot and indents 22px — so every one of the 26 marks arrived with a bullet beside
it. Two traps in one:

- **`list-style: none` does not remove it.** The dot is a generated
  pseudo-element, not a list marker; the two have nothing to do with each other.
  `.logo-run` has carried `list-style: none` since the day it was written and the
  dots appeared anyway.
- **The override needs the specificity.** `.prose ul li::before` is (0,1,3), so
  `.logo-run li::before` at (0,1,2) loses. `.prose .logo-run li::before` is
  (0,2,2) and wins. Counting these matters more than it looks: the rule that
  reads more specific (three elements) is the one that loses.

The check is to read the *computed* `::before` on the items, not to look at the
markup — `content: none` on all 52 (26 logos, twice) is the proof, and injecting
a plain `li` into the same `.prose` afterwards is what proves ordinary markdown
lists still get their dot.

**A stretched grid item needs a real `height` as well.** `align-self: stretch`
gives the *box* the row's height; a replaced element like `img` keeps drawing at
its own ratio inside it, so the picture stayed short while its box grew. The pair
that works is `align-self: stretch` **and** `height: 100%` **and**
`object-fit: cover` — the first sizes the box, the second makes the image take
it, the third stops the crop distorting. Measured to 0px against the text on all
four recipient rows.

**Stack a component in a `.split` track by its own width, not the viewport's.**
The recipient rows live in the scholarship page's narrow column, so a viewport
media query reads the wrong number: the card is **532px at a 900px viewport** and
**732px at 780px**, because the layout changes what the track gets before the
viewport gets narrow. A `@media (max-width: 560px)` therefore stacked at the
wrong moments in both directions. `container-type: inline-size` on
`.recipient-rows` with `@container (max-width: 600px)` asks the question the
layout actually answers. Anything inside a `.split` or `.page-flow` column has
the same problem.

The same applies to type, not just to breakpoints. The impact panels in the
`/board` copy column are 181px at a 1280 viewport and 155px at 862, so a numeral
sized in `vw` overflowed its panel; sizing it in `cqi` of the panel (each panel
`container-type: inline-size`) asks the right question. Declare a plain `rem`
first -- a browser without container units drops the `cqi` declaration and would
otherwise fall back to whatever the component's other rule says, which here was
the band's much larger `clamp()`.

**`align-items: stretch` will not make two columns END together, and it measures
as though it did.** The ask was that a stack of pictures in the right column
finish level with the bottom of the copy on the left. Stretch makes the grid ROW
as tall as its tallest item — so a long stack grows the row, the copy column's
*box* grows with it, and the two boxes do end level while the TEXT still ends
hundreds of pixels early. On the Baker page the stack overhung the text by 588px
and the measurement reported the gap as **zero**, because what was measured was
the stretched box.

- **Measure the last line of text, not the column.** `copy.getBoundingClientRect()`
  is the box; `Math.max(...[...copy.children].map(e => e.getBoundingClientRect().bottom))`
  is the text. They are the same number until something stretches, which is
  exactly when you need them not to be.
- **The fix is to stop the pictures voting on the row's height** — but only the
  pictures. See the next note; taking the whole column out of flow took the award
  card with it and put that on the footer instead.
- **Shrink, never grow.** Flex shrink brings a too-tall stack down in proportion.
  Growing a short one means upscaling: Smith would need 2.5x and Gary 1.64x, on
  photographs of real people. Those columns end early instead, and that is the
  honest outcome rather than a bug to fix.
- A `min-height` floor stops a short copy column grinding three pictures into
  slivers.

**A one-class override only TIES with the rule it is undoing.** The inline
variant of the impact figures is `.impact.impact-inline`, not `.impact-inline`,
and both halves of that were learned by breaking: `.impact-inline` is (0,1,0),
the same weight as the `.impact` rules it overrides, so source order decided and
the later rule won. The narrow-screen `.impact { padding: 44px 0 48px }` put the
band's padding back above the panels below 861px (a 94px gap under the copy where
every other width measured 50), and `.impact .value` held the numerals at the
band's 51px in a 157px panel with `$6.9M` 6px outside its own box. Same family as
the `.prose ul li::before` gold dots, where the longer-looking selector was the
one that lost. Count the classes; a test asserts no rule in that variant carries
only one.

**Two content-driven edges cannot be made to meet across a float.** The ask on
`/programs` was that the picture closing the copy END level with the bottom of
the aside card. The picture's top is wherever the copy ends and the card's bottom
is wherever its own content ends, and **a float's bottom is not something the
flow below it can be told about** -- no rule in the copy column can reach it. The
answer is structural, and scoped to the pages that need it
(`.page-flow.has-picture`): the card takes a grid column instead of floating, and
the copy column becomes a flex column whose last item takes the slack. Nothing
about the copy moves, and that is checkable rather than hoped for -- a float
shortens the LINE boxes beside it, so the copy already wrapped at the grid
column's width. Four traps in that one variant:

- **`flex-basis: 0`, not `auto`.** A flex item's base size counts toward its
  container's intrinsic height, so with `auto` the picture's own 728x228 sized
  the grid row, the row outgrew the card, and the picture ended **73px below**
  it. At zero it contributes only its floor, the card wins the row, and the
  growth is exactly the space left. This is the same mistake as letting the
  scholarship pictures size their row -- twice in one week, in two components.
- **The card must not stretch.** `align-self: start`, or the row ends level by
  growing the card, which measures as success.
- **A clearfix `::after` becomes a grid item.** `.page-flow::after` exists to
  clear the float; in the grid it is a third item taking a row of its own.
  `content: none` on the variant.
- **Margins do not collapse in a flex column.** The copy's last paragraph added
  its 18px on top of the picture's 50 and the gap read 68 -- against the 50 the
  inline logo strip gets, where ordinary block margins do collapse. Zeroed on the
  second-to-last child.

A `min-height` floor on such a picture belongs in the two-column query, not on
the component: below the breakpoint there is no row to fill, and a floor there
shows as a strip of the backing colour under a picture drawing at its own ratio
(33px of navy at 390px wide).

**Anything out of flow in the scholarship side column will land on the footer,
and it has happened twice.** That column must not let the pictures size the grid
row (or they never end level with the copy), and the first two attempts bought
that by taking things out of flow — which means `section.band` cannot grow for
them, so whatever does not fit paints over what is below.

- **First it was a picture.** The SKW page carries 406px of copy against a 376px
  card, so there is no room: the tile ran 231px past the band onto `.foot-grid`.
- **Then it was the award card**, because the fix for the first one was a reserve
  that only applied to pages *with* pictures. Foster Youth has none and 240px of
  copy, so the card itself ran 71px past the band. Same footer.

**The shape that works** keeps the card in flow and takes out only the pictures:

    .split-side { display: grid; grid-template-rows: auto minmax(0, 1fr);
                  position: relative; }
    .scholarship-photos { position: absolute; grid-row: 2; inset: 0; }

An absolutely positioned grid child with a row placement gets that **grid area**
as its containing block, so `inset: 0` is exactly the space left under the card —
no measuring and no magic number. The card sits in `auto`, counts toward the row,
and the band always grows for it.

**`minmax(0, 1fr)` alone does NOT stop content sizing the row.** That was the
first version of this fix and it looked right: an `fr` track sizes to its
*content* whenever the container's own height is indefinite, which this column's
is until the grid row resolves. With the pictures merely in a `1fr` track the
Baker copy column went 994px to 1,640px — grown by the pictures it was supposed
to ignore — and every stack reverted to natural size. Out of flow is what
actually stops it.

**A closed `details` still reports a non-zero `getBoundingClientRect`.** Its
content is laid out and hidden with `content-visibility`, so measuring the rect
height of something inside a shut disclosure says it is visible when it is not --
the opposite of the truth. `checkVisibility()` is the honest signal (false shut,
true open), and the container's own height is the other one: the program cards
measure 342 shut and 564 open. Exercise the control and compare the two states;
do not ask the hidden element how tall it is.

**An element screenshot cannot show you an overflow.** It clips at the container,
so the overflowing part is simply absent and the page looks fine.
`document.elementFromPoint(x, y)` at the element's own edge is what catches it —
it returned `.foot-grid` both times.

A `min-height` reserve on the column (`.split-side.has-photos`) is still needed
for the case where the copy is shorter than the card plus a picture, because the
pictures' track contributes nothing: card + gap + 200px.

**Hashing an asset URL is only half of a cache fix; the HTML has to revalidate
too.** The css and js had carried a content hash for weeks and the client still
reported "still showing old" — twice, while the server bytes were provably
correct both times. Two separate holes:

- **Images were not versioned at all.** Every `<img src>` used `link_url()`,
  which only prefixes the base path. A picture replaced in place under the same
  filename is the same URL, and `.htaccess` gave images `max-age=604800`, so a
  browser that had the page held the old picture for a week. That is exactly how
  a navy plate survived being deleted. All fourteen image srcs in each build now
  go through `asset_url()` / `assetUrl()`, and images moved to a year.
- **The HTML carried no `Cache-Control` at all** — read from the live subdomain,
  the page sent nothing but `vary: Accept-Encoding`. A held page goes on asking
  for the *old* hashes however good the hashing is.

**`.htaccess` `Header always set` does not reach PHP output on this host.** Not a
guess: the same block puts `X-Frame-Options` on `/css/site.css` and gives `/`
none — 1 against 0, measured live. So the HTML's headers are sent from
`App::sendHeaders()` and a matching express middleware
(`no-cache, must-revalidate` plus the three security headers), where nothing can
drop them. That is also what finally put the security headers on the pages rather
than only on the static files.

Two guards, because the halves fail differently: the node suite reads the
**rendered** pages and asserts every `/img/` src carries `?v=` (a template can be
right while a route hands it an unversioned value) and that the response headers
are there; the PHP suite asserts no view builds an image src with `link_url`.

None of this reaches back into a browser that already holds the old page — one
reload does that. Say so, rather than letting someone conclude the deploy failed.

**A contrast metric cannot see line work.** The lion mark was matted on navy
because a white-and-gold mark looked like it would disappear on the `#fdfcfa`
band, and a pixel count backed it up: 42% of the mark composites to within 18/255
of the background. That 42% is the lion's white *body*, which is drawn by its grey
shading and bounded by the gold mane and arc — on the band it reads perfectly
well. The client asked for the plate to go and was right. Rendering it and looking
took ten seconds and would have settled it first.

**A flex item stretches on the cross axis, including a picture you sized
yourself.** `.scholarship-photo` is a flex row, so its `img` was stretched to the
figure's `min-height` floor while `object-fit: contain` kept the picture its own
size inside it — a box taller than its picture, so the column ended on empty
space. `align-items: center` on the figure is what stops it. Any time a rule sets
a height on a flex item's container and the child is a replaced element, check the
box and the picture separately.

**A trailing margin is enough to miss a measurement.** The stack finished a
constant 18px below the text, at every width, which is the copy's last paragraph's
`margin-bottom` — the column's box ends below its last line. Worth checking
whenever two things are meant to line up and are consistently out by a small
constant.

**Piping a test suite throws away its exit code.** `php php/test/run.php | tail -2
&& git commit` commits whatever the suite did, because `tail` exits 0. A commit
went out with a red test this way. Run the suite on its own, read the result,
then commit.

**Adjacent sibling margins collapse; a flex container does not stop that.** The
callout on `/donate` sits above the jump index, which carries its own
`margin-bottom` -- and the note written against it claimed the two would ADD, so
the gap would be 56px rather than 50. It is 50: sibling margins collapse to the
larger of the two. Being a flex container stops a box's margins collapsing with
its **children's**, and keeps it out from under a float (which is why `.notice`
needs no `flow-root` and no `clear` in the copy column, measured at seven widths).
It does nothing to its siblings. Measure the gap before writing the number down
-- a comment that reasons wrongly is worse than no comment, because it is
inherited with a confident tone.

**A CSS assertion must stay inside its rule.** Two tests here used `.*?` with the
`/s` flag to reach a declaration inside a block — which runs straight past the
closing brace and matches a rule further down the sheet, so the assertion passed
with its declaration deleted. Use `[^}]*?`, match the declaration rather than the
rule's exact one-line text (adding a property reformats the rule and turns an
exact match red on formatting, not behaviour), and watch every new assertion fail
before trusting it.

**But `[^}]*` cannot reach INTO a media block**, and the failure looks like a
regression. A declaration inside `@media (max-width: 560px)` sits past the
closing brace of whatever rule precedes it in that block, so `[^}]*` stops short
and the assertion goes red against correct CSS. A **bounded** `.{0,400}?` is the
shape that works: it crosses the one brace it has to and still cannot wander off
down the sheet. Same technique this suite already uses to step over a PHP
short-echo tag's closing bracket.

**And pin the VALUE, not just the property.** An assertion that `.page-notice`
has *a* `margin:` stayed green with the gap flipped from the bottom of the box to
the top -- the exact regression that rule exists to prevent. The mutation pass is
what found it. Pinning `margin: 0 0 30px` then earned its keep one commit later,
going red on a deliberate change instead of letting it through: a pinned
declaration is a change detector, not a correctness proof, and that is the point
of it.

**An exact class-attribute match in a test is a formatting assertion in
behavioural clothes.** Two assertions matched `class="page-picture"` and broke
the moment that figure gained `has-caption` -- on nothing being wrong. Match the
class prefix (`class="page-picture`), or a regex that tolerates more classes.
Same family as matching a CSS rule's exact one-line text, which turns red on
adding a property.

**A viewport-fixed element and a `.wrap`-bound one converge as the window
narrows, and they are furthest apart on the screen you are looking at.** The
back-to-top control is fixed to the viewport's right edge; the footer's "Staff
sign in" link floats to the right of `.wrap`, which caps at 1120px. At 1280 the
button's left edge is 1214 against a wrap ending at 1200 — clear, and the width
the change was designed at. At **1140, 1024, 900, 760, 560 and 390** they
coincide and the button sat squarely on the link, measured unclickable at every
one. `.foot-legal` reserves 66px on the right (56 under 560px) which clears it at
every width, because `.wrap` is itself inset 24px. Two other fixes were
considered and are wrong: hiding the control when the footer appears removes it
exactly when someone is at the bottom of a long page, and moving the link takes
it out of the corner it belongs in.

**A fragment link must NOT be base-path-prefixed.** `href="#top"` resolves
against the *current* URL, so it is the top of whatever page you are on. Writing
it the way every other link in the footer is written —
`href="<?= e($basePath) ?>#top"` — makes it the **homepage** plus a fragment
under the `/~leofoundationusa` temporary URL: a back-to-top control that
navigates away. `link_url()` and `asset_url()` are for paths; a bare fragment is
not a path. A test pins the bare form in both views.

**A computed count is not a rendered count.** Sizing the logo strip by arithmetic
— cap plus gap divides into the strip width — was wrong twice, because the marks
are not all as wide as their cap and where they land decides how many fit whole.
`100/135/30` computes to 3.08 marks in a 508px strip and renders two. Measure the
rendered boxes. The same applies to scroll speed: two strips with different track
widths move at different speeds on the same `animation-duration`, so sample the
track's transform over a few seconds rather than reading the duration.

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
- `build.leofoundationusa.org` on **443 is reachable**, so the deployed review
  site CAN be fetched and checked from here. That is new, and it is how the
  `.htaccess` redirect bug was found.
- `160.153.181.93` (the client's server) → still 403 `host_not_allowed`
- **ports 21, 990 and 22 → time out**, including against the build subdomain and
  with valid credentials in hand. Re-tested 2026-08-31: `curl` reaches
  `Trying 160.153.181.93:21...` and stalls at the TCP layer.
- port 2083 (cPanel) → connection reset even for allowed hosts
- `claude.ai` → 403

**Chromium cannot open the build subdomain from here**, so a screenshot of the
deployed site is not a page load. The proxy re-terminates TLS and Playwright's
Chromium does not trust its CA — `ERR_CERT_AUTHORITY_INVALID`, with or without
`--use-system-ca-store`, and disabling verification is not an option. What works,
and is arguably better evidence: `curl` the deployed page and every asset it
references into a directory, serve that on a local port, and screenshot **that**.
The picture is then rendered from the server's own bytes. Pull the two
`url('../img/...')` lion watermarks by hand — they are in the stylesheet, not in
any `src`, so a scrape of `src`/`href` misses them and they 404 into the shot.

So: **you still cannot upload from here, but you can now verify what was
uploaded.** Do not ask for FTP or cPanel credentials to upload with — the ports
are closed regardless of credentials, and being handed a password does not change
that. Uploading goes through the GitHub Actions workflow, which runs on a runner
with no such restriction; see *Deploying*. Checking the result is a plain
`curl` from here, and it is worth doing every time: the one bug that reached a
real server was invisible to every local check.

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

**The live donate processor is Aplos, not Mightycause — and the client is
moving off Aplos to QuixChex, so all of this is on notice; see *Open work* 6.** `site.donateUrl` pointed
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

**The photograph closing the Programs page is the live site's own**, taken on
2026-09-18. What the client handed over was a 1895x562 screen capture of the
testimonial band that sits on every live scholarship page — carousel dots, white
strip and all, with the words baked into the pixels. The parts are published
separately: the band is `fusion-builder-row-24`, its `data-bg` is
`2024/07/991.jpg` at **1280x400 and 19KB** (three crosses on a hillside at
sunrise), and the words are an Avada three-quote rotator. So the capture was not
shipped; the photograph was, rehosted as `/img/programs/quote-mlk.jpg`. The
filename records where it came from — the picture no longer carries a quotation.

- It rendered the live MLK quotation over the photograph for two commits, as
  real text on the page record rather than a raster. The client asked for the
  picture without the words, and then for a different overlay entirely.
- **It now carries Deuteronomy 32:2, and that copy is the client's own**, not the
  live site's: *"Let my teaching fall like rain and my words descend like dew,
  like showers on new grass, like abundant rain on tender plants."* It is the
  first line of copy on this site that is not transcribed from the live
  WordPress pages. That is the supported case and not an exception to the rule
  above — **nothing is composed here**; the client writing their own copy is the
  point. Transcribed exactly as supplied, stored on the record, editable in
  `/admin`. The MLK text is in the history of `page-picture.ejs` if it is ever
  wanted back, and the other two quotes that live band rotates were never
  carried.
- No alt text is published for it, so the alt was written here from the
  photograph, the same as every other rehosted image.

The **Community Partnerships page is the live `/community-partnerships/`
page**, transcribed on 2026-08-30. It is short: an intro, one partner —
**Alice Cooper's Solid Rock Teen Center** — and a five-image set (the partner's
logo plus four event photographs). Seeded with slug `community` and
`inNav: false`, linked from the Programs page body rather than added as an
eighth header item.

- Partners reuse the `program-section` partial, since a partner renders exactly
  as a program does (titled row, photo, markdown body). The page record carries
  `partners` and `gallery` arrays; both survive an admin save for the usual
  reason (`applyFields()` spreads the existing record first).
- It sits in the header **as a dropdown under About**, not as a top-level item.
  A page names its parent by slug in `navParent`, and `navPages()` /
  `Content::navPages()` turn the flat list into a one-level tree; the footer uses
  `navFlat()` so a nested page still appears there. A child whose parent is not
  itself in the nav falls back to the top level rather than disappearing, and a
  page naming itself as its parent is ignored — both are tested. `navParent` is
  a field in the page form, so the nesting is editable in `/admin`.
- The dropdown opens on hover **and on `:focus-within`**, so it is reachable by
  keyboard; the parent stays a real link so a touch tap still goes somewhere.
  The panel is hidden with `visibility`, not `display:none`, or the link inside
  could not take focus. Below 860px there is no hover and no room to float, so
  the child renders inline after its parent instead.
- Images are rehosted under `public/img/partners/` and
  `php/public_html/img/partners/` (2.55 MB → 0.68 MB). The logo is kept square
  at 600px; the photographs cap at 1200px. No alt text is published live, so
  the alt was written here from each image.
- **The YouTube embed is a link, not an iframe.** The live page gates the video
  (`videoid=l6u3SMJEF5A`) behind a privacy-consent placeholder; this build has
  no consent mechanism, so auto-embedding a third-party iframe would be a
  downgrade. The partner copy links to the video instead.
- **Not carried across:** the four-button nav strip (Avada chrome duplicating
  this site's nav) and the contact form, which needs a mail handler this build
  does not have.
- Two of the four event photographs are candid shots of identifiable people who
  are not named anywhere. They are carried as published; worth asking the client
  whether they want them kept.

**Nine scholarship pictures come from the live site**, taken on 2026-09-18 and
rehosted under `public/img/scholarships/` and `php/public_html/img/scholarships/`.
They reach the server through a `seed_content` run, like every other content
change. All thirteen published scholarship pages were swept, not just the five
memorial ones:

| pictures | pages |
| --- | --- |
| a photograph, or several | McCurdy (2), Baker (3), Smith, Mealman, Gary |
| a logo or award artwork | GCU Guild, SKW Play it Forward, BHHS Legacy |
| the foundation's own lion | LEO Foundation, Entrepreneurial, Christian Studies |
| none published, none invented | Foundation Theatre, Foster Youth |

The four logos are **PNG, not JPEG** — flat colour and sharp type, which JPEG
rings around — and each is cropped to its own ink bounding box, so the column
width is the width of the mark and not of a box with the mark inside it (the LEO
wordmark carried 24px of margin, BHHS 10px). They are matted on **`#fdfcfa`, the
band's own colour, not white**: three carry an alpha channel and a white matte
shows as a faint box on the page.

**The three LEO-branded awards carry the foundation's lion**, the same mark the
footer and the masthead use, cropped to its ink and transparent, one shared file
at `/img/scholarships/leo-lion-mark.png`. It replaced the LEO *wordmark* that
Christian Studies had been given, which duplicated the header and the footer.

- It was first matted on the brand navy, on the reasoning that a white-and-gold
  mark would vanish on the pale band — and a pixel count agreed, 42% of it
  composites to within 18/255 of the background. **The count was measuring the
  wrong thing.** That 42% is the lion's white *body*, which is drawn by its grey
  shading and bounded by the gold mane and arc; on the band it reads perfectly
  well. The client asked for the plate to go and was right. A contrast metric
  cannot see line work — render it and look.

**Two logos come from the client, not the live site.** The live site publishes
the GCU Guild mark at 146x91 and BHHS Legacy at 482x134 — nothing larger exists
in its media library, checked against the media API's registered sizes and a
search of the whole library. At those sizes they sat much smaller than every
other mark, and drawing one 2.4x past its own pixels (a `fill` opt-in that
existed for exactly one commit) was visibly soft. **The client supplied
1672x941 and 2000x668 originals**, which is the fix that is not soft, and the
exception went with them. If a picture ever looks too small here again, ask for
a bigger file rather than reinstating the flag.

**No pngquant, optipng or PIL in this sandbox**, and a straight canvas PNG of a
two-colour logo is enormous — the GCU one came out at 199KB against 20–67KB for
the others. Snapping each channel onto a **24-step ramp** takes it to 78KB with
no visible change: the artwork is flat colour plus antialiasing, so most of those
distinct values were noise the encoder had to store. Compare the two by eye
before shipping one.

**Nothing on the site is drawn past its own pixels.** A test asserts the absence
of the opt-in rather than policing a single allowed case.

The record holds a **`photos` array**, each entry `{ src, alt, width, height }`.
An array because two of the five publish a *composite* rather than a single
picture, and a composite is useless at this size: the pictures sit in a narrow
column — 344px when they were first cut, 413px now — where a three-up composite
draws each face about 40px across. Split into their own files they stack one per
row at the column's full width.

- **McCurdy** splits into 2, **Baker** into 3. The split lines are *measured*, not
  guessed — the gap columns between the photographs carry no ink at all, so each
  boundary is where the picture actually ends. Every crop was looked at before it
  shipped.
- **Smith does not split** and ships whole. Its three photographs sit on a
  continuous tinted background with no separable gap, so any boundary would be a
  guess through someone's face. It is the one picture that stays small in the
  column. If the client wants it split they have to supply the originals.
- **Mealman and Gary** are single portraits and are carried whole.
- The **Baker banner is the one crop that removes something**: the scholarship's
  name is set into the artwork, and the page already renders it as the `h1`.
  Cropped to the three photographs, the same way the board portraits were cropped
  to their ring. Worth confirming with the client.
- Three of the files carried an **alpha channel**, and JPEG has none, so each was
  matted on white first — an unmatted transparent pixel encodes as black.
- **No alt text is published live for any of them**, so it was written here from
  the photograph: the two single portraits take the person's name, the composites'
  parts describe what is shown without naming who is who. The client is the only
  source for anything more, the same as the 26 strip logos.
- **`width` and `height` are stored** because they become the attributes that
  reserve the space before the picture loads, and because nothing here is ever
  drawn past its own pixels. The narrowest is 227px against a 413px column, so
  several render short of the column rather than filling it — that is the honest
  size of the source, not a layout bug.
- **The array is not editable in `/admin`.** It follows the board roster and the
  programs list — it lives on the record and survives a save because
  `applyFields()` spreads the existing record first, but nothing in the
  scholarship form edits it. The four flat photo fields that existed for one
  commit were dropped when the shape became an array. Open item if the client
  wants to change these themselves.

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

**The rings are recoloured, and that is the one edit to a board photograph.**
All six published as rgb(224, 208, 112), a pale yellow that matched nothing else
on the page; they are `--gold`, rgb(184, 134, 43), at the client's ask. The
photograph inside is untouched — verified numerically, not by eye: inside the
circle the mean difference is 0.35–1.36 per channel and the worst single pixel
4–12, which is JPEG re-encode noise, against 5.6–15.6 in the ring band itself.

How it was done matters, because the two obvious ways are both wrong:

- **A colour key alone recolours faces.** "Yellowish" also matches skin and
  blonde hair; it tinted part of Michele's face and a patch of Madeline's hair.
- **A radius mask works on four of six.** Jennifer's ring is not concentric with
  her crop, so her radial profile never exceeds 59% ring at any radius. Fitting
  the band per portrait then picked 0.203–0.968 on one and -0.002–1.004 on
  another, because bins near the centre hold a handful of pixels and one stray
  reads as 100%; requiring a minimum bin population then picked the longest run,
  which was across the middle of two faces.
- **What works is connected components.** Match the ring's measured colour, group
  matching pixels into connected regions, and keep only the region that SPANS
  the frame — that is the ring by definition, being the only thing that goes all
  the way round. It spans 0.96–1.00 of the frame on every portrait while the next
  largest match spans at most 0.20.

The antialiasing survives because the ring is drawn over white: each pixel is
`alpha*ring + (1-alpha)*white`, and blue carries the most contrast (112 against
255) so alpha recovers exactly and the edge recomposes in the new colour rather
than leaving a hard collar.

**Every image ships twice, once to each build, copied by hand — and nothing
caught a slip.** The suites read the store and the source, the lint reads PHP,
and the cross-build render diff compares MARKUP: both builds reference the same
`/img/<path>`, so identical markup proves nothing about the bytes behind it. The
PHP suite now walks both image trees and asserts every file is byte-identical,
with neither build holding a file the other lacks.

**Recipient photos are served from this repo.** All 15 are committed under
`public/img/recipients/` and `php/public_html/img/recipients/`, downscaled to
800px wide and re-encoded as JPEG (5.4 MB → 1.0 MB). `photoUrl` is an
app-absolute `/img/recipients/<slug>.jpg`, which `link_url()` prefixes with the
base path so it survives the `/~leofoundationusa` temporary URL. `.cpanel.yml`
copies the directory on deploy — if you add a photo, check it still does.
Nothing on the site loads an image from the WordPress host any more.

**The `slides` array was seeded from the live hero on 2026-08-29.** The
homepage no longer renders it — the hero centres on a student instead — but the
slides stay in the store and stay editable in `/admin`, and the tests below
still hold. The live homepage publishes the same three-item hero *twice*, the
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

The **About page is the live `/who-we-are-2/` and `/what-we-do/` pages
combined**, transcribed on 2026-09-17. Between them the two pages publish seven
sentences of body copy and nothing else; everything around them is Avada chrome.
Each shipped sentence was checked to appear verbatim in the live markup.

- `/who-we-are-2/` contributes one sentence, its own `h1`: the mission
  statement. It is the page's lede here for the same reason. Note it publishes
  "Leo Foundation's", not "LEO" -- carried as published.
- `/what-we-do/` contributes its `h1` (with *formerly known as Grand Canyon
  University Scholarship Foundation* in italics, as published -- it is emphasis,
  not a link) and the paragraph under it.
- The "LEO Foundation welcomes you..." line is on **both** pages, as the band
  above the contact form. It ships once.
- **Not carried across:** the nav strips on both pages (Avada chrome duplicating
  this site's own header); the contact form, which needs a mail handler this
  build does not have; and `/who-we-are-2/`'s counters, whose `data-value`
  attributes read 20 / 8500000 / 5685 / 6900000 -- the same four numbers the
  impact band already renders.
- `/what-we-do/`'s one photograph (`happy-students-walking-in-university.jpg`,
  1400x825, no alt text published) is **not** carried. It was added and then
  taken back out at the client's word — "forget it, dont use picture on about
  page". The page is copy only.
- The one line that is not transcribed is the navigation link to `/board`. The
  live page offers the same destination as a LEO LEADERSHIP button in the nav
  strip above, and `/board` is `inNav: false`, so without it the page is
  reachable only from Contact.

**This replaced composed copy, and took two things with it.** The old About body
was largely written here rather than transcribed. Gone with it: the governance
sentence that claimed the board "set the scholarship criteria ... and select
each year's recipients", which was never transcribed and contradicted the live
charter -- that open item is closed by deletion. Also gone: "5,685 students to
$6.9 million", which the **client chose** for this page. Neither live page
states a figure in its copy, so the transcription carries none and the impact
band is now the only place on the site that states them. If the client wants
them back on About, that is a line of copy they have to supply.

**The 26 strip logos were taken from the live WordPress site** on 2026-09-16 and
are committed under `public/img/logos/` and `php/public_html/img/logos/` (412KB
for all 26). They are stored as a `logos` collection — which had to be added to
`COLLECTIONS` in **both** stores, or `/admin` answers 500 with `unknown
collection: logos` — and they reach the server only through a `seed_content` run,
never through a code deploy.

Each file is cropped to its own **ink bounding box**, measured from the alpha
channel, so the CSS `height: 58px` is the height of the mark rather than the
height of a square with a mark somewhere inside it. That crop is what makes them
frame consistently; see the matching note in *Gotchas*.

**No alt text, deliberately.** The live media library publishes none, and nothing
on the live site names those 26 organisations — so naming them here would be
composing content, which this project does not do. They ship decorative and the
client is the only source. This is an open item.

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
5. ~~The About figures disagree with the impact band~~ — **done.** The client
   picked 5,685 students / $6.9M, and About now says exactly that. Keep the
   underlying finding on record, because the live site still disagrees with
   itself and it will come up again at cutover: its homepage prose reads "over
   4,500 youth to over $6M"; its counter widgets (on nine inner pages) read
   5,685 / $6.9M awarded / $8.5M raised — except `/ways-to-give` ($8.9M raised)
   and `/financial-statements` (4,500 / $6M / $8M, evidently stale). The
   "3,000 / $5 million" wording this site used to carry appears nowhere on the
   live site at all.
6. **The giving links move to QuixChex.** Every giving link here points at
   `aplos.com/aws/give/LEOFoundation/{Donation,donate-now,fieldorstudy,donate}`,
   transcribed as published — and the client has since said they are switching
   processors. So these are live money paths that are *known* to be going
   stale, not merely unverified. Get the QuixChex URLs from the client, replace
   all four, and have them click each before cutover. Highest-value open item.
7. **One homepage slide still links nowhere.** `/programs-partnerships/` is
   done — transcribed, seeded as `/programs`, and that slide's `ctaUrl` points
   at it. `/mission-moments-newsletter/` is still untranscribed, so its slide's
   `ctaUrl` stays empty. Not user-visible today (the homepage renders a student
   rather than the slides) but the slides are still live in `/admin`, so seed
   the newsletter page the way the others were seeded, then fill the slide in.
8. Old-URL redirect map verification before cutover. The scholarship slugs now
   match the WordPress ones, so most of the map should be one-to-one.
9. ~~No CI~~ — **done.** `.github/workflows/deploy.yml` runs both suites and the
   PHP lint on every push, which catches the seed-drift class of bug.
10. **The hero rail and the logo strip have no tests.** Nothing in either suite
    covers the rotation, the one-at-a-time slot logic, the no-skip cursor, the
    `heroLine` first-person rule, or the strip at all. They were verified by
    measurement in-session and on the deployed site, which catches nothing next
    month. The most valuable item here that does not need the client.
11. **The 26 strip logos have no alt text**, because nothing published names
    those organisations. See *Content accuracy*.
12. **The page and scholarship extras are not editable in `/admin`.** They all
    live on the record and survive a save because `applyFields()` spreads the
    existing record first, but no form field touches any of them: a
    scholarship's `photos`, and on a page `members`, `programs`, `partners`,
    `gallery`, `logoStrip`, `impactFigures`, `picture` and `notice`. That is a
    pile now, and everything else on both records IS editable. Needs a
    repeating field — worth doing if the client wants to change a picture or a
    callout themselves.
13. **The Smith photograph is one composite that could not be split**, so it
    renders small in the column while the others fill it. Only the client can
    close this, by supplying the three originals. See *Content accuracy*.
14. ~~The GCU Guild logo is upscaled and soft~~ — **done.** The client supplied
    a 1672x941 original, and a 2000x668 BHHS one with it. Nothing on the site is
    upscaled now and the `fill` opt-in is gone.
15. **Foundation Theatre and Foster Youth carry no picture.** The live site
    publishes none and none was invented. They are the other two LEO awards
    without a sponsor's logo, so the lion is the obvious candidate — the client's
    call, and offered.
16. ~~The Impact Leadership photograph's own word is cropped~~ — **done.** The
    three program photographs are square again, which is the whole picture, so
    "ADERSH" reads as LEADERSHIP. It cost the equal-height treatment those rows
    had for a few hours; the client asked for the full picture and the two
    cannot both hold.
17. **"Programs & Partnerships" carries no partnerships.** The one partner the
    site publishes — Alice Cooper's Solid Rock Teen Center — is on `/community`,
    reachable from a sentence of body copy. The live WordPress site splits them
    the same way, so this is not a transcription error; the title simply
    promises something the page does not deliver. Raised with the client, not
    yet answered.
18. **The Baker banner crop drops the award's name** from the artwork, because
    the page already renders it as the `h1`. Worth confirming with the client.

## Deploying

**Deploying works, and there is now a review site: <https://build.leofoundationusa.org>.**
The three FTP secrets were added to `moonshaden/node-hello` on 2026-08-31 and the
first real deploy went out that evening. The subdomain is a staging copy — the
production domain is still the old WordPress host; see *DNS* below.

**The deploy is TWO runs, not one.** A code deploy alone gives you a hollow site:
`leo-app/data/` is excluded from every ordinary deploy, so the server has no
content store and every page but `/`, `/scholarships` and `/recipients` returns
404 — the homepage renders at 5KB instead of 33KB and the scholarships page reads
"0 of 13". That is not a bug; the exclusion is deliberate, because `/admin` owns
that file on a live server. Seeding is the separate `seed_content: true` run,
which uploads only `content.json` and stops. On a *fresh* target there is nothing
to lose, so run it. On a server whose content has been edited through `/admin`, it
**overwrites their edits** — ask first.

So, from cold:

1. `dry_run: true, probe_path: '.'` — lists the remote root, transfers nothing.
2. `dry_run: true` — lists every file that would move.
3. `dry_run: false` — the code deploy.
4. `dry_run: false, seed_content: true` — the content store.

**Two runs means two runs, one after the other — never back to back.** The
workflow sets `concurrency: deploy-<ref>` with `cancel-in-progress: false`, which
queues rather than cancels — but GitHub keeps only **one pending run per group**,
so dispatching the second while the first is still pending **cancels the first**.
Firing the code deploy and the `seed_content` run seconds apart therefore ships
the content and silently drops the code. It happened twice in one session (runs
119/120 and 123/124: the code deploy cancelled, the seed succeeded), and the
symptom is a server whose content store has a field the deployed templates do not
render yet. Nothing breaks — an unknown field is ignored — so only a byte check
catches it. Wait for the first run to finish before dispatching the second.

**Gate on something the change actually moves.** The byte check below hashes
`site.css`, which is useless for a commit that does not touch the stylesheet: a
views-and-content change had the gate green before the deploy started. The
subtler version of the same mistake: gating on text that the OLD build also
shipped. A commit that moved a paragraph from the hidden half of a disclosure to
the shown half was gated on "is the paragraph on the live page" -- it was, all
along. The signal has to distinguish the two builds, here the paragraph's
position relative to `class="story-rest"`. Pick the
signal from the diff -- for that one it was the callout vanishing from the live
page (the new template reading a field the not-yet-seeded store did not have) and
coming back after the `seed_content` run. Note that a store whose SHAPE changed
renders nothing in between the two runs.

**Confirm a deploy by bytes, and only then say it is live.** A 200 proves the
server answered, not that it answered with *this* build, and this session burned
three rounds of the client reporting something "still wrong" when the real answer
was that the deploy had not finished. The check is short: hash the local
`php/public_html/css/site.css`, poll `https://build.leofoundationusa.org/css/site.css`
until it matches, then run the PHP dev server and compare each route's HTML
against the live one. Identical hashes or it is not live.

    sha256sum php/public_html/css/site.css | cut -c1-16
    curl -s https://build.leofoundationusa.org/css/site.css | sha256sum | cut -c1-16

A deploy takes roughly 90 seconds to show up. Poll it; do not assume it.

**Confirmed layout for the build subdomain** (probed on 2026-08-31, run
`33445026091`). The `leoftp@build.leofoundationusa.org` account lands directly on
the subdomain's document root, so the defaults are already right:
`FTP_PUBLIC_DIR` `./`, `FTP_APP_DIR` `./leo-app/`, `FTP_SERVER`
`build.leofoundationusa.org`, `FTP_VERIFY_CERT` `false`. The docroot arrived with
cPanel's own `.htaccess`, `.user.ini` and `php.ini`; the deploy overwrites
`.htaccess` and that did **not** break the PHP handler.

**`.htaccess` redirect targets must start with `/`.** All three old-WordPress
redirects were broken on the real server and nowhere else:

    /scholarship-faqs -> https://<host>/home/<account>/public_html/faq

In a `.htaccess`, an external redirect whose substitution is relative resolves
against the **filesystem**, not the URL space — a dead link that also leaked the
server path. Fixed in `5c5013b` with root-relative targets. **Neither dev build
reads `.htaccess`** (the Node twin routes itself, the PHP dev server uses
`router.php`), so both suites and the cross-build render diff stay green whether
this is right or wrong. Only the review site catches this class. Re-test the
three legacy URLs on the server after any `.htaccess` change.

**Verified on the live subdomain after deploying:** all ten routes 200 as
distinct pages, all 38 referenced assets 200, the three legacy redirects landing
on real pages, and `leo-app/` sealed — `config.php`, `boot.php` and
`content.json` all 404 over HTTP, so `Require all denied` is working on this
host. `/admin` is reachable but locked: no `config.php` ships, so the password is
empty, `Auth::isConfigured()` is false and every login is refused.

**Open on the review site:** it is fully indexable — no `robots.txt`, no
`X-Robots-Tag`. A public duplicate of the client's site can be crawled and donors
could land on it. A `robots.txt` cannot simply be added to `php/public_html/`,
because that ships to production too and would deindex the real site; it needs to
be host-conditional or dropped on the subdomain by hand.

(The security-header half of this note is closed. `.htaccess` `Header always set`
does not reach PHP output on this host — `X-Frame-Options` was on
`/css/site.css` and absent on `/` from the same block — so the HTML sends its own
from `App::sendHeaders()`, together with the `Cache-Control: no-cache` that makes
the hashed asset URLs actually reach a returning visitor.)

Note the account name in the leaked path was `buildleofoundati`, i.e. the
subdomain has its own cPanel-style account, separate from `leofoundationusa`.

The rest of this section was written when a deploy was expected to work, and its
mechanics are still accurate:

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
- The seeded `content.json` reaches the server through the separate
  `seed_content: true` run, not by hand. It has been run on the build subdomain,
  so that target is populated; a **new** target still needs it or the site goes
  live with the placeholder drafts.

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
