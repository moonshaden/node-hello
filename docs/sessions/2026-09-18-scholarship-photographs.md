# 2026-09-18 — the memorial scholarship photographs

Four commits, all one thread: get the five memorial scholarships' published
photographs onto the site, then move and size them the way the client wanted.

    b35c06b  Take the prose bullet off the inline logo strip
    2494295  Carry the five memorial scholarship photographs across
    2e10175  Stack the scholarship photographs in the right column under the card
    689dbfa  Centre the scholarship pictures and end the column level with the copy

## First: the gold dots (`b35c06b`)

> "remove gold dots from logo scroller on community partnerships page"

The inline logo strip renders **inside `.prose`** — that is the point of the
inline variant, it sits in the copy column under the last line. Which means it
inherits `.prose ul li::before`: a 6px gold dot at `--gold-bright`, absolutely
positioned, 22px of indent. The strip is a `ul` of `li`, so all 26 marks got one.

Two things worth keeping:

- `.logo-run` has carried `list-style: none` since it was written and the dots
  appeared anyway. A generated pseudo-element is not a list marker.
- `.logo-run li::before` would not have fixed it either. `.prose ul li::before` is
  (0,1,3) and that is (0,1,2) — the *longer* selector loses.
  `.prose .logo-run li::before` is (0,2,2) and wins.

Verified by reading the computed `::before`: before, 6x6, `rgb(217,164,65)`,
padding-left 22px; after, `content: none` on all 52 items, padding 0. Then a plain
`li` injected into the same `.prose` still drew its dot — so an admin's ordinary
markdown list is untouched, which a blunter fix would have broken.

## The photographs (`2494295`)

> "on the memorial scholarships.. 'Richard Mccurdy' , Leverne "Sharky" ', 'Joyce
> K. Smith' , 'Tiffany D. Mealman' , 'Evan C Gary', pull pics from
> leofoundationusa.org and add to build site"

All five live pages carry exactly one image, found through the WordPress REST API
(`/wp-json/wp/v2/pages/<id>`), each with the image directly under the page title
and above the body copy. None publishes alt text.

They are not one shape:

| | file | px | what it is |
| --- | --- | --- | --- |
| McCurdy | png | 490x300 | two colour photographs side by side |
| Baker | png | 2000x563 | the award's name in script type, then three photographs |
| Smith | png | 924x300 | three photographs on a tinted background |
| Mealman | jpg | 543x700 | a single portrait |
| Gary | jpg | 250x312 | a single black-and-white portrait |

Three carried an **alpha channel**, and JPEG has none, so every one was matted on
white before encoding — an unmatted transparent pixel encodes as black. 1.5 MB of
originals down to 248 KB.

**The Baker banner is the one crop that removed something.** The scholarship's
name is set into the artwork and the page already renders it as the `h1`, so it
was cropped to the three photographs — the same treatment the board portraits got.
Flagged to the client rather than done quietly.

### The treatment came from the live markup, not from taste

The five do not get one layout on the live site either: three carry Avada's
`alignleft` (the copy wraps beside them) and two `img-responsive` (they fill the
width). The split is by the image's own width — 463, 543 and 250px float; 924 and
980px fill. So that was the rule, at 560px. Reproducing the published split rather
than inventing one.

### What measuring caught, before it shipped

- **`width: 100%` upscaled the 463px McCurdy composite to 688px.** Capped at its
  own pixels instead. Nothing on this page is drawn past its natural size at any
  width.
- **The unfloat breakpoint was on the viewport, and this column's width does not
  track the viewport.** Measured down the sweep the copy column goes 688, 624,
  581, 532, 493, 432 as the split narrows — and then back up to **652** the moment
  the split collapses at 860px. So the viewport query unfloated the picture
  exactly where there was *most* room for it. Replaced with a named container
  query on the column.
- **The gold `.criteria` panel ran under the floated picture.** A float shortens
  line boxes, never block boxes. `flow-root`, as the jump index needed.

### And a defect that predated the change

The cross-build render diff came back with three scholarship pages differing.
`marked` autolinks a bare email address; the PHP `Markdown` has no autolink rule
and leaves it as text. Five pages carried `mwinney@leofoundationusa.org` in their
`criteria`, so **the deployed site had been showing an unclickable address** while
the dev twin showed a link, with both suites green.

The reason it survived is the more useful finding: **the diff set held one
scholarship page.** Five had never been diffed. Diffing one record of a
template-driven route proves the template, not the content. The set now holds all
eight.

