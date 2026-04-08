# KCL Pantry Planner — Project Context & Development Log

## Project Overview
**App Name:** Karibu Pantry Planner (Smart Requisition)
**Type:** PWA (Progressive Web App) — PHP + MySQL + Vanilla JS + Tailwind CSS
**Purpose:** Kitchen requisition and pantry management for safari camp kitchens
**Users:** Chefs, Storekeepers, Admins across 5+ camps in Tanzania
**Hosting:** Hostinger shared hosting with GitHub auto-deploy
**Live URL:** https://palegoldenrod-coyote-386848.hostingersite.com/
**Repo:** https://github.com/emailsuryatejam-design/Karibu-Pantry-Planner

## Tech Stack
- **Backend:** PHP 8+, MySQL (remote Hostinger DB)
- **Frontend:** Vanilla JS, Tailwind CSS (CDN), no build step
- **PWA:** Service worker, manifest.json, offline fallback
- **Push:** Web Push with VAPID (pure PHP, no Composer)
- **Server:** LiteSpeed (Hostinger), .htaccess for security/caching

## Kitchens (Camps)
| Code | Name | ID |
|------|------|----|
| NLP | Ngorongoro Lions Paw | 1 |
| SWC | Serengeti Woodlands Camp | 2 |
| SSC | Serengeti Safari Camp | 3 |
| SRC | Serengeti River Camp | 4 |
| TES | Tarangire Elephant Springs | 5 |
| DMO | Demo Kitchen | 6 |

## User Roles
- **Chef** — Plans menu, locks orders, submits to store, confirms receipt, closes day
- **Storekeeper** — Views orders, adjusts quantities, issues items, adds ad-hoc items
- **Admin** — Full access: settings, user management, kitchen config, all reports

## Key Workflow
1. **Dashboard** → Chef sets menu (dishes + guest count per meal)
2. **Lock Menu** → Generates aggregated item list from recipe ingredients
3. **Orders** → Chef reviews items, adjusts qty, adds staples, submits to store
4. **Store Orders** → Storekeeper sees order, adjusts, issues to kitchen
5. **Review Supply** → Chef confirms received quantities, disputes flagged
6. **Day Close** → Chef enters unused quantities, closes the day
7. **Reports** → Analytics on ordered/sent/received/unused/consumed

---

## Day-by-Day Development Log

### Day 1 — Feb 14, 2026 (Project Launch)

**Features Built:**
- Initial standalone PHP web app with SPA shell
- Login system with PIN-based auth (select user → enter 4-digit PIN)
- User management (CRUD for admin)
- Menu plan page with recipe-based ordering
- Recipe system with 100+ items seeded
- Weekly menu seed script (lunch + dinner rotation)
- Daily overview page

**Bugs Found & Fixed:**
- `showToast` PHP error — was calling JS function from PHP context
- Session cookie path not working for API calls — added `credentials: 'same-origin'` to fetch
- Admin default page routing — wasn't redirecting correctly
- Bottom nav not showing on all screens — moved nav outside page partials
- Timezone wrong — set to `Africa/Dar_es_Salaam`
- Menu plan loading forever — `app.js` loaded too late, recipe query too slow
- Weekly menu FK constraint blocking seed — dropped FK for compatibility
- `debug-recipes.php` was accidentally wiping `weekly_menu` table — removed
- Low-cost seed items removed (salt, pepper etc. cluttering the list)

---

### Day 2 — Feb 15, 2026 (Store Orders v1)

**Features Built:**
- Base-4 recipe scaling with per-dish portion steppers
- Store pack-size issuing (Requested vs Issuing columns)
- Receipt confirmation with dispute tracking (sent vs received mismatch)
- Download/print for grocery orders
- Page transition animations and loading spinners

**Bugs Found & Fixed:**
- JSON body action parsing broken in API endpoints — POST body not being read
- `received` status missing from `grocery_orders` ENUM — added via ALTER TABLE

---

### Days 3–19 — Feb 16 – Mar 5 (Pause)

No commits. Planning phase.

---

### Day 20 — Mar 6, 2026 (V2 Major Rewrite)

**Features Built:**
- Multi-kitchen support (kitchen_id scoping everywhere)
- PWA support (manifest, service worker, offline fallback)
- Push notifications (VAPID Web Push, pure PHP implementation)
- Voice announcements for notifications
- Database performance optimizations (indexes, caching)
- Renamed "Session" → "Requisition" throughout UI
- Admin-configurable requisition types (Breakfast/Lunch/Dinner/custom)

