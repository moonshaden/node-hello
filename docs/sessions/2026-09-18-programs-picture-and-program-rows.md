# 2026-09-18 — the programs picture, and the program rows

Four commits: a picture the client handed over and the three program rows beside
it, then the impact figures under the copy on `/board`.

    cf60c7a  Close the programs copy with the quotation, and size each photo to its text
    29ba57b  Put the quotation under the copy, the way the community strip sits
    65275ab  Drop the words and end the photograph on the card's bottom
    9264af7  Carry the impact figures under the copy on the board page

The shape of the session is worth more than any one commit: the **same component
was built three times**, because each answer taught the client what they actually
wanted. Nothing here was wasted — each round was measured and shipped before the
next ask arrived — but it is the argument for showing a thing early rather than
polishing it.

The fourth commit is a different component in the same slot, and the thread runs
through all four: **under the copy, in the copy column, 50px down.** That slot
now holds an inline logo strip on `/community`, a photograph on `/programs` and
the impact panels on `/board`.

## What the picture actually was (`cf60c7a`)

> "use this picture under text on programs page and make the three boxes
> underneath photos the same height as text in box"

The file handed over was 1895x562 and looked like a banner. It is not: it is a
**screen capture of the live site's own testimonial band**, and it carries the
slider's three carousel dots at the bottom and a white strip under them.
Shipping it would have put that chrome on the page and baked the words into a
raster.

Found the parts instead, through the WordPress REST API. The band is
`fusion-builder-row-24` on every scholarship page; its `data-bg` is
`2024/07/991.jpg`, **1280x400 and 19KB**, no dots and no strip. The words are a
three-quote Avada testimonial rotator, and the one in the capture is the second
of them.

So the band was rebuilt: the live photograph, and the live quotation as **real
text** on the page record — editable in `/admin`, selectable, and readable to a
screen reader.

Two decisions that carry:

- **An `<img>`, not a `background-image`.** An img src goes through
  `asset_url()` and carries a content hash; a `url()` in the stylesheet does
  not, and images are served with a year's cache. That is exactly the hole that
  let a deleted navy plate sit in the client's browser for a week.
- **A scrim.** `object-fit: cover` on a 1280x400 picture in a 342px band shows
  the middle of it, which is the bright sunset, and white type over that was
  legible and weak. Rendered it and looked, rather than settling it with a
  contrast number — the last time a number decided this question it was
  measuring the wrong thing.

### The three program photographs

Each program's picture was a fixed square beside its text. Now it runs the full
height of that text: `align-self: stretch` gives the box the row's height,
`height: 100%` makes the picture take the box, `object-fit: cover` stops the
crop distorting it — the same trio the recipient rows needed — plus
`min-height: 0`, because a grid item's automatic minimum size transfers through
the intrinsic ratio and a square in a 300px column would otherwise floor every
row at 300px whatever the text does.

Measured at 0px top **and** bottom on all three rows at 1440 / 1200 / 1024 / 900
/ 760. Below 720px the photo stacks above the text, where there is no row to
fill, so the square and `height: auto` come back in that query.

**It has a visible cost and it was reported rather than glossed.** The Impact
Leadership picture has the word LEADERSHIP set into the artwork, and a 300x590
window over it cuts the word to "ADERSH". The live site publishes all three as
1200x1200 squares, so there is no wider source to crop from: it is the direct
price of equal heights. Offered three ways out (keep, widen the column, swap the
picture); still open.

## Into the copy column (`29ba57b`)

> "also place pic under script like the scroller on community partnership page"

The band moved out of its own full-width block and into `.prose`, 50px under the
last line — the placement the inline logo strip has on `/community`.

**Putting a component inside `.prose` hands it every rule `.prose` has.** That
is the gold-dot lesson again, and it bit again: `.prose blockquote` is a 3px gold
left bar, 18px of indent and a soft ink colour, which shoves a centred quotation
off centre. `.prose .page-quote blockquote` (0,2,1) is what outranks it (0,1,1);
verified by reading the computed border and padding, both 0 at every width.