## Into the right column, stacked (`2e10175`)

> "put pictures to the right of bio/info, under top right column"
> "stack if multiple pics"

The second line is what made the first one work. The pictures were going into a
344px column, where the Baker composite would draw each face about 40px across. So
the field became a **`photos` array** and the composites were split into their own
files — each then gets the full column width.

The split lines were measured, not guessed: the gap columns between the
photographs carry no ink at all, so each boundary is where the picture actually
ends. McCurdy gave 2, Baker gave 3. Every crop was looked at before it shipped.

**Smith did not split.** Its three photographs sit on a continuous tinted
background with no separable gap — a detail-variance probe found nothing usable —
so any boundary would have been a guess through someone's face. It ships whole and
stays small in the column. Said so plainly rather than shipping a bad crop.

Layout: `.split` is a two-track grid, so the card and the pictures needed one
wrapper. As a third grid child the pictures wrap onto a new row and land under the
*copy*, not under the card.

This also dropped the four flat photo fields from the `/admin` scholarship form,
one commit after adding them. An array does not fit a flat form, so it follows the
board roster and the programs list instead. Recorded as open work — it is the one
part of a scholarship the client can no longer edit.

## Centred, and ending level with the copy (`689dbfa`)

> "center pics in columns and on stacked pics adjust pic size so the bottom of the
> column the same as text column on left"

Centring was the small half. Ending level was not, and **the first attempt
silently did nothing while measuring as a perfect success.**

`align-items: stretch` makes the grid row as tall as its tallest item. A long
stack therefore grows the row, the copy column's *box* grows with it, and the two
boxes end level — while the text still ends hundreds of pixels early. On the Baker
page the stack overhung the text by 588px and the measurement said the gap was
**zero**, because it was measuring the stretched box.

Caught by re-measuring against `Math.max(...[...copy.children].map(e => e.getBoundingClientRect().bottom))`
— the last line of text — rather than the column.

The fix is to stop the pictures voting on the row's height: `.split-side` holds no
in-flow content, its inner is `position: absolute; inset: 0`, so the copy is the
only thing sizing the row and the inner is handed exactly that height. Gated to
the width where `.split` actually has two columns.

**It shrinks and never grows.** McCurdy needs 0.80 of natural height and Baker
0.49, so both come down to meet the copy. Smith would need 2.5x and Gary 1.64x —
upscaling photographs of real people to fill a gap — so those two stay their own
size and their columns end early. That is the honest outcome, and it was reported
as such rather than dressed up.

Two more things measuring turned up:

- the stack finished a **constant 18px below** the text at every width, which is
  the copy's last paragraph's `margin-bottom`. A small constant offset is always
  worth chasing.
- a short picture's **box was stretched** to the `min-height` floor while
  `object-fit: contain` kept the picture its own size inside it, so the column
  ended on empty space. A flex item stretches on the cross axis unless told not
  to.

Final measurement, five widths 1440 to 861, against the text: flush wherever the
stack can shrink, centred everywhere, never upscaled, aspect ratios intact.

## Process notes

- **The stale dev server bit again**, and the symptom was the documented one: the
  cross-build diff came back 19/19 differing. Two `node server.js` processes from
  an earlier restart still held :3000, so the fresh start had died with
  `EADDRINUSE` into a log nobody reads and the diff compared the *old* build. The
  kill loop in the notes finds them; it needs running until nothing is left.
- **A byte-check gate keyed on the stylesheet reported the new images as 404**
  mid-deploy. lftp mirrors alphabetically, `css/` precedes `img/`, so the
  stylesheet matched while the pictures were still uploading. Gate on the
  stylesheet *and* the assets *and* the page.
- **A negative CSS assertion matched its own subject.** `width: 100%` is a
  substring of `max-width: 100%`, so the test asserting the sheet has no
  `width: 100%` was satisfied by the `max-width: 100%` it was there to protect. A
  `(?<!max-)` lookbehind fixes it. Every new assertion was watched red against its
  own broken subject before being trusted — which is how this one was found.

## Where to pick up

1. **Tests for the hero rail and the logo strip's behaviour** — still the largest
   piece of debt that does not need the client. Nothing exercises the rotation or
   the marquee.
2. **The Smith composite** — only the client can supply the three originals.
3. **A repeating field for `photos` in `/admin`**, so the pictures are editable
   like the rest of a scholarship.
4. **PR #4's body** is stale — twenty-two commits behind as of this head. Offered
   twice, not yet taken up.
