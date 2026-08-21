# Moving off WordPress

What has to happen, in the order it has to happen. Nothing here needs the
WordPress site to stay up afterwards.

## 1. Get the content out

The old site is the source of truth for wording. Export it once and work from
the export rather than copying page by page from a live site that is about to
be switched off.

- **Tools → Export → All content** in wp-admin produces a single WXR (XML) file.
  Keep it; it is the archive of record.
- Save the media library separately (`wp-content/uploads`). Recipient photos are
  the only images this site needs.
- Note the exact URL of every page that currently exists. That list becomes the
  redirect map in step 4.

## 2. Put the content into `data/content.json`

Either paste it through the admin area at `/admin`, or edit the JSON directly
and restart. Both end up in the same place.

The four content types:

| WordPress | Here |
| --- | --- |
| Each scholarship page | A **Scholarship** record |
| The recipients page | One **Recipient** record per student |
| About, FAQs, Donate, Contact | **Pages** |
| Sitewide banners, seasonal notices | **Announcements** |

Two things worth doing carefully:

- **Set the enrollment period once**, in Settings — `11-01` to `03-31` as a
  repeating period. Every scholarship inherits it. Only override it on an award
  that genuinely runs on a different schedule.
- **Match recipient `scholarship` values to scholarship names exactly.** That
  string is what puts a student on the right scholarship page. The admin form
  offers the existing names as autocomplete.

## 3. Check it in both seasons

The site behaves differently in and out of the application window, so look at
both before launch. Signed in, append `?asOf=` to any URL:

- `/?asOf=2026-11-01` — the day applications open
- `/?asOf=2026-03-31` — the last day to apply
- `/?asOf=2026-04-01` — the day after, when the closed-season copy takes over
- `/?asOf=2026-08-21` — deep out of season

## 4. Redirects

WordPress URLs end in a trailing slash and this site's do not, so decide the
redirects before the DNS change. Current mapping:

| Old | New |
| --- | --- |
| `/available-scholarships/` | `/scholarships` |
| `/leo-foundation-scholarship/` | `/scholarships/leo-foundation-scholarship` |
| `/scholarship-faqs/` | `/faq` |
| `/scholarship-recipients/` | `/recipients` |

Put these in whatever sits in front of the app — nginx, Cloudflare, the host's
own redirect rules. Verify each one against the URL list from step 1; anything
missed becomes a 404 for a student mid-application.

## 5. Switch over

1. Deploy, with `ADMIN_PASSWORD` and `NODE_ENV=production` set.
2. Point DNS at the new host and confirm HTTPS.
3. Keep the WordPress instance running but unreachable for a fortnight, in case
   something turns out to be missing.
4. Cancel the WordPress hosting, plugin licences, and any security or backup
   subscriptions attached to it.

## What is deliberately not carried over

- **Plugins.** Nothing here needs one.
- **Comments.** The old site had no meaningful use for them.
- **A user table.** One shared admin password, changed by editing an environment
  variable and restarting. If several people need separate accounts later, that
  is a real change — say so rather than working around it.
- **File uploads through the admin.** Recipient photos are referenced by URL.
  Host them wherever the foundation already keeps images; adding an upload
  pipeline means adding storage, and that can wait until it is actually wanted.
