# Session log — 18 September 2026, the community strip in place, and recipient rows

Head at the end: `06e990e`. Branch `claude/leo-foundation-site-redesign-knr6vv`,
PR #4 (draft). 90 node / 92 PHP tests.

Short session, four commits, two threads. `CLAUDE.md` holds the state; this is
the reasoning.

## What the client asked for

| # | Request | Commit |
| --- | --- | --- |
| 1 | Move the community logo scroller right under the last sentence of the same column | `e68ada4` |
| 2 | 50px beneath, and a bit larger | `add888d` |
| 3 | Larger still, and a slower scroll | `f550660` |
| 4 | On a scholarship's detail page, smaller recipient photos with the information horizontal beside them | `06e990e` |

## Decisions, and what was rejected

**`clear: right` was the wrong answer for the strip, and it was the first thing
tried.** The strip already carries `overflow: hidden` for the marquee, which
makes it a block formatting context — so it sits *beside* the floated card and
therefore directly under the copy. Adding `clear` drops it below the card and
opens a **352px gap**, measured, which was the exact gap the move was meant to
close. Note this is the opposite of what the page figure wanted a day earlier:
the figure is better below the card at one width than shrinking whenever it lands
level with it. The test now asserts the inline rule has no `clear`.

**Sitting in a column means sizing for the column, not the viewport.** The strip
is 728px beside the card against 1072px of column, so the full-bleed variant's
340px cap and 118px marks had to come down. The inline variant ended up with its
own breakpoint ladder rather than the band's tiers scaled — each step measured at
its own width for the largest mark that still leaves three on screen.

**Computed marks-per-screen is not measured marks-per-screen.** At 900px,
`100/135/30` computes to 3.08 marks in a 508px strip and renders two: the marks
are not all as wide as the cap, so where they land decides it. `96/126/28`
renders three. Two iterations, both measured.

**Scroll speed is not the duration.** The two strips have different track widths,
so the same `animation-duration` is a different speed. The slower scroll was set
by sampling the track's transform over four seconds — 56.5 px/s to 31.9, against
the homepage band's 65 — rather than by picking a number.

**The recipient card became a variant, not a rewrite.** It is shared by
`/recipients`, the homepage and a scholarship page; only the scholarship page
passes `cardLayout: 'row'`. The row arrangement is the one `.board-lead` already
uses for the chief executive: the portrait spans the left column across however
many rows the text produces, everything else pinned to column two.

## What the numbers were

- Community strip: 30px under the copy → 50px. Marks 94px → 112px → 128px, cap
  170 → 190 → 205. Scroll 56.5 px/s → 31.9. Three fully visible at every width
  from 1440 down to 600, two at 390.
- Scholarship recipients: portrait 646x808 → 140x175, card 688x1165 → 688x389,
  the four-card block **4,663px → 1,540px**. Then the portrait column 140 →
  **265px** and the picture to the text's full height, 0px difference on all
  four.

## Then: widen the portrait and match its height (`290f81e`)

> "make recipient column 125px wider and make pic height match the text"

Both halves were literal. 140 + 125 = **265px**, so the column is `265px
minmax(0, 1fr)` and the portrait `width: 265px`.

"Match the text" was the interesting one. The portrait already spanned the rows
with `align-self: stretch`, so its *box* was the full height of the text — and
the picture inside it was not. A replaced element keeps drawing at its own
aspect ratio however tall its box gets, so the image sat short in a stretched
box and looked exactly like the stretch had not worked. Three declarations make
the pair behave: `align-self: stretch` sizes the box, `height: 100%` makes the
image take it, `object-fit: cover` stops the crop distorting. Measured at **0px**
difference against the text on all four cards.

Widening the column then broke the stacking breakpoint, and the reason is worth
keeping. The rows sit in the scholarship page's `.split` column, so the viewport
is not the number that matters: the card measures **532px at a 900px viewport**
and **732px at 780px**, because the layout gives the track more of a narrower
page. `@media (max-width: 560px)` therefore stacked too early at one width and
too late at another. Moved to `container-type: inline-size` on `.recipient-rows`
with `@container (max-width: 600px)`. Row at 1440 / 1280 / 1120 / 1024 / 780,
stacked at 900 / 600 / 480 / 390.

## Then: the gold dots on the community strip (`b35c06b`)

> "remove gold dots from logo scroller on community partnerships page"

The inline strip renders **inside `.prose`** — that is the whole point of the
inline variant, it sits in the copy column under the last line. Which means it
inherits `.prose ul li::before`: a 6px gold dot at `--gold-bright`, absolutely
positioned, with 22px of indent. The strip is a `ul` of `li`, so all 26 marks
got one.

Two things about this that are easy to get wrong:

- `.logo-run` has carried `list-style: none` since it was written, and the dots
  appeared anyway. A generated pseudo-element is not a list marker; turning
  markers off does nothing to it.
- `.logo-run li::before` would not have fixed it either. `.prose ul li::before`
  is (0,1,3) and that is (0,1,2), so the *longer* selector loses.
  `.prose .logo-run li::before` is (0,2,2) and wins.

Verified by reading the computed `::before`, not the markup: before, 6x6,
`rgb(217, 164, 65)`, padding-left 22px; after, `content: none` on all **52**
items (26 logos, twice, for the seamless loop), padding 0, image flush with the
item's left edge. Then a plain `li` injected into the same `.prose` still drew
its 6px gold dot at 22px — so an admin's ordinary markdown list is untouched,
which is the thing a blunter fix would have broken.

Two assertions in the PHP suite, which loops over both sheets. Watched red with
the rule pulled ("the prose bullet is back on the inline strip logos") and green
with it back.

## Where to pick up

1. **Tests for the hero rail and the logo strip's behaviour.** The strip now has
   tests for its markup, its sharing and its CSS, but nothing exercises the
   rotation or the marquee. The hero rail still has nothing at all.
2. **The About figures** — still waiting on the client; see `CLAUDE.md` item 3.
3. **PR #4's body** is stale again, nineteen commits behind.
