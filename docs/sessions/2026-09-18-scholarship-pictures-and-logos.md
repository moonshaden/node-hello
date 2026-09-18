# 2026-09-18 — every scholarship picture, and the logos

Continues `2026-09-18-scholarship-photographs.md`, which covered the five
memorial awards. This one sweeps the rest and then reworks how the pictures sit.

    e76443a  Carry the remaining four scholarship images across
    3e2c84b  Stop a picture painting over the footer when the copy is short
    9524cd5  Let the GCU Guild logo fill its column
    7a1f9f0  Give the three LEO-branded awards the foundation's lion mark
    df4647c  Drop the navy plate behind the lion mark

## The sweep (`e76443a`)

> "add all pics and logos from scholarships on original site
> leofoundationusa.org to build same"

All thirteen published scholarship pages, not just the five already done. Four
more publish an image and **none of them is a photograph**: the GCU Guild logo
(142x80), the LEO Foundation wordmark on Christian Studies (700x144), the SKW
Play it Forward artwork (608x500) and the BHHS Legacy Foundation logo (482x134).
Four publish nothing at all, and a test now holds them empty so "all of them"
stays all of them and no more.

Three things about logos that photographs had not forced:

- **PNG, not JPEG.** Flat colour and sharp type, which JPEG rings around.
- **Cropped to the ink bounding box**, so the column width is the width of the
  mark. The LEO wordmark carried 24px of margin, BHHS 10px.
- **Matted on `#fdfcfa`, the band's own colour, not white.** Three carry an alpha
  channel, and a white matte behind a transparent logo shows as a faint box on
  the page. Sampled the actual computed background rather than assuming.

The sweep also found a flaw in the sizing rule from the previous session. On the
**SKW page** — 406px of copy against a 399px card, so minus thirteen pixels of
room — the tile was squeezed to the 110px floor and *still* overhung by 122px.
Small and overflowing, the worst of both. A lone picture now does not shrink:
shrinking earns its keep only when it achieves the fit, and a stack of two or
more is the case the shrink was asked for.

That also fixed a second thing: the floor was putting a 110px box around a
71px-tall logo, so the column ended 39px below the picture. Box slack went from
30/39/14 on the three logos to **zero** everywhere.

## The one that mattered: a picture on top of the footer (`3e2c84b`)

> "show me"

Showing the new pages is what found it. **The SKW tile was painting over the
footer.**

The mechanism is worth keeping. `.split-side` holds no in-flow content — its
inner is `position: absolute; inset: 0` — because that is what stops the pictures
sizing the grid row and lets them end level with the copy. The cost, not noticed
when that was written: **`section.band` cannot grow to contain them either.**
Anything that does not fit paints over whatever is below. On SKW the tile ran
231px past the band and landed on `.foot-grid`.

Two process notes:

- **An element screenshot did not show it.** `elementHandle.screenshot()` clips at
  the container, so the overflowing part was simply not in the picture and the
  page looked fine. `document.elementFromPoint` at the tile's own centre returned
  `.foot-grid`, which is what caught it.
- **It predated the previous commit.** With the shrink on, the tile squeezed to
  the floor and still overhung by 122px. Both settings collided; one was just
  less visible. The report that said the column "simply ends past the copy" was
  wrong, and saying so was part of the fix.

The answer is a reserve rather than a squeeze: `.split-side.has-photos` gets
`min-height: 619px` — card (399) plus gap (20) plus 200px of picture — so the row
is `max(copy, reserve)` and a column carrying pictures always has room. It
changes nothing on the eight pages whose copy is already taller. With the reserve
in place a lone picture can shrink again, which put Mealman back to flush.

The class comes from the template rather than `:has()`, so it does not depend on
selector support.

## The GCU Guild logo (`9524cd5`)

> "make gcu guild logo larger"

There is no larger file. The media API reports `full` at 146x91 with one 66x41
thumbnail, and a search of the whole library for "guild" and "gcu" returns
nothing else. So larger means upscaling, and filling the column is **2.4x** —
legible, and visibly soft. Rendered at 142 / 220 / 280 / 344 and looked at all
four before choosing.

Implemented as `fill` on the photo record rather than a change to the default, so
the rule stays "never past its own pixels" with one named exception. Two tests:
the opt-in has to actually enlarge the picture, and it has to be on exactly one
record — if that count grows, someone has reached for it to tidy a ragged column
instead of carrying a decision.

## The lion (`7a1f9f0`, then `df4647c`)

> "make the logo for these scholarships the lion head logo from footer"

The three LEO-branded awards carry no sponsor's logo, so they take the
foundation's own — the same mark the footer and masthead use. It also settled the
open question about Christian Studies, which had been given the LEO *wordmark*,
duplicating the header and the footer. That file is gone.

**And then the mistake.** The footer sits on navy and the mark is white and gold,
so it looked like it would vanish on the `#fdfcfa` band. A pixel count agreed:
42% of the mark composites to within 18/255 of the background. So it shipped
matted on the brand navy — the favicon treatment, which is an established pattern
here for exactly this reason.

> "remove the navy back"

The client was right and the metric was measuring the wrong thing. That 42% is
the lion's white **body**, which is drawn by its own grey shading and bounded by
the gold mane and arc. Composited on the band and looked at, it reads perfectly
well. **A contrast metric cannot see line work.** Rendering it and looking took
ten seconds and would have settled it before the plate was ever built.

It is now cropped to its ink and transparent, 391x378, one shared file.

## Process notes

- **The stale dev server bit twice more**, once reading 19/19 differ and once
  3/24. Both times an old `node server.js` still held :3000 and the fresh start
  had died with `EADDRINUSE`. The kill loop needs running until `/proc` shows
  nothing left — a single pass missed a survivor each time.
- **A broken grep looked like a broken build.** Checking the rendered `img` tag
  with `grep -o 'leo-lion-mark[^>]*'` returned no `width`/`height`, which read as
  a real divergence. grep is line-based and that tag spans two lines. Collapse
  whitespace first (`tr -s ' \\n' ' '`) before matching anything that can wrap.
- Every new assertion this session was watched red against its own broken
  subject: the reserve, the fill opt-in and its one-record cap, the lion on each
  of the three records, the lion file's existence, a logo removed from the store,
  and a picture added where the live site publishes none.

## Where to pick up

1. **Tests for the hero rail and the logo strip's behaviour** — still the largest
   piece of debt that does not need the client.
2. **Foundation Theatre and Foster Youth** have no picture. The lion is the
   obvious candidate; offered, not yet answered.
3. **A better GCU Guild file**, and **the three Smith originals** — both only the
   client can supply.
4. **A repeating field for `photos` in `/admin`**, so the pictures are editable
   like the rest of a scholarship.
5. **PR #4's body** is stale — twenty-seven commits behind at this head. Offered
   several times, not yet taken up.