**Bugs Found & Fixed:**
- .htaccess security rules blocking legitimate requests — tuned rules

---

### Day 21 — Mar 7, 2026 (Dish-Based Ordering)

**Features Built:**
- Dish-based requisition ordering (pick dishes → auto-calculate ingredients)
- Removed manual items mode — all ordering through recipes
- Auto-create requisitions per meal type on page load
- Rotational set menu system (weekly fixed menu per kitchen)
- Batch API endpoints for performance
- XSS safety (escHtml throughout)
- Meal Types management in admin Settings
- Instant tab switching (parallelize API calls)

**Bugs Found & Fixed:**
- Duplicate requisitions created on refresh — added UNIQUE constraint + INSERT IGNORE
- Set menu copy bug — was overwriting instead of appending
- `escHtml` caching stale data — invalidation issue
- DB migration running on every request — added "already ran" detection
- Search 500 error — missing table column
- Self-healing migration creating tables in wrong order

---

### Day 22 — Mar 9, 2026 (Supplementary Orders + Reports)

**Features Built:**
- Chef-simplified order view with per-portion display
- Admin scaling settings (rounding mode, min order qty)
- Supplementary orders (chef can order more after submitting)
- Reports: meal type labels, Req/Issued/Received/Diff detail view
- Print order feature (A4 format with full flow data)
- Per-dish portion control
- Streamlined recipe ingredient adding (keep sheet open for multi-add)
- Unified reports between chef & store
- PWA install FAB button with iOS support
- Day-close unused portions tracking

**Bugs Found & Fixed:**
- Supplementary order creation — action not being read from URL query string
- Store print using wrong function — `printStoreOrder()` vs `printRequisition()`
- Save/submit stale-state bug — form data not refreshing after save
- iOS push notification failure — Safari doesn't support Web Push
- Day-close: couldn't edit unused on closed orders — status check too strict
- Empty drafts showing in day-close — added filter
- Submit error toast not showing — error handler missing
- Guest count display wrong after submission
- Notifications table not created — bumped migration version

---

### Day 23 — Mar 10, 2026 (Performance)

**Features Built:**
- Speed up Order page: collapsed 4 sequential API calls into 1 batch endpoint

---

### Day 24 — Mar 11, 2026 (Kitchen Management)

**Features Built:**
- Admin reset_all_orders action for clean database
- Dynamic kitchen count on admin dashboard
- Auto-refresh session user data from DB every 60s
- Dinner set menu seeded (56 dishes, 7-day rotation)
- Kitchen Inventory page for storekeepers
- Removed legacy grocery_orders system (v1)

**Bugs Found & Fixed:**
- Set menu not loading — missing `is_active` columns on tables
- Stock display removed from ordering (was confusing chefs)
- Set menu not reloading when switching meal tabs — cache not clearing
- Set menu replacing manually added dishes — changed to merge behavior
- PIN validation too loose — enforced exactly 4 digits
- LiteSpeed caching PHP pages — disabled via .htaccess
- Store Orders page reading from wrong table (old grocery_orders) — switched to requisitions
- Store Orders missing closed/processing statuses
- Session kitchen_name not populated — fixed session init
- Migration file blocked by .htaccess `migrate-*` rule — renamed

---

### Day 25 — Mar 12, 2026

**Bugs Found & Fixed:**
- Chef name wrong on orders — `created_by` not updated on save/submit

---

### Day 26 — Mar 13, 2026

**Features Built:**
- Admin users can access inventory pages

---

### Day 27 — Mar 14, 2026 (Inventory + Tablet UX)

**Features Built:**
- Custom modal confirm dialogs (replaced native `confirm()`)
- Stock adjustment for chefs with discrepancy reports
- Separate kitchen pantry inventory from store stock
- Pantry stock breakdown shown in ordering UI
- Portions popup for dish management
- Tablet-optimized UI (7-inch targets, larger touch areas)
- Dish search as centered modal popup
- UOM management: piece_weight conversion, pantry staple flag

**Bugs Found & Fixed:**
- Store-inventory and kitchen-inventory role access mixed up
- One-time UOM migration script left in repo — removed

---

### Day 28 — Mar 16, 2026 (Kitchen URLs + Store Line Editing)

**Features Built:**
- Kitchen-specific login URLs (/NLP/, /TES/, /SWC/, /SSC/, /SRC/)
- Directory-based routing for LiteSpeed compatibility
- Storekeeper line item editing (remove/restore/add items to orders)

