# 2026-09-18 — the board portraits' ring, and the footer columns

Fourth thread of the same working session, after
`2026-09-18-programs-cards-and-verse.md`.

    7245fad  Recolour the board portraits' ring to the site gold
    5aaff86  Start the two footer menus on one line as each other
    af44f8b  Drop the footer menus 100px, leaving the sign-off where it is
    59d774b  Set the footer menus' drop to 60px

## Recolouring a ring without touching the photograph (`7245fad`)

> "the current pictures have a yellow circle, can we change that color to match
> the gold in site"

All six rings were rgb(224, 208, 112), measured — a pale yellow sitting beside
the gold office labels, the gold pill and the gold "Read the full bio" links
without matching any of them. They are `--gold`, rgb(184, 134, 43), now.

**It took three attempts, and the first two are the lesson.**

1. **A colour key alone recolours faces.** "Yellowish" — r and g high, b lower —
   also matches skin and blonde hair. It tinted part of Michele's face and a
   patch of Madeline's hair, both visible in the render.
2. **A radius mask works on four of six.** Jennifer's ring is not concentric with
   her crop: her radial profile never exceeds 59% ring at any radius, where
   Greg's is a clean 100% plateau from 0.945 to 1.0. Fitting the band per
   portrait then picked **0.203–0.968** on one and **-0.002–1.004** on another,
   because bins near the centre hold a handful of pixels and one stray reads as
   100%. Requiring a minimum bin population fixed that and then picked the wrong
   run — the longest, which was across the middle of two faces.
3. **What works is connected regions.** Match on the ring's own measured colour,
   group the matching pixels into connected components, and keep only the
   component that SPANS the frame. That is the ring by definition: it is the only
   thing that goes all the way round. Measured, it spans 0.96–1.00 of the frame
   on every portrait while the next largest match spans at most 0.20.

**The antialiasing survives because the ring is drawn over white.** Each pixel is
`alpha*ring + (1-alpha)*white`; blue carries the most contrast (112 against 255)
and recovers alpha, so the swap recomposes the same edge in the new colour rather
than leaving a hard collar.

Verified rather than eyeballed: inside the circle the mean difference is
0.35–1.36 per channel and the worst single pixel 4–12, which is JPEG re-encode
noise; in the ring band it is 5.6–15.6. Re-encoded at 0.86, 203KB to 236KB for
all six. The ring samples rgb(184, 135, 43) off the deployed page.

### The guard this change needed

Every image ships **twice**, once to each build, copied by hand — and a slip
leaves the deployed site with the old file while the dev twin shows the new one.
**Nothing would have caught it**: the suites read the store and the source, the
lint reads PHP, and the cross-build render diff compares MARKUP — both builds
reference the same `/img/<path>`, so identical markup proves nothing about the
bytes behind it. Same class as the content store drifting, which has been pinned
for weeks.

The PHP suite now walks both image trees and asserts every file is byte-identical
with neither build holding a file the other lacks. Watched red against a one-byte
change and against an extra file.

## The footer columns (`5aaff86`, `af44f8b`, `59d774b`)

> "bring third column content up so it is in line with second column content"
> … "add 100px margin over SCHOLARSHIPS and CONTACT only, leave logo lion in
> place" … "change that margin to 60px"

The menus centred individually against the sign-off, and the row is the
sign-off's height, so each centred by its OWN: 278px of links against 217px of
contact details put their headings 30px apart. Both range to the start of the row
now, then take a 60px drop together.

**The second column moved up with the third, and that was said rather than
smoothed over.** Holding the links where they were and lifting only the contact
details means an offset equal to the difference between two content heights —
right once and wrong after the next link is added in `/admin`, which is exactly
the kind of number the footer already carries a warning about. The alternative
that preserves both is a subgrid; offered, not taken.

**Keeping the lion still took one change beyond the margin.** At 100px the taller
menu is 378px against the sign-off's 350, so the row grows by 28 — and a centred
sign-off drifts half of that, 14px, down with it. Ranging every column to the
start pins it. At 60px the menu is 338 and the row does not grow at all, so the
footer's height is back to where it started; the start alignment is a no-op again
at this value and is kept because it is what holds the lion if the number grows.

**A margin between columns is a margin between stacked blocks below the
breakpoint.** Unscoped, the 100px was 100px of space between every stacked block
on a phone, three times over, on top of the 32px gap. Scoped to `min-width:
861px`, where the three actually sit side by side.

## A test that passed while describing the opposite

The guard on the footer was called **"the footer menus centre against the brand
column"** and asserted `align-items: center` on `.foot-grid`. The first of these
commits stops the menus centring — but leaves `align-items: center` in place for
the sign-off, so **the guard stayed green over the behaviour it was written to
prevent**, with a name that was now false.

Renamed, repointed at what the footer actually does, and the stale rationale on
two neighbouring rules rewritten: they no longer keep the menus' text on centre,
they keep each column's box tight to its ink.

Then it earned its keep twice over. It pinned `align-self: start` to
`.foot-grid > div + div`, and when the declaration had to move to
`.foot-grid > div` (so the sign-off ranges too) it went **red** rather than
letting the change through. A pinned declaration is a change detector; that is
the whole point of it.

## The flapping live byte-check, finally chased

The live comparison has reported a page as differing several times today, always
just after a deploy, and a re-read has always found it identical. Chased
properly this time:

- **20 repeat fetches** of the flagged page — all 20 identical to each other and
  to the local build.
- **120 capture-on-difference comparisons** (five passes over all 24 paths,
  writing both sides to disk the moment they disagree): nothing caught.
- Every case where a length difference was reported (13 bytes, 26 bytes) showed
  **no differing character** on re-read.

So it is the fetch during deploy propagation, not the site, and not the stored
files. It is on record as a known artifact rather than something to re-diagnose
each time. What it is NOT: a reason to skip the check — it has caught real
divergence before.

## Where to pick up

1. **"Programs & Partnerships" carries no partnerships** — raised, unanswered.
2. **The giving links move to QuixChex** — still Aplos, still the highest-value
   open item.
3. **Foundation Theatre and Foster Youth** have no picture; the lion is offered.
4. **Tests for the hero rail and the logo strip's behaviour.**
5. **A repeating field in `/admin`** for the nine page and scholarship extras.
