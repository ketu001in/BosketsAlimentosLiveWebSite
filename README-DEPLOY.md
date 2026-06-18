# Bosket's Alimentos — Deployment Guide (Hostinger)

> **A world of truly fusion food · 100% Veg**

A complete PHP 8 + MySQL community website: recipes, food-story walls, buddy system,
forum, notifications and a full admin panel. No frameworks, no build step — upload and run.

**Brand identity:** "Artisan seal" logo (BA monogram, master file in
`assets/img/logo.svg`), "Coastal mint" palette (sea green `#3fa796` /
deep teal `#23756a` / dusk coral accent `#e2856e`), Playfair Display headings +
Inter body text. All theme colors live in the `:root` block at the top of
`assets/css/style.css`. The Recipes menu is a mega-menu — visitors can browse
recipes by Category, Cuisine and Origin directly from the navigation. The site
also has About Us (`about.php`) and Contact Us (`contact.php`) pages; contact
messages are stored in the database (Admin Panel → Messages) and emailed to
the first administrator. Admins can create members manually, change roles and
reset any member's password from Admin → Users. When signed in, the main menu
shows "<display name>'s Wall"; the buddy feed lives in the avatar menu.

---

## 1. What you need

- Any Hostinger **shared / Premium / Business** plan (hPanel) with PHP **8.0+**
- A domain pointed at the hosting (or use the temporary Hostinger URL)

## 2. Create the database (hPanel)

1. hPanel → **Databases → MySQL Databases**
2. Create a database, a user and a strong password. Note all three
   (they look like `u123456789_boskets`).

## 3. Configure the site

Open **`config.php`** and set:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_boskets');   // your database name
define('DB_USER', 'u123456789_boskets');   // your database user
define('DB_PASS', 'your-strong-password');
```

Optionally set `SITE_URL` to `https://yourdomain.com` (auto-detected if left empty).

For password-reset emails, optionally set `MAIL_FROM` to an address on your own
domain (create it in hPanel → **Emails**) so messages don't land in spam.
Left empty, it defaults to `no-reply@yourdomain.com`.

## 4. Upload the files

**Option A — File Manager:** zip this whole folder, upload the zip into
`public_html`, extract, and make sure the files sit **directly** in `public_html`
(i.e. `public_html/index.php` exists).

**Option B — FTP:** connect with FileZilla (hPanel → Files → FTP Accounts) and
upload everything into `public_html`.

## 5. Run the installer

1. Open `https://yourdomain.com/install.php`
2. It verifies the database connection, then asks for your **admin account**
   (username, email, password) and whether to seed **demo content**
   (4 sample members + 8 fusion recipes + forum topics — recommended so the
   site looks alive on day one).
3. Click **Install**.
4. **Delete `install.php` from the server immediately afterwards**
   (File Manager → right-click → Delete). The installer refuses to run twice,
   but don't leave it there.

## 6. Check PHP settings (recommended)

hPanel → **Advanced → PHP Configuration**:

- PHP version: **8.1+**
- `upload_max_filesize`: **30M** (covers the 25 MB step-video limit)
- `post_max_size`: **64M** (a recipe post can carry several files at once)
- `max_execution_time`: 120

(The included `.htaccess` already requests these; the hPanel values win if they differ.)

## 7. Turn on HTTPS

hPanel → **Security → SSL** → install the free certificate, then enable
**Force HTTPS**. Done.

---

## Day-to-day administration

Sign in with your admin account → avatar menu → **Admin Panel**:

| Section | What you can do |
|---|---|
| Dashboard | Site statistics, newest members & recipes |
| Users | Ban/unban, promote to admin, delete users with all their content |
| Content | Feature recipes on the homepage ⭐, hide/restore/delete recipes & comments |
| Forum | Create/delete boards, hide/delete topics |
| Master Lists | Rename, merge or delete ingredients, categories, cuisines and origins |

**Demo accounts** (if you seeded demo content) all use password `Demo@1234`:
`maya_fusion`, `leo_cocina`, `sakura_spice`, `arjun_tadka`.
Delete them anytime from Admin → Users — the master lists stay.

## How the site works (feature map)

- **Membership** — email + password signup (instant), avatar upload (≤ 5 MB),
  profile with bio, password change, forgot-password reset by email
  (1-hour token link), full account self-deletion.
- **Walls & privacy** — recipes and the forum are public; wall posts (food stories)
  are visible **only to buddies**, Facebook-style.
- **Buddy system** — send a BUDDY REQUEST → the other member gets a 🔔 notification
  → accept/reject → accepted buddies appear in both buddy lists and see each
  other's posts in **My Feed**.
- **Recipes** — 5-section "Post a New Recipe" form: (1) name + main photo + story,
  (2) ingredients with quantities, (3) category/cuisine/origin, (4) step-by-step
  method with optional photo or ≤ 25 MB video per step, (5) verdict/trivia.
  Typing 3 letters in ingredient/category/cuisine/origin fields searches the
  master list; unknown entries are added automatically for everyone.
- **Engagement** — reactions (👍 ❤️ 😋 🤩), comments everywhere, share = repost
  to your wall with a note + WhatsApp / Facebook / X / copy-link.
- **Forum** — boards (predefined + user-creatable), topics, replies, reactions.
- **Notifications** — buddy requests/accepts, reactions, comments, shares.
- **SEO** — Open Graph / Twitter-card tags on every page (recipe pages include
  the dish photo), `robots.txt`, and a live XML sitemap at `/sitemap.php`.
  After deployment, submit `https://yourdomain.com/sitemap.php` in Google
  Search Console and put that absolute URL on the `Sitemap:` line of `robots.txt`.

## File map

```
config.php              ← the only file you must edit
install.php             ← one-time installer (DELETE after use)
index.php               ← homepage
register/login/logout   ← auth
forgot-password.php     ← email a reset link
reset-password.php      ← set a new password from the link
settings.php            ← account management
profile.php             ← profile + wall + buddy list
wall-post.php           ← single wall post (notification landing page)
buddies.php             ← requests & member search
feed.php                ← buddy feed
notifications.php       ← notification centre
recipes.php / recipe.php / post-recipe.php
forum.php / forum-topic.php / new-topic.php
sitemap.php / robots.txt ← SEO
api/                    ← AJAX endpoints (reactions, comments, buddies, typeahead…)
admin/                  ← admin panel
includes/               ← bootstrap, helpers, layout (HTTP-blocked)
assets/                 ← CSS + JS
uploads/                ← user media (script execution blocked)
```

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Could not connect to the database" | Re-check the four `DB_*` values in `config.php`; host is `localhost` on Hostinger shared plans |
| Uploads fail for big files | Raise `upload_max_filesize` / `post_max_size` in hPanel → PHP Configuration |
| Styles missing / links broken in a subfolder install | Set `SITE_URL` in `config.php` to the exact base URL |
| 500 error after upload | Confirm PHP version is 8.0+ in hPanel → PHP Configuration |
| Password-reset emails missing / in spam | Set `MAIL_FROM` in `config.php` to a mailbox on your own domain (create it in hPanel → Emails) |
