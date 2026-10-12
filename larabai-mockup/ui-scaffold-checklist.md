# UI Scaffold Checklist — Brew & Bite Café

Stage 2 of `development-playbook.md`: port the static mockup (`larabai-mockup/`) into Laravel as
**pure UI** — Blade views, controllers, routes, plain HTML forms with `@csrf`. **No Livewire.**
No database, no auth. Plan and route map: `development-plan.md` §0, §4.

**Rules for every step**
- Sample data stays identical to the mockup's `BB_DATA` (`js/app.js`) so screens can be compared by eye.
- Every form is a real `<form method="POST">` + `@csrf` (+ `@method(...)`) aimed at its real named route.
  The controller saves nothing and redirects back with **"Demo only — nothing is saved"**.
- Every transition the mockup shows has a visible button/form. No disabled buttons.
- JavaScript is plain, display-only, and bound by document-level event delegation.
- One step per session; each step ends with the app working in the browser, verified, **staged, not committed**.
- Commit message: `feat: Step N - <what it delivered>`.

---

## Step 0 — Shell
- [x] Mockup `style.css` copied unchanged to `resources/css/mockup.css`; Laravel-only additions in `resources/css/additions.css`
- [x] Tailwind and axios removed (package.json, `vite.config.js`, `resources/js/bootstrap.js`)
- [x] `config/cafe.php` — café details, delivery fee, Senior/PWD rates, demo mode
- [x] Layouts: `base` (head, fonts, theme boot, flash toast) → `staff`, `customer`, `kitchen`, `guest`, `public`;
      body classes `m-<module>` + `v-<module>-<view>` reproduce the mockup's CSS scoping
- [x] Staff sidebar component (`<x-sidebar>`) — groups per role, admin group first, logout form, theme toggle
- [x] Core JS: theme (no flash on load), toast (auto-hides flash), modal open/close, clock
- [x] Every route in plan §4 defined, named and grouped per module; every controller exists
- [x] Every GET screen has its Blade view in the right layout with a "ported in Step N" placeholder
- [x] Every write route redirects back with the demo toast
- [x] `tests/Feature/ScaffoldRoutesTest` — every GET route returns 200, every write route redirects with the toast
- [x] `welcome.blade.php` removed; `/` is the homepage

> Verified 2026-10-12: 62 tests pass; real-HTTP click-through (table QR → cart → checkout → pay)
> works with CSRF enforced (no token → 419). Laragon has the `larabai-resto-cafe.test` vhost file
> but Apache must be reloaded (Laragon → Reload) before it serves the app.

## Step 1 — Shared components
- [ ] `x-status-pill` (order statuses → mockup chips), `x-money` (₱ formatting), `x-modal` (with form slot, re-opens on validation error)
- [ ] `x-stat-card`, `x-empty-state`, `x-order-lines` (qty × name, options text, note, amount), `x-tracker` (progress steps)
- [ ] `x-confirm-form` (type-to-confirm), `x-art` (drawn glass/plate from item tone)
- [ ] `App\Support\DemoData` — `BB_DATA` ported to PHP arrays (categories, items + option groups, ingredients, recipes, users, sample orders)

## Step 2 — Customer module (`customer.html`)
- [ ] QR entry: `/t/{table}` (signed) and `/counter` (signed) start a session and redirect to the menu; no session → "Scan the QR code" gate
- [ ] Menu: categories bar, search, best seller / new labels, sold-out greyed, item sheet (options, notes, qty, live price) → POST add to cart
- [ ] Cart: qty update (PATCH), remove (DELETE), changed-price notice, cart bar
- [ ] Checkout: dine-in / take-out / delivery, address + landmark, payment method → POST
- [ ] Pay (simulated GCash / Maya) → POST; Success + receipt; Track (progress) + My orders; Cancel order (POST)

## Step 3 — Cashier module (`cashier.html`)
- [ ] POS: menu grid, categories, search, options modal, ticket built by JS into one form → POST charge
- [ ] Orders: tabs (Awaiting payment / In progress / Completed / Cancelled & voided) as `?tab=`, search, row actions per stage as forms
- [ ] Payment: cash (tendered → change preview), GCash / Maya, COD, Senior/PWD fields → POST
- [ ] Receipt: printable

## Step 4 — Kitchen module (`kitchen.html`)
- [ ] Board: New / Preparing / Ready columns, ticket details, time since, COD flag; Start / Back / Ready / Done as forms
- [ ] Recently done strip with Recall; per-line done ticks (localStorage); availability drawer with sold-out switches (PATCH)
- [ ] Dark by default, own theme setting; board partial route for polling

## Step 5 — Inventory module (`inventory.html`)
- [ ] Stock list: tabs (All / Low / Out), category filter, search, value, status, "can't be made" banner, summary cards
- [ ] Restock modal (add delivery / set counted amount, before → after preview) → POST
- [ ] Add / edit ingredient modal → POST / PUT; History page per ingredient

## Step 6 — Admin module (`admin.html`)
- [ ] Dashboard: KPI cards vs last week, 14-day sales chart, payment mix, orders by hour, best sellers, recent activity, needs attention
- [ ] Menu & prices: item list, item editor (option groups + ingredients per serving, dynamic rows) → POST / PUT / DELETE; categories
- [ ] Users & roles: list, add / edit, change password, deactivate, role permissions table
- [ ] Audit logs: filters (period, module, user), search, CSV export link
- [ ] Reset demo data: preview + type-to-confirm; printable table QR sheet

## Step 7 — Auxiliary pages
- [ ] Homepage (`home.html`), System navigator (`index.html`) with signed table/counter QR links
- [ ] Staff login (shift board + form + demo accounts); demo login picks the role shown in the sidebar; logout

## Step 8 — Polish
- [ ] Every mockup screen reachable from navigation; every transition has a control with a handler
- [ ] Empty states; links behave (staff pages ↔ printable/standalone pages)
- [ ] Side-by-side check of every screen against the mockup in light and dark mode
- [ ] `ScaffoldRoutesTest` green; `php artisan route:list` matches plan §4
