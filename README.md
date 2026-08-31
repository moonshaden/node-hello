# LEO Foundation website

The foundation's public site and the small admin area that runs it. No
WordPress, no database, no build step.

**Two builds of the same site**, sharing one content format:

| | Runtime | Use it when |
| --- | --- | --- |
| [`php/`](php/) | PHP 8, zero dependencies | **Deploying to the cPanel account.** Nothing to install on the server. |
| root | Node 20 + three packages | Local development, or a host that runs Node. |

They render identically and read the same `content.json`. The PHP build is the
one that goes on shared hosting — see [`php/README.md`](php/README.md) for the
cPanel install.

## The Node build

```bash
npm install
ADMIN_PASSWORD='choose-something-long' npm start   # http://localhost:3000
npm test
```

Without `ADMIN_PASSWORD` the public site still serves; the admin area at
`/admin` stays switched off.

## Why it is built this way

The site has one job that WordPress made harder than it needed to be:
**scholarship application periods open and close on a schedule, and the awards
already made are the thing worth showing off.** Everything here follows from
that.

- **Application periods repeat and wrap the year.** The enrollment period runs
  November 1 to March 31, which is one cycle spanning two calendar years. It is
  stored once as a repeating month-day pair, and resolved into the current cycle
  on every request. Nobody has to edit a date in November, and the site cannot
  be caught claiming applications are open when they closed months ago.
- **Deadlines are calendar dates in the foundation's timezone.** "Closes March
  31" means the end of March 31 in Phoenix, whatever timezone the server is in.
  Phoenix does not observe daylight saving, and none of this depends on that.
- **Recipients lead.** Awarded students appear above the application funnel on
  the homepage, get their own filterable page by year, and appear on the page of
  the scholarship they won.
- **Content lives in one JSON file.** `data/content.json` diffs cleanly in git,
  a backup is `cp`, and there is no database to administer or migrate.

## Layout

```
server.js              entry point
src/schedule.js        window resolution — annual cycles, publish gating
src/store.js           atomic JSON content store
src/content.js         view models shared by the public routes
src/markdown.js        markdown rendering with a sanitise pass
src/auth.js            single-password admin session
src/app.js             app factory and per-request context
src/routes/public.js   the public site
src/routes/admin.js    the admin area — one config table drives all four types
views/                 EJS templates
public/css/            two stylesheets, hand-written
data/content.json      all site content
test/                  node:test, no test framework needed
```

## Time windows

Three shapes, all resolved by `src/schedule.js`:

| Shape | Stored as | Behaviour |
| --- | --- | --- |
| `annual` | `opensOn: '11-01'`, `closesOn: '03-31'` | Repeats every year. A close date earlier than the open date wraps the year. |
| `fixed` | `opensOn: '2026-09-01'`, `closesOn: '2026-09-30'` | One-off. Closes permanently. |
| `always` | — | Always accepting. |

Scholarships default to `inherit`, meaning they follow the site-wide enrollment
period set in **Settings**. Give an individual award its own window when it runs
on a different schedule.

Two further controls, separate from the application window:

- **Show from / hide after** — a hard visibility gate for anything that should
  only exist for part of the year: an awards-night page, a matching-gift
  campaign, a seasonal announcement.
- **Hide while closed** — off by default, because students research awards months
  before they can apply. Turn it on for an award that should vanish out of season.

Announcements can be bound to the enrollment state instead of to dates
(`Only while applications are open` / `...closed`), so the "apply now" banner and
the "reopens in November" banner swap themselves over every year.

## Admin

`/admin`, one shared password in `ADMIN_PASSWORD`, signed HttpOnly cookie, twelve
hour session. Four content types — scholarships, recipients, pages,
announcements — share one set of routes and one form template driven by the
`RESOURCES` table in `src/routes/admin.js`. Adding a field is a one-line change
there.

**Date travel.** Signed in, append `?asOf=2026-01-15` to any public URL to see
the site exactly as it will look on that date, drafts included. It is how you
check next season's homepage without waiting for next season. Anonymous visitors
never see drafts or previews, whatever query string they send.

## Deploying

Any host that runs Node: a small VPS, Render, Fly, Railway.

- Set `ADMIN_PASSWORD` and `NODE_ENV=production` (the latter marks the session
  cookie `Secure`, so serve over HTTPS).
- `SESSION_SECRET` is optional; set it to invalidate existing sessions without
  changing the password.
- `data/content.json` is the only mutable state. Back it up, or commit it — both
  work.
- Run one process. Two processes writing the same file will not corrupt it, but
  the last writer wins.

## Notes on the seeded content

This repository was built without access to the live site — the environment it
was developed in blocks `leofoundationusa.org`. The seeded scholarships, pages
and organisation details were reconstructed from public sources and **need
checking against the real site before launch**, particularly award amounts and
eligibility criteria.

The three recipient records are deliberate placeholders, saved as drafts so they
never appear publicly. Replace them with real students. See
[`docs/MIGRATION.md`](docs/MIGRATION.md) for the move off WordPress.