No `clear`, for the reason already recorded against the strip: `overflow: hidden`
is already a block formatting context, so it sits beside the floated card, and
clearing it would drop it below the card and open the gap the move was closing.

Two things measuring settled that the eye got wrong:

- the caption **looked** top-heavy in the narrow column and was not — the space
  above the first line and below the attribution was equal to the pixel at
  1440 / 1024 / 900 / 700 / 390;
- the quotation wrapped to three lines with one word alone on the last, so the
  paragraph took `text-wrap: balance`.

## Picture only, ending on the card (`65275ab`)

> "use the last pic i put in with no text and crop to fit with card bottom"

The file sent back was **byte-identical** to the one already shipped (sha256
`bb7cb0a7a4dd…`), so this was about the treatment, not the file. Saying so took
one line and saved a round.

The words came off, and the component was renamed for what it now is:
`page-quote` → `page-picture`, and the record's `quote` lost `text` and `cite`.

### Making two content-driven edges meet

The picture's top is wherever the copy ends; the card's bottom is wherever its
own content ends. **A float's bottom is not something the flow below it can be
told about**, so no rule in the copy column can reach it. The fix is structural:
a page carrying a picture puts the card in a **grid column** instead of floating
it (`.page-flow.has-picture`), and the copy column becomes a flex column whose
last item takes the slack.

The copy does not move, and that is checkable rather than hoped for: a float
shortens the LINE boxes beside it, so this page's copy already wrapped at exactly
the grid column's width.

**`flex-basis: 0`, not `auto` — and `auto` was tried first.** A flex item's base
size counts toward its container's intrinsic height, so with `auto` the
picture's own 728x228 sized the grid row, the row outgrew the card, and the
picture ended **73px below** it. At zero it contributes only its floor, the card
wins the row, and the growth is exactly the space left. Same shape as the
scholarship column, where letting the pictures vote on the row height was also
the bug — that is twice now, in two different components, in one week.

**The card must not stretch.** `align-self: start`, or the row "ends level" by
growing the card, which measures as success. Also on record from the scholarship
column.

Three smaller things:

- **`.page-flow::after` is a clearfix, and in a grid it becomes a third grid
  item** taking a row of its own. `content: none` on the variant.
- **Margins do not collapse in a flex column.** The copy's last paragraph was
  adding its 18px on top of the picture's 50 and the gap read 68, against the
  inline strip's 50 — where ordinary block margins do collapse. Zeroed on the
  second-to-last child so the two components sit on the same gap.
- **The floor belongs in the two-column query.** At 390px a `min-height` on the
  component showed as 33px of the navy backing under a picture drawing at its own
  ratio. It is 100px, and it never decides the height on this page: swept every
  13px from 861 to 1500, the room between the copy and the card runs **117 to
  173**. It is there for a page whose copy outgrows the card.

Final measurement: the picture's bottom is level with the card's **to the pixel**
at 1440 / 1280 / 1200 / 1024 / 940 / 900 / 862, the gap is 50 at every one, and
nothing overlaps the card. Re-measured on the deployed page at 1280 and 900: 0px
both.

## The impact figures on /board (`9264af7`)

> "put this under script on board of directors page"

A screenshot of the homepage's four panels, and the same placement again: inside
the copy column, 50px under the last line. The screenshot pointed at the panels
rather than the band, so the page carries the panels only and the OUR IMPACT
eyebrow and heading stay on the homepage.

**One component, not a second copy.** The grid markup moved into
`impact-figures.ejs` / `.php`; both homepages and both page templates include it
and read the same `site.impact`. Worth being firm about, because the live
WordPress site is the cautionary tale: it states 5,685 / $6.9M on nine pages,
4,500 / $6M on `/financial-statements` and $8.9M raised on `/ways-to-give`. That
is what a second copy of the markup buys. A page opts in with
`impactFigures: true` on its record, exactly as `logoStrip` works -- it survives
an admin save because `applyFields()` spreads the existing record first, and no
form field edits it, so there is no silent-data-loss trap to open.

