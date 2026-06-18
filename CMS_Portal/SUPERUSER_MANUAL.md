# Bosket's Alimentos — CMS Portal · SuperUser Manual

A practical guide to the content-management portal that controls the live
website. Keep this for reference.

---

## 1. What the CMS is (and isn't)

The **CMS Portal** is a separate admin area that lets you (the **SuperUser**)
add your own pages and menu items to the website, moderate what members post,
and change the site's theme and fonts — **without touching any of the built-in
pages, menus, or design files**.

- It lives at **`/CMS_Portal/`** on your site
  (locally: `http://127.0.0.1:8088/CMS_Portal/`; live: `https://yourdomain.com/CMS_Portal/`).
- It shares the **same database** as the website, so anything you publish is live instantly.
- It is a **separate login** from the main site. Signing into the CMS does **not**
  sign you into the public site, and vice-versa.

**The SuperUser is your existing site Admin account.** There's no separate
CMS user to create — you log in with your admin username/email + password.

**What the SuperUser cannot do:** edit or delete the website's original menus
(Recipes, Forum, About Us, Contact Us…), its original pages, or its design
files. Your additions live *alongside* the originals and never overwrite them.

---

## 2. Signing in

1. Go to **`/CMS_Portal/login.php`**.
2. Enter your **admin** username (or email) and password.
3. Only administrator accounts are accepted. A normal member cannot log in here.

**Forgot your password?** Click *Forgot your password?* on the login screen,
enter the admin email, and you'll get a reset link (valid 1 hour). This resets
the same password you use on the main site.

**Signing out:** use *Sign out* (top-right). It only ends the CMS session.

---

## 3. Dashboard

The landing screen shows:
- counts of your **published pages** and **CMS menu items**,
- **new recipes / comments in the last 7 days** (a heads-up for moderation),
- live totals of recipes, comments, wall posts and forum topics,
- your most recent moderation actions.

Use the **Quick actions** buttons to jump straight to any task.

---

## 4. Pages

**Pages → New page.**

| Field | What it does |
|-------|--------------|
| **Title** | The page heading and browser title. |
| **URL slug** | The web address (`…/page.php?slug=your-slug`). Leave blank to auto-generate from the title. |
| **Content** | A visual (WYSIWYG) editor — type and format like a document. |
| **Status** | *Draft* (hidden) or *Published* (live). New pages start as draft. |
| **Who can see it** | *Public* (everyone) or *Members only* (must be logged into the website). |
| **SEO description** | Optional ~160-character summary for search engines / link previews. |

### The editor toolbar
- **B / I / U** — bold, italic, underline
- **H2 / H3** — headings · **¶** — normal paragraph
- **• / 1.** — bulleted / numbered lists · **"** — quote
- **🔗** — insert a link (paste any `https://…` or an internal path like `/recipes.php`)
- **🖼** — upload and insert an image (max 5 MB)
- **✕** — clear formatting

Click **Save** to keep editing, or **Save & close** to return to the list.
For safety, page content is automatically cleaned of unsafe code when saved.

### Publishing checklist
1. Write the page, set **Status = Published**, choose visibility, **Save**.
2. (Optional) add a **menu item** that links to it (see next section), so
   visitors can find it.

### Deleting a page
Open the page → **Delete page**. Any menu items linking to it are automatically
unlinked (they won't show a broken link).

---

## 5. Menus

**Menus.** Add your own items to the **Top navigation** and/or the **Footer**.
The site's built-in menu items are never changed — yours appear next to them.

To add an item (right-hand form):
- **Label** — the text shown in the menu.
- **Location** — *Top navigation* or *Footer*.
- **Parent** — leave as *None* for a top-level item, or pick a top-level item to
  make this a **dropdown sub-item** under it (one level of submenus).
- **Links to** — *A CMS page* (pick one of your published pages) **or** *A custom
  URL* (e.g. `https://instagram.com/...` or an internal path like `/forum.php`).
- **Open in a new tab** — handy for external links.
- **Visible on the site** — untick to hide an item without deleting it.

Each item row has:
- **▲ / ▼** — reorder within its group,
- **Edit**, **Hide/Show**, **Delete** (deleting a parent also removes its sub-items).

> Only **published** pages appear on the live site. If you link a draft page,
> the item stays hidden until you publish that page.

---

## 6. Moderation

**Moderation.** Posts from members go live immediately (post-moderation). Review
them here across four tabs: **Recipes, Comments, Wall posts, Forum topics**
(comments include forum replies).

Per item:
- **Hide** — removes it from the public site immediately. **Reversible.**
- **Restore** — puts a hidden item back live.
- **Delete** — permanently removes it (and its comments/reactions). **Cannot be undone.**

Every action is recorded in the dashboard's *Recent moderation* log.

> The main-site **Admin panel** still works exactly as before — you can moderate
> from either place; they act on the same content.

---

## 7. Appearance (theme & fonts)

**Appearance.** These settings restyle the **public website** (the CMS portal
keeps its own fixed look).

- **Theme** — *Light*, *Dark*, or *System* (System follows each visitor's own
  device setting). Your choice is the site-wide default.
- **Visitor light/dark toggle** — when on, visitors see a small ☀️/🌙 button in
  the header and can switch the theme for themselves; their choice is remembered
  on their device. Your default is what everyone sees first.
- **Fonts** — choose a professional heading+body pairing applied across the
  whole site.

Click **Save appearance**, then **Preview site** to see it live.

---

## 8. Going live (Hostinger)

The CMS is part of the same site, so it deploys with it. After uploading the
site and running the installer:

- The CMS is reachable at **`https://yourdomain.com/CMS_Portal/`**.
- Log in with your admin account.
- The CMS database tables are created automatically the first time you open the
  portal — nothing extra to install.

Keep the portal URL private-ish (it's admin-only and not linked from the public
site), and make sure your admin password is strong.

---

## 9. Quick reference

| I want to… | Go to |
|------------|-------|
| Add a page | Pages → New page |
| Put a page in the menu | Menus → Add item → links to *A CMS page* |
| Add an external link to the nav/footer | Menus → Add item → *A custom URL* |
| Hide a member's post/comment | Moderation → relevant tab → Hide |
| Make the site dark | Appearance → Theme → Dark → Save |
| Change the site's fonts | Appearance → Fonts → pick a pairing → Save |
| Reset my password | Login screen → Forgot your password? |

---

*The CMS only **adds** to your website. Your original pages, menus, recipes,
forum and design are always safe.*
