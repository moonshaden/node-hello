# LEO Foundation — scraped source data & content audit

Scraped from the live WordPress site at <https://leofoundationusa.org> via its
open REST API (`/wp-json/wp/v2/`). All content below is verbatim from published
pages. Captured 2026-08-24.

## Blocker: no LEO Foundation codebase in scope

The task asked to load recipients into "both content stores", delete "the three
placeholder drafts", and verify "seeded scholarship copy". **None of those exist
in any repository available to this session.**

- This repo (`moonshaden/node-hello`) is a two-file Express "hello world" server.
  The working branch is identical to `master`.
- No LEO Foundation repo exists on the account. Available repos: `quixtix`,
  `node-hello`, `quixchex`, `quixchex-site`, `globalone`, `claude-memory`,
  `ach-frontend`.
- There is no `CLAUDE.md` in this repo.

So the scrape — the input to all of that work — is done and committed here.
The load/delete/verify steps need the real repo.

## What was captured

- `data/recipients.json` — 15 recipients, full verbatim bios, photo URLs.
- `data/scholarships.json` — 16 scholarships (13 published, 1 hidden, 2 form-only)
  with amounts and full qualifying criteria.

Source pages: Scholarship Recipients (page ID 3422), Available Scholarships
(3442), and 13 individual scholarship pages.

## Impact counters ("the numbers")

The recipients page renders these as animated counters that start at `0`; the
real target values live in `data-value` attributes:

| Metric | Value |
| --- | --- |
| Years | 20 |
| Money raised | $8,500,000 |
| Students awarded | 5,685 |
| Awarded in scholarships | $6,900,000 |

## Recipients (15)

Every recipient has a photo. Only first names are published for 13 of 15 — the
Hamre sisters are the only ones with surnames.

| # | Name | School | Scholarship named in bio |
| --- | --- | --- | --- |
| 1 | Sophia Hamre | Grand Canyon University | LEO Foundation Scholarship |
| 2 | Sydney Hamre | Grand Canyon University | *not named* |
| 3 | Elijah | Grand Canyon University | *not named* |
| 4 | Matthew | *not named* | LEO Foundation Christian Studies Scholarship |
| 5 | Jacob | *not named* | BHHS Legacy Nursing & Health Related Scholarship |
| 6 | Natalie | *not named* | LEO Foundation Scholarship |
| 7 | Isabelle | GCU (transferred from UC Santa Cruz) | LEO Foundation (master's) |
| 8 | Eunice | *not named* | LEO Foundation Scholarships |
| 9 | Sebastian | *not named* | LEO Foundation |
| 10 | Joshua | Grand Canyon University | LEO Foundation Scholarship |
| 11 | Jaiden | Yavapai College | *not named* |
| 12 | Keian | Grand Canyon University | LEO Foundation scholarship |
| 13 | Caleb | Grand Canyon University | LEO Foundation |
| 14 | Brooklyn | *not named* | LEO Foundation |
| 15 | Julian | *not named* | Leo Foundation Scholarship Award |

### Gap: no years, no amounts

**The site publishes no award year and no dollar amount for any recipient.**
Both fields are `null` for all 15 records. If the "students behind the numbers"
section is meant to show year and amount per student, that data has to come from
the client — it is not on the website.

Four recipients (2, 3, 11 and partly 12) have no scholarship named in their bio
either, so they cannot be linked to a specific award without client input.

## Content problems found on the live site

**Correction (client input):** Arvizu, Tim Browning Memorial, and Alex Acosta are
**deliberately disabled — the foundation is not currently issuing them.** An
earlier version of this file called that a bug and claimed students could apply
to scholarships they could not read about. That was wrong, and the risk was
overstated. Corrected analysis below.

### How the application forms actually work

The homepage carries the modal markup for all 16 application forms. A custom
script defines a `modalMap` of page-slug -> modal class, and on each scholarship
detail page it rewires the "APPLY ONLINE" button to open the matching modal.

Matching is `currentUrl.includes(key)` — a naive substring test against the whole
URL, query string included. Verified: the 13 live scholarships all route to the
correct modal, no collisions.

The three withdrawn scholarships are correctly disabled at the page level — all
three detail URLs return **404** and none appear on Available Scholarships.

### 1. Arvizu application form is still reachable (the one real remnant)

`"arvizu-scholarship": "arvizu"` is **still in the `modalMap`**, and the Arvizu
modal (`.fusion-modal.modal-27.arvizu`) is still in the homepage DOM. Because the
match is a substring test on the full URL, any URL containing that string still
opens a working application form — including a query string on the homepage:

    https://leofoundationusa.org/?arvizu-scholarship

The intended route (`/arvizu-scholarship/`) 404s, so this is not linked from
anywhere and nobody reaches it by normal browsing. But the form still submits.

**Fix:** delete the `"arvizu-scholarship": "arvizu",` line from the homepage's
custom JS. That alone closes it.

### 2. Dead modal markup for the other two (cleanup only, no exposure)

`.fusion-modal.modal-14.timbrowning` and `.fusion-modal.modal-28.acosta` remain
in the homepage DOM, but neither has a `modalMap` key and neither has a page, so
there is no way to open them. Harmless — worth removing to keep the homepage from
carrying forms for scholarships that no longer exist.

**Fix:** remove those two modal blocks in the homepage layout.

### 3. Duplicate BHHS block

A second BHHS Legacy entry is commented out on Available Scholarships. The live
one says amount "Various"; the commented one says "Amount varies"; the detail
page says "The scholarship amount varies." Pick one wording.

### 4. Name mismatch

Available Scholarships calls it "LEO Foundation Theatre Scholarship"; the page
itself is titled "Foundation Theatre Scholarship".

### 5. Typos in published copy

"pursing" for "pursuing" appears twice on Available Scholarships (SKW Music,
Theatre). The LaVern Baker entry runs the name and "Scholarship Amount:" together
without a break.

### 6. Sophia Hamre bio

Mixes first and third person, and contains a sentence fragment: "allowing her to
continue her studies with a sense of relief and determination."

### 7. Photo filenames are meaningless

Three recipient photos (Matthew, Joshua, Julian) are served as
`Copy-of-Murphy-logo*.png`. They are real photos (231-537 KB), just badly named —
worth renaming on migration.

## Applying these fixes

Items 1 and 2 are edits to the **live WordPress homepage** (Avada/Fusion builder
plus a custom JS block). This session has no WordPress credentials and no repo
containing the theme, so they could not be applied here — they need someone with
WP admin access. Items 3-6 are page-content edits in the same place. Item 7 is a
media-library rename best done during migration.

## Application window (as published)

November 1, 2025 through March 31, 2026, for the 2026-2027 academic year.
Applicants must be traditional ground students who are Arizona residents.
Contact: mwinney@leofoundationusa.org