**Specificity, twice, in one component, and both failed silently.**
`.impact-inline` is (0,1,0) -- a TIE with the `.impact` rules it is undoing, so
source order decides and the later rule wins:

- the narrow-screen `.impact { padding: 44px 0 48px }` put the band's padding
  back above the panels below 861px. It showed as a 94px gap under the copy where
  every other width measured 50 -- and the first diagnosis of that number was
  wrong (a collapsed margin was blamed) until the element's own top was compared
  with the grid's and the 44px appeared between them.
- `.impact .value` held the numerals at the band's 51px inside a 157px panel,
  with `$6.9M` 6px past its own box. The overflow probe found it; reading the
  computed `font-size` is what explained it.

Every rule in the variant carries both classes now, and a test asserts that none
of them carries only one -- the regression is writing the obvious selector.

**The panel is the container, not the viewport.** Same lesson as the recipient
rows: this sits in a `.page-flow` column, so a panel is 181px at a 1280 viewport
and 155px at 862. The numerals size off `cqi` of the panel, with a plain `rem`
declared first so a browser without container units still gets something that
fits rather than falling back to the band's `clamp(2.5rem, 5vw, 3.5rem)`.

Measured at 1440 / 1280 / 1024 / 900 / 862 / 760 / 390: 50px under the copy at
every one, nothing overlapping the card, no panel or numeral overflowing -- four
across in the wide column, two by two in the narrow one. Re-measured on the
deployed page: 50px, four panels, no overflow.

## Process notes

- **A 24/24 cross-build "differ" was the stale dev server again, in a new
  guise.** Not the port this time: the node server's asset-version `Map` is read
  once at boot, so after editing the stylesheet it went on serving the OLD
  `?v=` hash while PHP computed the new one per request — every page differed on
  one attribute. Restarting node fixed it. **Restart the node twin after any
  asset change, not just after a route change.**
- **A `/proc` kill loop killed its own shell.** The case pattern matched
  `server.js` anywhere in the command line, and the invoking bash command
  contains the loop's own text — the same trap as `pkill -f`, which the notes
  already carry. Match on the executable (`readlink /proc/$p/exe`) as well as the
  command line, and skip `$$`.
- **Chromium cannot reach the build subdomain from this sandbox.** The egress
  proxy re-terminates TLS and Playwright's Chromium does not trust its CA
  (`ERR_CERT_AUTHORITY_INVALID`), with or without `--use-system-ca-store`.
  Disabling verification is not an option. What works, and is arguably better:
  `curl` the deployed page and every asset it references into a directory, serve
  that on a local port, and screenshot **that** — the picture is then rendered
  from the server's own bytes. Pull the two `url('../img/...')` lion watermarks
  by hand; they are in the stylesheet, not in any `src`.
- **One live comparison came back DIFFERS on `/community` with identical
  lengths**, and re-reading it byte for byte found no differing character: a
  race with the deploy still settling, which is the third time that pattern has
  shown up. Chase the actual bytes before reporting a divergence.
- Every assertion added this session was watched red against its own broken
  subject — twenty-odd of them across the three commits, including one that went
  green and had to be tightened (`str_contains($src, 'blockquote')` was satisfied
  by the closing tag after the opening one had been replaced).
- **A source-position assertion was deleted rather than fixed.** "The include
  sits before `.prose` closes" was written against a `</div>\n  </div>` marker,
  which is indentation, not structure. Replaced with a check on the **rendered**
  page: nothing that closes the column or opens a new one may stand between
  `class="prose"` and the figure.

## Where to pick up

1. **The Impact Leadership crop** — "ADERSH". The client's call; offered.
2. **Tests for the hero rail and the logo strip's behaviour** — still the
   largest piece of debt that does not need the client.
3. **Foundation Theatre and Foster Youth** have no picture. Offered three times
   now.
4. **The three Smith originals**, and **a repeating field for `photos` in
   `/admin`**.
5. **PR #4's body** is stale — thirty-six commits behind at this head. Offered
   several times, not yet taken up.
