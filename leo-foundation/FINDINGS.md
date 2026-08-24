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

1. **Arvizu Scholarship is invisible.** Its block on Available Scholarships is
   wrapped in an HTML comment, so it never renders — but its application modal is
   still live on the homepage. Students can apply to a scholarship they cannot
   read about. ($1,000; requires Latino heritage.)
2. **Tim Browning Memorial Scholarship** and **Alex Acosta Scholarship** have
   application modals on the homepage but no detail page and no listing on
   Available Scholarships. No published amount or criteria for either.
3. **Duplicate BHHS block.** A second BHHS Legacy entry is also commented out on
   Available Scholarships. The live one says amount "Various"; the commented one
   says "Amount varies"; the detail page says "The scholarship amount varies."
4. **Name mismatch.** Available Scholarships calls it "LEO Foundation Theatre
   Scholarship"; the page itself is titled "Foundation Theatre Scholarship".
5. **Typos in published copy.** "pursing" for "pursuing" appears twice on
   Available Scholarships (SKW Music, Theatre). The LaVern Baker entry runs the
   name and "Scholarship Amount:" together without a break.
6. **Stale-dated Sophia Hamre bio.** It mixes first person and third person, and
   contains a sentence fragment: "allowing her to continue her studies with a
   sense of relief and determination."
7. **Photo filenames are meaningless.** Three recipient photos (Matthew, Joshua,
   Julian) are served as `Copy-of-Murphy-logo*.png`. They are real photos
   (231–537 KB), just badly named — worth renaming on migration.

## Application window (as published)

November 1, 2025 through March 31, 2026, for the 2026-2027 academic year.
Applicants must be traditional ground students who are Arizona residents.
Contact: mwinney@leofoundationusa.org
