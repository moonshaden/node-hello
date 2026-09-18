# 2026-09-18 — the lion links home, and a way back to the top

Fifth thread of the same working session, after
`2026-09-18-board-rings-and-footer.md`.

    d278b7d  Link the footer lion to the homepage
    f8e924e  Add a back-to-top control to any page that runs past a full screen

Two small controls, and between them four things that would each have shipped
as a quiet defect.

## The footer lion links home (`d278b7d`)

> "make the lion head logo in footer link to homepage"

An anchor round the mark. Two things the markup needed beyond that, and neither
is obvious from the ask:

**The link has no accessible name unless you give it one.** The lion is `alt=""`
and `aria-hidden="true"` on purpose — the wordmark under it is the sign-off's
accessible name, and a mark that also announced would say the organisation
twice. So an anchor wrapping *only* that image is a link with no name at all:
a screen reader reads "link" and stops. It carries
`aria-label="LEO Foundation home"`, the same wording the masthead uses.

**`width: max-content` on the anchor.** An anchor is a block here, and the
sign-off block is 320px against a 175px lion — so without it the dead space
either side of the mark is clickable, which reads as a broken target rather
than a link. `margin: 0 auto` re-centres the shrunk box, because the image's own
auto margins have nothing left to distribute once the box is its width.

Measured rather than assumed, in both builds: the lion unchanged at 56px from
the top of the footer and 14px above the wordmark, its box and the link's box
the same 175x128, `elementFromPoint` returning the link at the mark's centre and
`.foot-sign` four pixels inside either edge, a click landing on `/`, and the
anchor taking keyboard focus reporting "LEO Foundation home".

## Back to top (`f8e924e`)

> "lets put a return to top arrow on any page that scrolls past a full screen"

### The condition was measured before it was coded

Every page qualifies today, and the numbers are the reason the rest of the
component looks the way it does. At 1280x900 the shortest page is Foster Youth
at 1.67 screens; in a 1080px-tall window it is **1.40 screens, 427px of
scroll**. The homepage is 4.99 screens, `/recipients` 6.66, and on a 390px phone
`/recipients` is **18.66**.

So "past a full screen" is not a filter today — but page copy is editable in
`/admin` and a tall window shrinks the list, so it is judged at runtime and
re-judged on resize and on `load` (lazy images make a document taller after
parse).

### Two thresholds, because one is wrong at both ends

- **Half a screen down** is the usual trigger, and on Foster Youth in a 1080px
  window you can never scroll that far — the control would exist and never
  appear.
- **Halfway down the page** fixes that and is far too late on a long page: the
  homepage would not offer one until 1,797px.

Whichever comes **first**, which is the smaller number in both directions.
Measured: 450px on `/recipients`, 214px on Foster Youth.

### The traps

**`.to-top[hidden] { display: none }` is load-bearing, and this is the second
component to need it.** The rule sets `display: grid`, and a class with
`display` outranks the browser's own `[hidden] { display: none }` — so without
that line the control is on screen on every page from the first paint, before
the script has looked at anything. The hero rail learned this in August; the
note in CLAUDE.md said "any component that sets `display` on a class and then
toggles `hidden` in script needs the same line", and it was right.

**A bare fragment, never base-path-prefixed.** The first version of the PHP view
built `href="<?= e($basePath) ?>#top"`, by reflex, because every other link in
that file does. `#top` resolves against the *current* URL, so a prefixed one
would be the **homepage** plus a fragment under the `/~leofoundationusa`
temporary URL — a back-to-top control that navigates away from the page you are
on. Caught by reading it back before the suites ran; a guard pins the bare form
in both views now, and the mutation for it is exactly that prefix.

**Hidden with `visibility`, not `display`.** It has to leave the tab order
between scroll positions *and* be transitionable. `visibility: hidden` does both
— unlike `display`, which does the first and cannot be animated, so the fade
would need the hero rail's two-frame dance. The nav dropdown is hidden the same
way for the same focus reason.

