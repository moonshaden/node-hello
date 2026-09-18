# 2026-09-18 — the programs cards, and the verse

Third thread of the same working session, after
`2026-09-18-programs-picture-and-program-rows.md` and
`2026-09-18-giving-callout.md`.

    3c86ae3  Match the programs picture to the impact block on the board page
    50f8871  Show the whole photograph on each program, and open the copy behind Read more
    73986ae  Only disclose a program's copy when hiding earns its keep
    63dc589  Fill each program card before the Read more
    c5944a9  Set the Deuteronomy verse over the programs photograph

## Two asks that could not both hold

This morning: *"make the three boxes underneath photos the same height as text in
box."* This evening: *"use full pic for leadership and crop other two to match."*

A photograph that fills the height of its text is cropped to that shape; the
Impact Leadership one carries the word LEADERSHIP set into the artwork, and the
tall crop cut it to "ADERSH" — which is what the client saw. The two cannot both
hold, the later one wins, and **the reversal was named in the commit rather than
quietly performed.** All three sources are 1200x1200 (stored 800x800), so a
square frame crops none of them and "crop the other two to match" is exactly what
a square frame does to them.

That closes the open item the morning's work created, by undoing the morning's
work. Worth remembering before the next "make these the same height" ask on a
picture with type in it.

## Read more, and the two decisions inside it (`50f8871`, `63dc589`)

> "shorten content and put a read more link to expand" … "fill box with text
> before the read more"

Same disclosure the recipient stories and the board bios use: a `details`, no
script, nothing published unreachable.

**Split on paragraph boundaries, never on a character count.** These bodies carry
markdown links — the Youth Development one has two — and a count cuts one in
half, leaving a raw `[label](url` on the page. A test asserts every markdown link
in every program still renders as an anchor, and it goes red against a
200-character split.

**Add paragraphs until the budget is PASSED, not until it would be exceeded.**
The first version stopped short of the budget and left the Impact Leadership card
on its 174-character opener, which was the card the fill was for. Shown now:
764 / 862 / 567 characters, against 243 / 656 / 361 behind the toggle.

**Measured, not computed** — the logo-strip lesson again. 500 is a proxy for the
photograph's 300px, tuned by looking at rendered cards: at 1440 and 1280 the copy
ends 77px past the photograph on the first two cards and 7px short on the third.
A character count is not a line count and a line count is not a height.

## The change reached a page it was not made for (`73986ae`)

`/community` shares `program-section` for its one partner, so the partner block
got a "Read more" hiding **116 characters** — more friction than the text it
saved, on a page nobody had asked about. Found by looking at the page the change
reached rather than only the page it was made for.

The fix is a threshold rather than a per-page flag: under 240 characters left
over, the whole body renders. Three toggles on `/programs`, none on `/community`,
and the guard covers both directions — a threshold of 0 and one of 5,000 were
each watched red.

## The verse (`c5944a9`)

> "overlay this verse … Deuteronomy 32:2 over this picture"

The caption machinery that was built for the MLK quotation and removed a few
hours earlier comes back, recovered from the history of the same rule: the words
in a grid cell over the photograph with a scrim between, never baked into the
raster.

**This is the first line of copy on the site that is not transcribed from the
live WordPress pages.** That is the client supplying content, which is the
supported case; "never invent content" means nothing is composed here, not that
the client cannot write. Stored on the record, so `/admin` edits it.

It fits the 181px box the board's impact block set two commits earlier, so that
match survives: two lines of verse at 1440 / 1280 / 1024 / 900, three at 862 and
390, caption inside the figure at every one.

## Measurement notes, and three ways a check lied

- **A closed `details` still reports a non-zero `getBoundingClientRect`.** Its
  content is laid out and hidden with `content-visibility`, so a rect-height
  check reports the OPPOSITE of the truth. `checkVisibility()` is the honest
  signal: false shut, true open. The card's own height is the other one — 342
  shut, 564 open.
- **An exact class-attribute match in a test is a formatting assertion wearing a
  behavioural one's clothes.** Two assertions matched `class="page-picture"` and
  broke the moment the figure gained `has-caption` — on nothing being wrong. They
  match the class prefix now. Same family as matching a CSS rule's exact one-line
  text.
- **A deploy gate has to be something the change actually moves.** The chosen
  signal for the fill commit was "the second paragraph is on the live page" — and
  it was there before the deploy started, because the old build shipped the same
  text inside the hidden half. The signal that distinguishes is the paragraph's
  position relative to `class="story-rest"`. A first attempt at that check had
  its own bug: `find()` returning -1 sliced to `[:-1]` and reported SHOWN for
  everything.

## Process notes

- **A stale `python3 -m http.server` held the mirror port**, so a fresh one could
  not bind and every page under test was the 404 page — `.band` missing, the
  measurement meaningless. Third variant of the stale-server lesson, after the
  node twin and its asset-version map.
- **A deployed-page mirror has to be served at the ROOT.** The pages use
  root-absolute `src`s, so mirroring two pages into `p/` and `b/` 404'd every
  asset and both pages rendered unstyled — with measurements that looked like
  real numbers (`[1184, 18]`).
- **The live byte-check flapped again and never reproduced.** 120 further page
  comparisons, each capturing both sides at the moment of difference: nothing.
  Every direct re-read was identical character for character. It is the fetch,
  not the site — but it is now on record as a thing that happens rather than a
  thing to explain each time.
- The disclosure's CSS is on its third copy (recipient story, board bio, program
  body). The same three declarations each time. Left alone deliberately: lifting
  them into one `.story` block changes specificity for two components on two
  other pages, and neither was measured today.

## Where to pick up

1. **The page is called "Programs & Partnerships" and carries no partnerships.**
   Alice Cooper's Solid Rock Teen Center is on `/community`, reachable from one
   sentence of body copy. The live site splits them the same way, so this is not
   wrong — but the title promises something the page does not deliver. Raised
   with the client, not yet answered.
2. **The giving links move to QuixChex** — still the highest-value open item, and
   still pointing at Aplos.
3. **Foundation Theatre and Foster Youth** have no picture; the lion is offered.
4. **Tests for the hero rail and the logo strip's behaviour.**
5. **A repeating field in `/admin`** for the page and scholarship extras, which
   now number nine fields across two records.
