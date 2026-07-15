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
    meal's order, emails the kitchen's reception + manager, cc bobby@karibucamps.com. Excludes Woodlands(2)
    + Demo(6). Run by **GitHub Actions** `.github/workflows/missed-meal-alerts.yml` at 05:10/09:10/15:10 UTC
    (= 08:10/12:10/18:10 EAT), which SSHes in with the `HOSTINGER_SSH_KEY` secret. Manual test:
    `gh workflow run "Missed-meal alerts" -f meal=lunch -f dry=true`.
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
- "menu not loading" / "can't load menu" → NOT a data bug. Dashboard `dbInit` calls
  `page_init`; if that one API call fails (flaky camp internet, or stale cached app
  version) it showed a dead-end "Failed to load menu". Server is almost always healthy —
  verify with an authenticated `page_init` probe. Dashboard now retries 3× then shows a
  connection-aware message + **Retry** button (2026-06-21). First remediation for a chef:
  reload / reopen the app on a stable connection.

---
*Created 2026-06-19. Keep under 500 lines; prune stale facts. Secrets → `CLAUDE.local.md`.*