**Focus has to follow the scroll.** Without it a keyboard user activates the
control, the page goes to the top, and their focus is still in the footer — so
the next Tab takes them straight back down. The masthead is `id="top"` with
`tabindex="-1"` and the handler focuses it with `preventScroll`. The href is a
real fragment, so the destination works even if the handler never binds.

### The one that would have shipped as a bug

**It covered the footer's "Staff sign in" link at every width below ~1180px.**
The control is fixed to the **viewport's** right edge; the sign-in link floats to
the right of **`.wrap`**, which is capped at 1120px. Above about 1180 those are
apart — at 1280 the button's left edge is 1214 against a wrap ending at 1200 —
and at every width below they coincide. Swept and confirmed unclickable at
**1140, 1024, 900, 760, 560 and 390**, which is every phone.

Three fixes were possible and two were wrong:

- *Hide the control when the footer comes into view* — that removes it exactly
  when someone is at the bottom of a long page, which is when it is most wanted.
- *Move the sign-in link* — it belongs in that corner.
- **Reserve the space.** `.foot-legal` takes `padding-right: 66px` (56 under
  560px, matching the smaller control), which clears it at every width because
  `.wrap` is itself inset 24px: the float ends at least 24px left of the
  button's edge. Re-swept — nothing interactive under the control at any of
  those widths.

Worth keeping as a general note: **a viewport-fixed element and a `.wrap`-bound
one converge as the window narrows.** They are furthest apart on the widest
screen, which is where a change like this is looked at.

### Exercised, not loaded

Both builds, in a browser: hidden at the top, still hidden at 300px on
`/recipients` (under the 450 trigger), shown at 600, click returns scrollY to 0
and moves focus to `HEADER#top` and the control hides itself again, 300px on
Foster Youth shows it (the halfway branch doing its job), a page in a 2000px
window keeps its `hidden` attribute, it is last in the tab order named "Back to
top", Enter does what a click does, and under `prefers-reduced-motion` the
control stays and still lands at 0 — stillness is not a request to lose the way
back.

## Test notes

**A `[^}]*` assertion cannot reach into a media block.** The rule already on
record is to use `[^}]*?` rather than `.*?` so an assertion stays inside its
rule — but the phone reserve lives *inside* `@media (max-width: 560px)`, past
the closing brace of the `.to-top` rule above it, so `[^}]*` stops short and the
assertion was red against correct CSS. A **bounded** `.{0,400}?` is the shape
that works: it crosses the one brace it has to and still cannot wander off down
the sheet. Same technique the suite already uses to step over a PHP short-echo
tag's closing bracket.

Ten mutations across the two commits, each watched red against its own broken
subject — including one that went green on the first grep for it and turned out
to be the grep, not the guard. Read the failure text, not the exit code of a
pattern match.

**The cross-build diff script was rewritten from scratch** (the previous one
lived in a scratch directory and was gone), and the first version reported
**24 of 24 differing** on a whitespace residue: EJS and PHP leave different
blanks where their own server-side comments were. Collapsing whitespace *runs*
— not deleting whitespace, which would hide a lost space between words — brings
it to zero. Then the new normaliser was itself checked by breaking one attribute
in one build and confirming it reported 24 of 24 again. A normaliser that
reports zero is worth nothing until you have seen it report something.

## Where to pick up

1. **The giving links move to QuixChex** — still Aplos, still the highest-value
   open item.
2. **"Programs & Partnerships" carries no partnerships** — raised, unanswered.
3. **Foundation Theatre and Foster Youth** have no picture; the lion is offered.
4. **Tests for the hero rail and the logo strip's behaviour** — the largest
   piece of debt that does not need the client.
5. **A repeating field in `/admin`** for the nine page and scholarship extras.
6. **PR #4's body** is stale — forty-seven commits behind at this head.
