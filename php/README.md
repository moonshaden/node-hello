# LEO Foundation website — PHP build

The same site as the Node version, rewritten for shared cPanel hosting. No
Composer, no build step, no Node, no database, nothing to install on the server.
PHP 8.0 or newer is the only requirement.

```
leo-app/            everything private — goes ABOVE the web root
  boot.php          wiring
  config.php        your admin password (you create this)
  src/              Schedule, Store, Content, Markdown, Auth, Admin, App
  views/            templates
  data/content.json all site content
public_html/        the only folder the web serves
  index.php         front controller
  .htaccess         routing, old WordPress redirects, security headers
  css/  js/
test/run.php        38 tests, run with `php test/run.php`
```

Nothing in `leo-app/` is reachable over HTTP, because it sits outside
`public_html`. Even if PHP stopped executing, `content.json` could not be
downloaded.

## Installing on cPanel

Everything below is File Manager work — no SSH needed.

**1. Upload.** In cPanel → File Manager, go to `/home/leofoundationusa` (the
account's home, the folder *containing* `public_html`). Upload
`leo-foundation-php.zip` there and Extract.

**2. Clear the placeholder.** A new cPanel account ships a default
`public_html/index.html`. Apache serves `.html` before `.php`, so **delete it**
or the site will not appear.

**3. Set the admin password.** Copy `leo-app/config.example.php` to
`leo-app/config.php` and edit it:

```php
return [
    'admin_password' => 'a long passphrase, not a word',
    'session_secret' => '',
];
```

Leaving `admin_password` empty switches the admin area off entirely — the public
site still serves.

**4. Check the PHP version.** cPanel → MultiPHP Manager, set the domain to PHP
8.0 or newer. The code uses typed properties and `match`, so 7.x will not run.

**5. Permissions.** Folders `755`, files `644`. `leo-app/data/content.json` must
be writable by the account user — that is the default, and it is the only file
the site ever writes.

**6. Visit the site**, then `/admin` and sign in.

## Before pointing the domain

`leofoundationusa.org` still resolves to the old WordPress host, so test here
first and only move DNS once the pages look right.

**The `/~username/` temporary URL works.** The site derives its mount point from
where `index.php` sits, so it runs correctly at

```
http://160.153.181.93/~leofoundationusa/
```

with every link, stylesheet and redirect carrying the prefix. Nothing to
configure — it also means the site can be installed in any subdirectory, and
`base_path` in `config.php` overrides the derived value if a host reports
`SCRIPT_NAME` unhelpfully.

The subdomain-style temp URL (`username.server-hostname`) does **not** exist on
this server — there is no wildcard DNS on the hostname, so it returns NXDOMAIN.

A `hosts` file entry on your own machine pointing `leofoundationusa.org` at the
server IP is the closest thing to a production test, since it exercises the real
domain at a root path. Redirects for the old
WordPress URLs are already in `public_html/.htaccess`:

| Old | New |
| --- | --- |
| `/available-scholarships/` | `/scholarships` |
| `/scholarship-recipients/` | `/recipients` |
| `/scholarship-faqs/` | `/faq` |

Add any others you find in the WordPress export before switching over.

Once the certificate is issued (cPanel → SSL/TLS Status → Run AutoSSL),
uncomment the HTTPS redirect at the top of `.htaccess`.

## Running it locally

```bash
cd php
php -S localhost:8000 -t public_html router.php
php test/run.php
```

`router.php` exists only for the built-in server — Apache uses `.htaccess`.

## Backups

`leo-app/data/content.json` is the entire site. Download it from File Manager
and you have a complete backup; upload it again and the site is restored. Worth
doing after each round of scholarship edits.

## Notes on the seeded content

Built without access to the live site — the environment it was written in blocks
`leofoundationusa.org` — so the scholarships, amounts and criteria were
reconstructed from public sources and **need checking before launch**. The three
recipient records are placeholders saved as drafts, so they never appear
publicly. Replace them with real students and untick Draft.
