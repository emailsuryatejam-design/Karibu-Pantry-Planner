# Karibu Pantry Planner — project memory

PHP/MySQL PWA for safari-camp kitchens. **Chefs** plan meals → generate ingredient
requisitions; **storekeepers** fulfill (mark sent); chefs confirm receipt; day-close
reconciles unused stock. One app, role-based views. Hosted on Hostinger.

## Stack
- PHP 8.3, MySQL/MariaDB (`u929828006_Pantryplanner`)
- PWA: `service-worker.js` — **cache-first** for static (`assets/app.js`, css, icons),
  **network-first** for `.php` / `/api/`.
- dompdf 2.0 for PDFs (no flex/grid in dompdf — use tables).
- Gmail SMTP via PHPMailer.
- No build step. Vanilla JS, inline `<script>` blocks in `pages/*.php`, Tailwind via CDN classes.

## Deploy (Hostinger)
- Connection details + password: see `CLAUDE.local.md`.
- Remote root: `domains/palegoldenrod-coyote-386848.hostingersite.com/public_html`
- Live URL: https://palegoldenrod-coyote-386848.hostingersite.com
- Local working copy: this folder (`smart req_ karibu`), pulled from production.

**Deploy workflow (always, in order):**
1. `php -l <file>` for PHP; `node --check` for JS. For `.php` with inline JS, extract the
   `<script>` block and neutralize `<?= ?>`/`<?php ?>` (replace with `0`) before `node --check`.
2. Timestamped remote backup → `_bak/YYYYMMDD-HHMMSS/` before overwriting.
3. `rsync -az` each file to its exact path (mind trailing-slash discipline).
4. Verify on server with `grep`; check live behavior.
5. **If `assets/app.js` changed, bump `CACHE_NAME` in `service-worker.js`** (currently
   `karibu-v9`) or clients keep the cached old JS.

## Domain model
- Requisition lifecycle: `draft → processing → submitted → fulfilled → received → closed`.
- `requisition_lines.is_staple`: `1` = manually-added staple (rice, oil, salt…), `0` = recipe menu item.
- `requisition_lines.is_primary` (on recipe ingredients): chef's orange "tap to toggle".
  Toggled **off (is_primary=0) ⇒ not ordered at all** — enforced in `save_dish_lines`.
- Quantities per line: `order_qty` (chef requested) → `fulfilled_qty` (store sent) →
  `received_qty` (chef received) → `unused_qty` (day-close).
- `api/store-orders.php` is the **storekeeper's view over the SAME `requisitions`/
  `requisition_lines`** — `order.id` IS the requisition id. Not a separate table.