**Bugs Found & Fixed:**
- .htaccess RewriteRule failed on LiteSpeed — clean URLs don't work
- Switched to physical directory approach (/NLP/index.php etc.)
- Deploy not syncing — multiple force-push triggers needed

---

### Day 29 — Mar 18, 2026 (Recipes + Chef Books)

**Features Built:**
- 65 recipes seeded from weekly menu + chef recipe cards
- Pantry staple marking (bulk fix_staples API)
- Chef-specific recipe books (filtered by created_by)
- Recipe duplication for new chefs (64 recipes auto-copied)
- Admin recipe list with chef filter dropdown

**Bugs Found & Fixed:**
- Deploy trigger comment accidentally added inside PHP — removed
- Duplicate recipe ingredients created during seeding — cleaned up

---

### Day 30 — Mar 23, 2026 (25 Bottlenecks + Security)

**Features Built:**
- 25 UI/UX bottleneck fixes across chef and storekeeper flows
- Auto-copy recipes when creating new chef users
- Soft-delete for users (preserve data integrity)
- Inline toggle for ingredient primary/staple status
- Chef filter dropdown for admin recipe list
- Camp-specific PWA manifest for per-kitchen app installs
- Install App button on login screen
- Camp dropdown on login page with dynamic user filtering
- Logout redirects to camp-specific URL

**Bugs Found & Fixed:**
- save_and_submit fall-through — data not preserved across switch cases
- Kitchen login redirects using relative paths — changed to absolute
- JS syntax error in recipes page preventing load
- Recipes not loading at all — syntax error in chef filter code
- PWA install button not showing — service worker not registered on login page

---

### Day 31 — Mar 25, 2026 (Security Audit + Major Restructure)

**Features Built:**
- **Security hardening** (from 14-agent system design audit):
  - PIN hashing with bcrypt (password_hash/verify)
  - DB credentials moved to .env
  - Deleted api/_fix.php (remote code execution backdoor)
  - Protected setup.php (CLI-only)
  - CSRF tokens on all POST endpoints
  - HTTPS enforcement + HSTS
  - Content-Security-Policy header
  - Login rate limiting (5 attempts/15min)
  - Session regeneration after login
  - User list no longer exposed on login page
  - Role checks on store-orders API
  - Recipe ownership enforcement
  - escapeLike() applied consistently
- **Dashboard overhaul**: date switcher, horizontal meal tabs, per-meal guest count, per-meal lock
- **Orders page redesign**: Menu/Staple tabs, tap-to-edit popup, add staple items
- **SAP item migration**: 1744 items imported with SAP codes
- SAP item numbers in UI search + printouts
- Category filter chips in item search
- Popup tile design for add/edit items
- Delete order button (cancel before fulfillment)

**Bugs Found & Fixed:**
- 3 high-severity lifecycle bugs: race condition on cancel, is_staple missing from store query, mark_sent not setting line status
- 4 lifecycle bugs: empty order stuck in draft, staple lines lost on re-lock, store UI missing staple badge, incomplete receipt validation
- is_staple column missing from requisition_lines — added self-healing migration
- add_line_to_order error not visible — added try-catch
- Draft requisitions with staple lines hidden on Orders page — fixed filter
- lock_menu falling through to wrong switch case — fixed with goto label
- Hardcoded DB fallback needed until .env uploaded to Hostinger

---

### Day 32 — Mar 26, 2026 (Storekeeper Enhancements)

**Features Built:**
- Storekeeper can edit fulfilled orders (add/remove/restore items after sending)
- SAP item number column added to both printouts

**Bugs Found & Fixed:**
- Store-added items incorrectly marked as is_staple=1 — changed to 0

---

### Day 33 — Mar 27, 2026

**Features Built:**
- Inventory button disabled in store nav (temporarily)

---

### Day 34 — Mar 31, 2026 (Orders UX Overhaul)

**Features Built:**
- Add button moved to order card headers (removed floating FAB)
- Collapse/expand toggle on each order card
- Staple tab: flat item list instead of per-meal cards (purple header)
- Breakfast card always visible on Orders (even without locked menu)
- Recipes page: Meals/Breakfast tabs
- New recipe defaults to breakfast category when on Breakfast tab

**Bugs Found & Fixed:**
- Breakfast items not adding — draft reqs with 0 lines filtered out by ordLoad
- is_staple flag always set to 1 — now based on active tab (0 for menu, 1 for staple)

---

### Day 35 — Apr 2, 2026 (Chef Order Control)

