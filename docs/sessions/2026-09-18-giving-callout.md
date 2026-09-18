# 2026-09-18 — the callout at the head of the giving page

Same working session as `2026-09-18-programs-picture-and-program-rows.md`, which
it continues; a separate file because it is a separate component and nobody
looking for this will look there.

    7564c2a  Open the giving copy with a callout under its index
    ce327e5  Set the giving callout as a bold line over a second sentence
    358e960  Carry the third sentence into the giving callout, and open the gap above it
    d3ff38e  Move the giving callout above the jump index
    ae732e2  Set the head of the copy column: 30px between the boxes, 50px under the index

Five commits for one small box, and the shape is the same as the picture on
`/programs` earlier the same day: the client refines by looking. Each round was
measured, shipped and shown before the next ask arrived, which is what makes
that affordable.

## What it is

> "add this under the top shaded column on the giving page and use the first
> sentence of the text underneath as content"

The reference was a screenshot of **this site's own** scholarships panel — the
white card with the gold left bar that carries the enrolment instructions. So
this is that component in a new place rather than a second design: `.notice`,
with a `page-notice` variant for the spacing.

It is a `notice` object on the page record — `{ heading, body }`, either half
optional — following `logoStrip`, `picture` and the rest: it lives on the record,
survives an admin save because `applyFields()` spreads the existing record first,
and no form field edits it. **Not editable in `/admin`**, like the others, and
that is now a small pile of page extras in the same position; worth a repeating
field one day.

## The copy moved, it was not duplicated

Three sentences went into the box over two rounds, and each time they came OUT
of the paragraph below:

| round | box | copy now starts |
| --- | --- | --- |
| `7564c2a` | "There are many ways to give to the LEO Foundation." | "Your gift of any size..." |
| `ce327e5` | + "Your gift of any size and in any form can make a difference for students today." | "And, it's tax-deductible." |
| `358e960` | + "And, it's tax-deductible." | "Scholarship assistance can be designated..." |

Duplicating would have put the same line twice within an inch. Every word the
live `/ways-to-give/` page publishes is still on this page, in the same order —
only the container changed. A test asserts each line appears exactly once, in the
store and in the rendered HTML.

**The middle row of that table is why the third round happened, and it was
flagged rather than left.** After `ce327e5` the copy opened with "And, it's
tax-deductible." as a paragraph of its own — four words starting with a
conjunction, because the sentence they followed had moved into the box. Saying so
plainly ("the natural fix is to carry it into the callout, it is one line either
way") got a one-word answer and a better page.

## The things that were wrong first

**A comment that reasoned instead of measuring.** The note against the margin
said the gap would be 56px — the box's 50 plus the index's own 6. It is 50:
**adjacent sibling margins collapse to the larger of the two**, and being a flex
container does not change that. What that *does* change is collapsing with its
own children's margins, and staying out from under the floated card. The comment
now says what was measured. Getting the reasoning wrong in a comment is worse
than not commenting: the next person inherits the error with a confident tone.

**A CSS guard that passed for a weak reason.** The assertion checked that
`.page-notice` had *a* `margin:` declaration. Flipping the 50px from the bottom
to the top — the exact regression the layout could suffer — left it green. That
came out of the routine mutation pass and is why the pass exists. It pins
`margin: 0 0 30px` now.

Then it earned its keep one commit later: the very next ask changed that gap, and
the guard went **red** instead of letting the change through silently. A pinned
declaration is a change detector, not a correctness proof, and that is the point.

**An assertion anchored on a word that moved.** "The callout is above the copy"
was anchored on `tax-deductible` — which then moved INTO the callout, so the
assertion would have been comparing the callout with itself and passing. It is
anchored on the copy's own first words now, and was watched red against a
template that renders the callout after the article.

## Above the index, not under it (`d3ff38e`)

> "lets see what the box would look like above the shaded box in same column"

Worth recording as a practice: this was mocked up **locally and reverted** —
template order swapped, margin flipped, screenshot taken, working tree restored,
nothing committed or deployed. The question was "what would it look like", and
the answer is a picture, not a release.

The answer was yes, and it is measurable rather than a matter of taste: above the
index, the callout's top edge lines up with the top of the aside card at **0px**,
so the column starts on one horizontal. Below it, the index held that line and
the callout hung under it.

## The spacing, and what else it touched (`ae732e2`)

> "put a 30px margin between callout and index boxes and a 50px margin under index"

30px between the two boxes, so they read as a pair at the head of the column, and
50px under the index — the gap the callout, the inline logo strip and the
programs picture all sit on.

**The second half is a change to a shared component.** `.page-index` carried
`margin-bottom: 6px`, and it is the same rule on every page with enough headings
to get an index: `/faq` (13), `/donate` (11), `/privacy` (9). All three were
measured and looked at, not just the page that prompted it — and the FAQ gains
the most, because its first question's rule was almost against the box. Said so
to the client rather than letting them find it.

Final measurement, on the deployed page: callout top 0px against the card, 30px
to the index, 50px under the index, both boxes the same width.

## Deploy and verification notes

- **A commit with no CSS change cannot be gated on the stylesheet hash.** `ce327e5`
  touched only views and the store, so the usual "poll until `site.css` matches"
  check was green before the deploy even started. The signal used instead was the
  callout *disappearing* from the live page — the new template reading
  `notice.heading` against a store that still held a plain string — and then
  coming back after the `seed_content` run. Pick a gate that the change actually
  moves.
- **A view-only change still needs both runs when the store's shape changed**,
  and the page renders nothing in between. Brief, and on a review site, but worth
  knowing before someone looks at the wrong moment.
- **The live byte-comparison flapped, and it was the fetch, not the content.**
  Two pages reported as differing mid-deploy; re-reading each byte for byte found
  no differing character, and five full passes afterwards — 120 page comparisons
  — were clean. That is the fourth time a transient "DIFFERS" has been chased
  here. Always re-read before reporting one.

## Where to pick up

1. **The Impact Leadership crop** — "ADERSH". Offered, still the client's call.
2. **Tests for the hero rail and the logo strip's behaviour** — still the largest
   piece of debt that does not need the client.
3. **Foundation Theatre and Foster Youth** have no picture; the lion is offered.
4. **A repeating field in `/admin`** for the page extras that now number five —
   `members`, `programs`, `partners`, `gallery`, `photos`, and now `notice` and
   `picture`. All live on the record and none is editable.
5. **PR #4's body** is stale — forty-two commits behind at this head.
