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
  the four-card block **4,663px → 1,540px**.

## Where to pick up

1. **Tests for the hero rail and the logo strip's behaviour.** The strip now has
   tests for its markup, its sharing and its CSS, but nothing exercises the
   rotation or the marquee. The hero rail still has nothing at all.
2. **The About figures** — still waiting on the client; see `CLAUDE.md` item 3.
3. **PR #4's body** is stale again, seventeen commits behind.