- **Draft auto-creation (2026-06-24):** `page_init` + `auto_create_for_date` only create
  drafts for meals a kitchen actually uses = core (breakfast/lunch/dinner) ∪ any meal it has
  generated before (`mealsToAutoCreate()`). Other meal types are started on demand via the
  `ensure_session` action (the dashed **"+ start meal"** chip on dashboard & requisition pages).
  Before this, page_init pre-created a draft for ALL active meal types every visit → 645 empty
  "phantom" drafts piled up (e.g. sundowner/bush_dinner at camps that don't serve them).
  Those were soft-deleted (reversible; undo manifest in `reports/phantom_purge_manifest_*.json`).

## Security hardening (2026-08-14)
- **Web-exposed script audit.** `.htaccess` now denies every non-entry-point PHP by name pattern:
  `^(config|auth|vapid-keys|setup|mailer|pdf|seed-.+|cron-.+|notify-.+|migrate-.+|test-.+|debug-.+|_.+)\.php$`
  — only real entry points stay reachable (index.php, app.php, admin-login/forgot/reset.php, api/*.php,
  pages/*.php by basename). Also `RewriteRule ^_bak/|^db-backups/|^reports/|^\.git/ - [F,L]`.
- **The big hole was `seed-menus.php` (+ `seed-weekly-menu.php`)** — destructive DB reseeders whose own
  docblock said "run via URL", no auth, reachable (200). They (and cron-*/notify-*) now have a
  `if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }` guard AS WELL — defense in depth, so they
  can't run over the web even if .htaccess is bypassed. CLI runs (SSH/GitHub Actions) still work.
- Removed leftover `_cleantest.php` from the web root. `_migrate_staples.php` etc. already removed.
- Verified live: all those scripts now 403/404; index/app/admin-login/assets/api still serve.
- **Already OK (left as-is):** login rate-limit 5/15min per user+IP (config.php `checkLoginRateLimit`);
  session cookies HttpOnly+SameSite=Lax+Secure; CSP + HSTS + X-Frame-Options headers; `.env`/config/auth
  blocked; PDO prepared statements throughout; `reset_all_orders`/`reset_orders` disabled (403).
- **Still worth doing (not urgent):** rotate Joyce's admin password (shared in plaintext during setup).

## SAP stock integration — daily snapshot (2026-08-16)
- **Why:** SAP (Rama's ERP) serves live warehouse stock via `POST http://196.61.9.142:8588/api/ItemListDetails`
  (no auth, reachable from the Hostinger server; NOT from arbitrary hosts). **Item codes match ours 1:1 —
  `items.code` = SAP `itemCode`** (verified 100/100 on a page, names identical), so we join directly, no mapping
  table. The 72 old `CHK001`-style seed items don't match; the ~2,979 real items carry SAP numeric codes in `code`.
- **The endpoint is stock-ONLY — NO dates.** Fields: `onHand,onOrder,isCommited,avaibale,price` + per-warehouse
  `onHand`. So movement over time can't be read from it — we snapshot daily ourselves; **day-over-day delta = the
  movement**. Backfilling past movement by posting-date needs a *different* transactions/OINM read endpoint from
  Rama (not exposed). `GoodsIssue` exists but is a **WRITE — never call it**. Etiquette: one full pull/day (~13 req).
- **`sap-snapshot.php`** (CLI-only guard + web-blocked via `.htaccess` `sap-.+`): pulls the whole catalogue
  (~1,285 items, 13×100 pages) into three self-created tables — `sap_stock`(snapshot_date,item_code,whs_code,
  on_hand; only non-zero rows), `sap_stock_meta`(per item/day: name,grp,uom,onHand,onOrder,committed,available,
  price), `sap_snapshot_log`(one row/run: pages,items,wh_rows,ok,note). **Commits per page** so a dropped
  connection loses nothing — Hostinger **reaps detached processes** (nohup AND setsid both get killed) and long
  attached SSH sessions can drop ~90s in, so per-page commit is the only reliable path. Idempotent upserts;
  **skips if that day already has an `ok=1` snapshot** (staggered crons never double-pull). Flags: `--force`,
  `--date=YYYY-MM-DD`, `--quiet`.
- **Schedule:** GitHub Actions `sap-stock-snapshot.yml` — two staggered daily fires (03:10 + 06:10 UTC; the 2nd
  skips if the 1st already stored today's), SSH + 3× retry, mirrors `missed-meal-alerts.yml`. **First snapshot:
  2026-08-16.**
- **Warehouse→camp map:** Lions Paw = `LP+LPBAR+INTLP` · River = `RC+RCBAR+INTRC` · Sametu = `SAMETU+SMBAR+INTSMT`
  · Tarangire = `TG+TGBAR+INTTG` · Woodlands = `WOODLAND+WLBAR+INTWL` · shared source = `HO` (also seen: `Raha`,
  `SUPPLIER`, `INTHO`).
- **Next:** admin "Daily Audit" page over these snapshots — Tier-1 flags (ordered-but-no-stock, over-order,
  stuck-in-transit, anomalies) work from day 1; **movement reconciliation** (SAP delta vs app fulfilments) lights
  up once ≥2 days exist. Cost/consumption in TZS still needs Rama.

## Admin: Camp Usage Scorecard (2026-08-14)
- `pages/admin-usage.php` (nav: Admin → **Operations → Usage Scorecard**, `app.php?page=admin-usage&days=7|14|30`).
  Live, server-rendered, admin-only. Per-camp full lifecycle over the last N completed days (core meals):
  Ordered (of N×3), On-time %, Reaches-store %, Fulfilled %, Closed %, Stuck (processing count). Green ≥80 /
  amber 50–79 / red <50. On-time compares `created_at + INTERVAL <offset> HOUR` (offset = DB→EAT, computed
  live from `NOW()`, ~3) against `TIMESTAMP(req_date, cutoff)` where cutoffs are 08:00/12:00/18:00. Dormant
  camps shown explicitly. Registered in `$adminPages` + `$opsPages`. Sibling of admin-attendance.php.

## Admin: Attendance / usage calendar (2026-07-30)
- `pages/admin-attendance.php` (nav: Admin → **Operations → Attendance**, `app.php?page=admin-attendance&days=7|14|30`).
  Server-rendered grid: rows = active kitchens (excl Demo 6), cols = days, each day = 3 dots B/L/D.
  green=ordered (submitted→closed) · **amber=processing (built, never Submitted to Store)** · red=missed ·
  grey=not due yet (today before the meal cutoff). Right column = usage % (green ÷ due; today's not-yet-due
  excluded). Amber instantly exposes camps like Tarangire that build orders but never submit them.
  Registered in `$adminPages` + `$opsPages`. Only breakfast/lunch/dinner tracked.
- Gap noted: `admin-login.php` does NOT update `users.last_login` — admin login times aren't recorded
  (chef/store PIN logins aren't either). One-line fix if per-user login history is wanted.

## Admins & no-hard-delete rule (2026-07-29)
- **Global admins** live in `users` (role='admin', `kitchen_id`=NULL, login via `admin-login.php`
  with email + `password_hash`). Per-kitchen admins have a `kitchen_id` and log in by PIN.
  Provisioned 2026-07-29: **Joyce** (id 35, joyce@spanishtiles.co.tz) — note: **external domain**.
- **HOUSE RULE — no hard deletes.** Surya's standing instruction: never issue hard `DELETE`; use
  soft-delete (`deleted_at`/`deleted_by`). Most of the app already does (84 `deleted_at` sites), BUT
  these hard-`DELETE` paths still exist and are **NOT yet fixed**:
  - ⚠️ `reset_all_orders` (api/requisitions.php ~2532) — admin-only, **wipes ALL** requisitions/lines/
    dishes/notifications. Nuclear.
  - ⚠️ `reset_orders` (api/items.php ~172) — admin-only, wipes ALL requisitions + menu tables.
  - `chef_remove`/custom-dish removal (requisitions.php ~1760) + menu-plan/set-menus config deletes —
    routine hard-deletes, lower stakes.
  - push_subscriptions deletes = fine (transient tokens). Migration dedup (req.php ~473) = one-time.
  - **DB backups:** `mysqldump` works on the server (PHP `exec` is disabled, so run it from the shell
    with a 0600 `--defaults-extra-file`). Backups saved to `~/db-backups/` on the server + pulled to
    local `reports/db-backups/` (gitignored). First backup: 2026-07-29.

## Staples are a SEPARATE order (2026-08-01)
- **Bulk staples (salt/sugar/butter) live on their own daily order**, not on a meal. `meals='staples'`,
  one per (kitchen,date), supp 0, created on demand by `ensureStaplesReq()` (api/requisitions.php).
  Excluded from `mealsToAutoCreate()` and from the Orders **menu tab** (`code !== 'staples'`), shown only
  in the **Staple tab**. Submitting/fulfilling staples never touches a meal — this fixes the "submit
  staples → lunch locks with 0 dishes → empty menu" bug at the root.
- **`add_line_to_order` now takes `intent`:** `'staple'` (default) → routed to the staples order
  (is_staple=1); `'meal_extra'` → a one-off item that STAYS on the meal (is_staple=0, source_recipe_id
  NULL). The chef Orders menu-tab "+" is now "Add extra item to this meal" (gated on the meal being
  generated, `ordCanAddMealExtra` = status processing/submitted); the Staple-tab "+" and top-bar "+" add
  bulk staples. Empty meal card → "Plan on Home" link (no more manual meal-building from Orders).
- **Menu regeneration preserves meal extras:** `save_dish_lines` now soft-deletes only dish-generated
  menu lines (`is_staple=0 AND source_recipe_id IS NOT NULL`), leaving manual meal extras intact.
- `page_init`/`day_summary`/`list` now filter `deleted_at IS NULL` so archived orders never show.
- **Data migration DONE (2026-08-05):** moved 3,775 historical is_staple=1 lines off meals onto 144
  per-(kitchen,date) staples orders; archived 58 staples-only phantom meals (soft, Recycle Bin). Total
  staple qty preserved exactly (22,773.3 → 22,773.3), 0 staples left on meals. Transactional with
  verify-or-rollback (first attempt self-rolled-back on a too-narrow check — fixed to compare total
  staple qty everywhere). Undo manifest: reports/cleanup/staples_migration_2026-08-05.json; backup:
  db-backups/pantryplanner-20260805-071607.sql.gz. Plan: ~/.claude/plans/calm-roaming-glade.md.

## No back-dating (2026-07-16)
- **Chefs can only create/change orders for today or later.** `guardBackdate()` at the top of
  `api/requisitions.php` blocks ~18 write actions (create, ensure_session, lock_menu, submit_order,
  add_*/update_line/toggle_line/save_dish_lines…) when the order's `req_date < date('Y-m-d')` (EAT).
  It resolves the date from `req_date` | `requisition_id` | `parent_id` | `line_id`.
- **Admins are exempt** — the escape hatch for genuinely fixing an old order.
- **NOT guarded** (these legitimately happen after the order's date): `fulfill`, `confirm_receipt`,
  `close`, `close_with_unused`, `update_unused`, `day_close_*`, `cancel_order`, `admin_*`, all reads.
- `page_init` + `auto_create_for_date` **no longer auto-create drafts on past dates** — browsing back
  through the calendar used to silently create back-dated drafts (that's how ~250 stale past-dated open
  orders accumulated: Lions Paw 78 oldest 26 Mar, Tarangire 78, River 52, Sametu 39). Viewing history
  still works (returns existing orders, `created: 0`).
- UI mirrors it: `ordIsPastDay()` / `dbIsPastDay()` force read-only + an amber "This day has passed —
  view only" note on Orders and Dashboard. Server is authoritative; the UI just avoids error toasts.
- **Known consequence:** Tarangire was actively back-filling (21 back-dated orders in 60d; on 14 Jul they
  created lunch+dinner for 8–12 Jul). That workflow is now blocked — they must order on the day or ask an admin.
- **Caveat:** the UI's "today" comes from the *browser* (`todayStr()`), the block from the *server* (EAT).
  A tablet with a wrong clock/timezone could show a day the server rejects. Camps are EAT so this is
  narrow, but a badly-set tablet = "that day has passed" on what looks like today.
- Forward-dating is untouched — planning ahead is normal (270 forward-dated orders in 60d).

## Printing — ONE canonical function (do not fork this)
**All print buttons across the app call `printOrder(reqId, kitchenName, skipDaySuggest)`**
in `assets/app.js`. It reads `api/requisitions.php?action=get`, splits lines into a menu
table + a separate **"Staple items" section** (via `is_staple`), and renders signatures.
- `skipDaySuggest=true` suppresses the "print whole day to save paper?" nudge — used where
  the user explicitly chose a single meal (Orders page) or the page already has a whole-day
  button (Store Orders).
- Whole-day print: `printWholeDay(date, kitchenId, name)` (one consolidated staples list for
  the day). PDF equivalent: `pdf.php` `generateDayPDF()` + `api/...action=day_pdf`.
- **2026-06-19: removed `printStoreOrder()`** (a divergent store-only template that ignored
  staples). Store Orders now uses `printOrder` like every other page. **Never re-introduce a
  second print template — staple handling will drift.** If a role needs different print
  output, branch *inside* `printOrder`, don't fork it.

## Unit translation — recipe units → store/purchase units (2026-06-29)
- `items.pack_size_g` = base sub-units (g or ml) in ONE purchase unit (e.g. Heinz 415G tin = 415).
  Auto-derived from item names for 359 items; the rest (fresh produce / unlabelled) are NULL and
  fall back to kg. To add more: set `items.pack_size_g`.
- `toPurchaseUnit($qty,$curUom,$itemUom,$packSizeG)` in `api/requisitions.php` converts a computed
  order qty into the item's buying unit: kg/ltr kept; counts kept (eggs/apples never "converted");
  weight/volume ÷ pack_size_g → whole packs (round up); no pack size → kg fallback.
- Applied at the INSERT step of `save_dish_lines` AND `add_custom_dish`. **Recipes are never
  altered** — chef plans in grams, the order/store speak tins/bottles/pcs. Write-time only:
  new/regenerated orders convert; old orders keep their stored units.

## Read-time vs write-time changes (important mental model)
- **Read-time** (print/format/day-close display): apply to ALL orders incl. old ones.
- **Write-time** (unit normalization grams→kg/ml→ltr; Phase-3 running-stock balance): only
  affect newly generated/regenerated orders — old orders keep their stored values.

## Admin
- Kitchen app login: PIN-based, per staff. Global admin (Karibu) + per-kitchen admins.
- Admin portal: `admin-login.php`, forgot-password flow `admin-forgot.php` + `admin-reset.php`.
- `cron-daily-req-email.php` emails the previous day's requisition sheet.
- **Automations (2026-07-15):**
  - `cron-daily-camp-notes.php` — per-camp ops report. `... html` = color-coded HTML page; no arg = JSON.
    Powers the local Claude routine `karibu-daily-camp-notes` (saves reports/daily/YYYY-MM-DD.html).
  - `notify-missed-meals.php <breakfast|lunch|dinner> [--dry] [--to=x]` — if a camp hasn't placed that
    meal's order, emails the kitchen's reception + manager. Excludes Woodlands(2) + Demo(6).
    **Bobby removed from cc (2026-07-23).** Run by **GitHub Actions** `missed-meal-alerts.yml`.
    - **⏸ ARCHIVED / OFF (2026-07-24):** workflow **disabled** via `gh workflow disable "Missed-meal alerts"`
      — no scheduled runs fire. Code (yml + script) is kept in the repo, not deleted. To bring it back:
      `gh workflow enable "Missed-meal alerts"`. Reason: user shelved the feature. A known gap if it's
      re-enabled: it counts a `processing` order as "placed", so a camp stuck at processing (e.g. Tarangire,
      chef never taps Submit to Store) is NOT chased — consider requiring `status='submitted'` before reviving.
    - **Timing (2026-07-23):** GitHub's scheduler runs 1–3h late, so alerts were landing ~11am. Now
      each meal has **3 staggered crons** starting early (breakfast 03/04/05:10 UTC, lunch 07/08/09:10,
      dinner 13/14/15:10). The script only **sends after the meal's cutoff** (`$SEND_AFTER` = 08:10/12:10/
      18:10 EAT) and **once per camp/meal/day** (file-cache key `missedmeal:{date}:{meal}:{kid}`, 20h TTL).
      So early fires are skipped, whichever fire lands post-cutoff sends near on-time, no dupes.
    - **Reliability:** the SSH step **retries 3× (30s apart) and fails loudly** — a brief timeout used to
      silently skip a meal (happened 19 + 21 Jul). `--dry`/`--to=` bypass the time-guard for previewing.
    - Manual test: `gh workflow run "Missed-meal alerts" -f meal=lunch -f dry=true`.
- Repo: github.com/emailsuryatejam-design/Karibu-Pantry-Planner (branch `main`). **Synced to production
  2026-07-15.** Deploy is still rsync from the local folder → server; commit + push after deploying to
  keep git in sync. `CLAUDE.local.md` (secret), `reports/`, `vendor/`, `_*.php` are gitignored.
- Order-submit email alerts go to per-kitchen notification recipients (demo kitchen → global admin).

## Key files
- `api/requisitions.php` — central API (generate, lock, submit, day-close, day-print/pdf, add-line).
- `api/store-orders.php` — storekeeper view/actions over requisitions.
- `assets/app.js` — all client logic (cache-first; bump SW on change).
- `pages/` — `dashboard.php` (chef plan), `requisition.php`, `orders.php` (chef orders),
  `store-orders.php`, `store-dashboard.php`, `review-supply.php`, `store-history.php`,
  `day-close.php`.
- `pdf.php` — PDF generation.

## Phrasebook ("when user says X")
- "stores can't print staples" → it's the print function; ensure that page uses `printOrder`.
- "kgs converted to milligrams" → unit normalization issue in `save_dish_lines` (item-catalog uom).
- "guest counter reset" → per-meal count should inherit last-set value, not reset to default.
- "added after submitting didn't reach store" → `add_line_to_order` (is_staple=1, item_id nullable).
- "too many drafts" / "phantom / empty drafts" → was page_init pre-creating a draft for every
  meal type daily. Fixed 2026-06-24 (`mealsToAutoCreate` + `ensure_session`). Not chef behaviour;
  empty drafts carry 0 lines and never affect procurement. Unique key
  `(kitchen_id, req_date, meals, supplement_number)` prevents true duplicate/counter orders.
- "add a dish not in the recipe book from orders" / "custom dish" → `add_custom_dish` action
  (2026-06-29). Orders page editable orders show a dashed **"Add a new dish"** button → modal
  (name + serves N + ingredients). It saves a recipe (owned by chef, category = the order's
  meal type) AND attaches+generates lines on the order. Reuses an existing same-name recipe
  (no duplicate); won't double lines if the dish is already on that order. Recipes never
  altered by ordering. `ensure_session` = the on-demand single-meal draft creator.
- "give stores access to recipes / edit orange button" → done 2026-07-09. `recipes` added to
  `$storePages` + a store bottom-nav item (app.php). Storekeepers get **view + the orange
  on/off toggle only** — API guard at top of `api/recipes.php` blocks every other action
  (add/remove/edit-qty/delete/save) with 403; UI hides those controls via `R_CAN_EDIT`
  (`R_ROLE` in pages/recipes.php). `toggle_primary` has no role gate so it works for stores.
- "can't change ingredient qty" / "edit quantity after adding" → `update_ingredient` action in
  `api/recipes.php` + editable number input per ingredient row in `pages/recipes.php`
  (`rUpdateIngQty`). Added 2026-07-04. Before, qty was display-only (only add/toggle/remove existed).
- "off items still ordered" → root cause: each chef has their own recipe COPY with independent
  toggles; the shared camp order may use a different copy where the item is ON. **Fixed
  2026-07-09:** `toggle_primary` now syncs the on/off choice CAMP-WIDE (same item, all same-name
  copies at that kitchen, matched by `item_id` else `item_name`). Toggle once → sticks everywhere.
  Did NOT run a bulk backfill of the 86 existing disagreements — dry-run showed it would wrongly
  turn OFF core ingredients (potatoes in potato soup, etc.). Existing mismatches are fixed by a
  human toggling the specific item once.
- "recipe add/edit scattered / too many fields" → **Fixed 2026-07-09.** Recipe form trimmed to
  Name · Meal type · Serves · Method · Packed-toggle (dropped cuisine/difficulty/prep/cook/notes);
  detail view decluttered (`rOpenForm`/`rSaveRecipe`/`rLoadDetail` in pages/recipes.php). Ingredient
  qty is inline-editable (`update_ingredient`).
- "toggle an item off from orders" / "chef stopped taking an ingredient from store" → **order-line
  orange toggle (2026-07-15).** Orange dot per menu row in `pages/orders.php` `ordRenderEditableLines`
  → `ordToggleLine()` → `toggle_line` action in `api/requisitions.php`. **Permanent, like the Recipes
  dot:** OFF sets `requisition_lines.status='rejected'` (this order — qty preserved, store's `mark_sent`
  skips it) AND flips `recipe_ingredients.is_primary=0` **camp-wide** for that item across the order's
  dishes (future orders). ON reverses both. One-time skip = just set qty to 0 (unchanged). The chef's
  Orders page passes `&action=get&...&include_off=1` so toggled-off lines stay visible (greyed,
  strikethrough) to switch back on; every other `get` caller still hides rejected. Locked once the
  order is fulfilled/received/closed.
- "can't see the lunch/X menu" (dishes missing though chef added them) → **split-session bug.** Once a
  meal's primary order is submitted, further dishes go onto **supplements**; the dashboard's menu view
  shows only ONE session per meal (`dbSessionMap` keeps first-or-draft), so if the primary has no dishes
  (only staples) the chef sees an empty menu + a "need more? add supplement" loop while the real dishes sit
  on hidden supplements. Seen 4×/7d, always lunch (Sametu, River). **Partial fix 2026-08-01:** `page_init`
  (+ the 2 sibling session queries) now filter `AND r.deleted_at IS NULL` so archived orders vanish.
  **Still TODO:** make the dashboard menu view aggregate dishes across a meal's primary + supplements.
  One-off remediation: reset the meal to a single clean draft (reuse the supp-0 row to dodge the unique key
  `(kitchen,date,meal,supp)`, soft-delete the rest; manifest in reports/cleanup/). Fresh draft auto-loads
  that day's weekly_menu set menu so the chef sees dishes again.
- "menu not loading" / "can't load menu" → NOT a data bug. Dashboard `dbInit` calls
  `page_init`; if that one API call fails (flaky camp internet, or stale cached app
  version) it showed a dead-end "Failed to load menu". Server is almost always healthy —
  verify with an authenticated `page_init` probe. Dashboard now retries 3× then shows a
  connection-aware message + **Retry** button (2026-06-21). First remediation for a chef:
  reload / reopen the app on a stable connection.

---
*Created 2026-06-19. Keep under 500 lines; prune stale facts. Secrets → `CLAUDE.local.md`.*