**Features Built:**
- Guest count editing after lock (before store issues) — recalculate_order API
- Dish source breakdown stored as JSON (source_dishes column)
- Dish breakdown shown under items on Orders (e.g. "Chicken Stew (1.5) + Rice (0.5)")
- Chef sees orders after submission (read-only with status badges)
- Ingredients shown under dishes on Dashboard
- 3-column signature block on printouts (Prepared by / Issued by / Received by Manager)
- Order history via date switcher (past dates show completed orders)

**Bugs Found & Fixed:**
- Guest count only had +5/-5 buttons — changed to editable input with -/+/Save
- Auto-debounce was confusing — replaced with explicit Save button

---

## System Design Audit Summary (14-Agent Report)

A comprehensive audit was run using 14 parallel agents, each analyzing one system design document against the actual codebase. Key findings across all agents:

### Critical Issues (All Fixed)
1. PINs stored in plaintext → Hashed with bcrypt
2. Hardcoded DB credentials → Moved to .env
3. api/_fix.php backdoor → Deleted
4. No CSRF protection → Added tokens
5. No login rate limiting → 5 attempts/15min
6. setup.php publicly accessible → Protected

### Architecture Notes
- Monolithic PHP app — appropriate for scale (5 camps, ~30 users)
- File-based caching with TTL (items 5min, kitchens 10min)
- No message queue — push notifications sent synchronously
- No database transactions on some multi-table writes (partially fixed)
- Self-healing migrations run inline during API requests
- 1300+ line requisitions.php — largest file, handles 15+ actions

### Known Technical Debt
1. CDN Tailwind in production (should be pre-built CSS)
2. No automated tests
3. No API versioning
4. Service worker caches PHP pages (stale data risk)
5. Self-healing DDL in request paths (should be migration scripts)
6. No pagination on some list endpoints
7. Correlated subqueries in list queries (should be JOINs)
8. No service layer — business logic embedded in API handlers

---

## File Structure (Key Files)

```
├── index.php              # Login page (camp dropdown + PIN pad)
├── app.php                # SPA shell (page routing, nav, auth)
├── logout.php             # Session destroy + redirect
├── config.php             # DB, session, helpers, audit logging
├── auth.php               # requireAuth, requireRole middleware
├── setup.php              # DB schema creation + seed data
├── .htaccess              # Security headers, caching, HTTPS
├── manifest.json          # PWA manifest
├── service-worker.js      # Offline support, cache strategies
├── Smart-Requisition.md   # Full PRD/specification
│
├── api/
│   ├── requisitions.php   # Core: 15+ actions, 1500+ lines
│   ├── store-orders.php   # Storekeeper order management
│   ├── recipes.php        # Recipe CRUD + ingredients
│   ├── items.php          # Item catalog management
│   ├── users.php          # User CRUD + recipe copying
│   ├── kitchens.php       # Kitchen CRUD + settings
│   ├── inventory.php      # Stock adjustments + discrepancies
│   ├── menu-plan.php      # Menu plan management
│   ├── set-menus.php      # Weekly rotational menu
│   ├── push.php           # Push notification subscriptions
│   └── requisition-types.php  # Meal type configuration
│
├── pages/
│   ├── dashboard.php      # Chef home: menu planning per meal
│   ├── orders.php         # Chef: item list, submit to store
│   ├── recipes.php        # Recipe management (Meals/Breakfast tabs)
│   ├── store-orders.php   # Storekeeper: order fulfillment
│   ├── store-dashboard.php # Storekeeper home
│   ├── review-supply.php  # Chef: confirm receipt
│   ├── day-close.php      # End of day closeout
│   ├── inventory.php      # Stock management
│   ├── reports.php        # Analytics and summaries
│   ├── settings.php       # Admin settings (kitchens, users, UOM)
│   └── set-menu.php       # Weekly menu configuration
│
├── assets/
│   └── app.js             # Core JS: API helper, print, push, voice
│
├── lib/
│   └── push-sender.php    # Pure PHP Web Push (VAPID/ECDH/AES)
│
└── NLP/, SWC/, SSC/, SRC/, TES/  # Kitchen entry point directories
    └── index.php          # Redirects to main index with kitchen filter
```

---

## Contacts & Access

- **Hostinger Panel:** hpanel.hostinger.com
- **GitHub Repo:** github.com/emailsuryatejam-design/Karibu-Pantry-Planner
- **DB Host:** auth-db960.hstgr.io
- **Auto-deploy:** GitHub push → Hostinger (with occasional lag)
