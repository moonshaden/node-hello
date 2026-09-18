# Session log — 15–16 September 2026, homepage and page layout

Head at the end: `3f524d0`. Branch `claude/leo-foundation-site-redesign-knr6vv`,
PR #4 (draft).

`CLAUDE.md` carries the **state** — what the site is now and the traps to avoid.
This file carries the **narrative**: what was asked, in what order, what was
tried and rejected, and what went wrong. Read it if you are picking the work back
up and want to know why something is the way it is rather than what it is.

## What the client asked for, in order

Each line is a separate request; most were reviewed on the deployed build
subdomain before the next one started.

| # | Request | Commit |
| --- | --- | --- |
| 1 | 20px interior padding on every box and container | `719b39a` |
| 2 | Remove the motion effect on the LEO pillars, and across the site | `38f9b22` |
| 3 | Deploy it to the build site | — |
| 4 | Have the hero showcase more than one student, maybe 3, with their excerpts | `bb53658` |
| 5 | Three containers on the right, rotating through all students, fading every 5 seconds | `bb53658` |
| 6 | The fade pushes the page down — it should carousel in place | `761a103` |
| 7 | Fade the students individually, not as a group of 3 | `7deb2af` |
| 8 | Some logos look small in the carousel | `8b318af`, `bbd4469` |
| 9 | Put the logo carousel between the hero and the numbers | `ca7b72a` |
| 10 | Run the seed so the logos show | — (workflow run) |
| 11 | White background for the marquee band | `ca7b72a` |
| 12 | Show 2 students, not 3; move the mission paragraph into the third's place | `0b51c84` |
| 13 | Make sure the rotation doesn't leave anyone out | `0b51c84` |
| 14 | Reorganise — less wasted real estate; buttons beneath the wording, not centred | `0b51c84` |
| 15 | Write the CSS for mobile too | `8b318af` |
| 16 | Move the `PHOENIX, ARIZONA · 501(C)(3) NONPROFIT` line into the white ribbon | `566674f` |
| 17 | Make it all one line — it takes up real estate | `ad7994c`, `ccfda64` |
| 18 | The masthead should render as one line, not a break; fix mobile too | `bbd4469` |
| 19 | Extend the FAQ answers to the full container width instead of leaving the right side blank | `779ae07` |
| 20 | The yellow box and the white box need spacing between them | `7c47917` |
| 21 | Update the PR body and the project notes | `3f524d0` |

## Decisions, and what was rejected

**Three students became two.** The client asked for three, saw it, and asked for
two with the mission paragraph in the third's place — explicitly to conserve
vertical space. `data-hero-visible` is an attribute rather than a constant so
this is a one-character change if they want three back.

**The fade was a group swap first.** All visible cards changed together on each
tick, which read as a slideshow rather than a rotation. It now advances one slot
per tick (`tick % PER_VIEW`), so each card has its own rhythm.

**Rotation coverage needed a cursor, not a shuffle.** The first version picked
the next card relative to the slot being replaced, which could re-show the same
few recipients and strand others. A single forward cursor over the whole list,
skipping anything already on screen, is what guarantees every recipient appears.

**The logos were fixed by cropping, not by CSS.** First attempt was
`max-width: 190px` with `object-fit: contain`, which made it worse: a wide
wordmark in a padded square letterboxes, so Nippon's 200×34 mark rendered about
32px of ink inside a 58px box next to a full-height roundel. The answer was to
measure each file's ink bounding box from the alpha channel, crop to it, and
then size — after which the CSS cap could be removed entirely.

**The masthead needed three tiers, not a `nowrap`.** A single `nowrap` rule
starting at 901px overflowed horizontally: the row needs about 1000px and the
column is 952px at that width.

**The page card floats rather than taking a grid column.** A grid column leaves
the space below the card blank; a float lets the copy wrap beside it and then run
the full width. The cost is that the aside has to come first in source, so a
screen reader meets the card before the copy — four short lines, judged the
cheaper of the two.

## What went wrong, and how it surfaced

Recorded because the same mistakes are easy to repeat.

- **"Done" was said when "pushed" was meant — three times.** The client looked at
  the live site before the deploy had finished and reported the change as broken
  or missing. It was neither; it simply was not there yet. The agreement now is:
  a change is only described as live once its bytes match the local build. A
  deploy takes roughly 90 seconds. **This is the single most useful thing in this
  file.**
- **Verification was too slow and too noisy.** Several rounds of headless
  screenshots ran while the client waited. They asked for it to stop: push to the
  build site and hand over links. Numeric measurement (element rectangles,
  character counts, byte hashes) answers most layout questions faster than a
  screenshot and is easier to check.
- **A client observation was second-guessed.** Told the masthead was breaking,
  the response assumed a narrow window; they replied "it is not my window range,
  I am full screen". Their Mac renders these strings roughly 12% wider than this
  sandbox's headless Chromium. Sizing for a measured fit *here* is not sizing for
  their screen — hence the headroom rule in `CLAUDE.md`.
- **A stale `node server.js` on :3000 caused a false alarm.** The cross-build
  render diff reported all ten pages differing. The cause was an old process
  holding the port, so `npm start` died with `EADDRINUSE` and the diff compared
  the *old* Node build against the current PHP one. Check `/proc/*/cmdline` and
  kill by PID; `pkill -f` matches the invoking bash command and kills the caller.
- **The wrong thing was checked, twice.** A probe for staged elements used
  selectors (`[data-stage]`, `.reveal`) that appear nowhere in this codebase —
  the real classes are `.is-deep` and `.is-risen` — so it reported success
  against nothing. And a first pass at tests called helpers that do not exist
  (`renderRoute`, `tempStore`). Read the file you are testing against.

## Where to pick up

1. **Tests for the hero rail and the logo strip.** Nothing covers the rotation,
   the one-at-a-time slot logic, the no-skip cursor, the `heroLine` first-person
   rule, or the strip. Everything in this session was verified by measurement and
   on the deployed site, which catches nothing in future. Highest-value item that
   does not need the client.
2. **Decide the `85ch` question.** Below the floated card at 1440px a line of
   page copy runs ~128 characters against the 60–75 that reads comfortably. The
   cost was put to the client twice and they have not ruled on it either way —
   they approved the layout, not the measure. The cap is one rule on
   `.page-flow .prose` if it turns out to read too wide.
3. **Client-blocked, unchanged:** the giving links still point at Aplos rather
   than QuixChex (live money paths, known to be going stale); the 26 logos have
   no alt text because nothing published names those organisations; the build
   subdomain is still crawlable; the About board-governance sentence is still
   untranscribed. All listed in `CLAUDE.md`.

## Working agreements established this session

- Say a change is **live** only after its deployed bytes match the local build.
  A 200 proves the server answered, not that it answered with this build.
- Prefer pushing to the build site and handing over a link to running long local
  verification loops while the client waits.
- Size the header for headroom, not for a fit measured in this sandbox.
- Take the client's description of what they are seeing at face value and look
  for a cause that fits it, rather than proposing one that makes the report wrong.
