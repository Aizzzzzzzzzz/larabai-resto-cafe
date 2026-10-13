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
- [x] `x-status-pill` (order statuses → mockup chips), `x-modal` (real form, `@csrf`, method spoofing, re-opens on validation error), `x-field-error`
- [x] ₱ formatting *(actual: global `peso()` helper + `App\Support\Format` instead of an `x-money` component — a component adds stray whitespace inside inline text)*
- [x] `x-stat-card` + `x-delta`, `x-empty-state` (customer) + `x-empty-row` (staff tables), `x-order-lines`, `x-tracker` (class component — COD and cancelled/voided variants)
- [x] `x-confirm-form` (type-to-confirm) + `data-confirm-twice` buttons (`resources/js/core/confirm.js`), `x-art` (drawn glass/plate)
- [x] `App\Support\DemoData` — `BB_DATA` ported (categories, 27 items + option groups, 41 ingredients, recipes, option recipes, templates, users, 5 tables)
      plus 13 sample orders today covering every status, type, payment method, COD and a Senior discount
- [x] Enums `OrderStatus` (label, chip, tracker rank), `OrderType`, `PaymentMethod` (label, ref prefix) — created early, Phase 2 keeps them
- [x] Component gallery (demo mode): `/navigator/components` (staff) and `/navigator/components/customer`
- [x] Tests: `SharedComponentsTest`, `Unit/DemoDataTest` (recipes reference real items/ingredients, totals add up, every status has a sample)

> Verified 2026-10-12: 82 tests pass; galleries return 200 over HTTP; the modal form posts with CSRF and lands with the toast.

## Step 2 — Customer module (`customer.html`)
- [x] QR entry: `/t/{table}` (signed) and `/counter` (signed) start a session and redirect to the menu; no session → "Scan the QR code" gate;
      later screens behind `qr.session` middleware; a different table/counter starts a fresh cart
- [x] Menu: categories bar, search, best seller / new labels, sold-out greyed, item sheet (options, notes, qty, live price) → POST add to cart
      (each item's options are server-rendered `<template>`s copied into the one sheet form)
- [x] Cart: qty − / + (PATCH), remove (DELETE), changed-price notice, cart bar
      *(actual: the cart works for real — `App\Services\CustomerCart` in the Laravel session, prices from the menu, options validated by `AddToCartRequest`. Session state, not saved data, so it carries into Phase 3)*
- [x] Checkout: dine-in / take-out (table QR) or take-out / delivery (counter QR), address + landmark, payment method → POST, validated by `CheckoutRequest`
- [x] Pay (simulated GCash / Maya) → POST; Success + receipt; Track (progress, order tabs, 10 s status poll) + My orders; Cancel order (tap twice, only while awaiting payment)
      *(scaffold: placing an order saves nothing — checkout shows the sample order matching the chosen type/method with the demo toast)*

> Verified 2026-10-12: 90 tests pass (`tests/Feature/Customer/CustomerOrderingTest.php`); HTTP walk-through
> (scan → add 2 items → cart ₱663.00 → GCash → wallet → paid → tracker) works; headless-Edge screenshots of
> menu, tracker, wallet and receipt match the mockup. JS interactions (sheet, live price, checkout toggles)
> clicked in headless Edge during Step 3 — all pass.

## Step 3 — Cashier module (`cashier.html`)
- [x] POS: menu grid, categories, search, options modal, ticket built by JS (kept in localStorage) into one form → POST charge,
      validated + re-priced by `ChargeRequest`; the ticket empties after a successful charge
- [x] Orders: tabs (Awaiting payment / In progress / Completed / Cancelled & voided / All) and search as GET forms, KPIs,
      row actions per stage from `App\Support\CashierActions` (same rules guard the controllers → 403);
      one shared Cancel/Void reason modal (`data-action` on the row button, `_action` kept across a failed submit)
- [x] Payment: cash (tendered, quick amounts, change preview), GCash / Maya, collect COD, Senior/PWD fields → POST,
      validated by `TakePaymentRequest` (ID required, diners ≥ eligible, cash must cover the amount due after discount)
- [x] Receipt: printable slip, VAT / VAT-exempt lines, change, VOIDED stamp
- [x] `App\Services\Pricing` *(actual: built now, Phase 2 item)* — Senior/PWD discount, total, VAT breakdown, quick cash; `Unit/PricingTest`
- [x] Sidebar badges now live (unpaid orders, low/out ingredients) via `StockLevel`
- [x] Rule from plan §3 applied: cash on delivery can't be marked delivered before the cash is collected

> Verified 2026-10-12: 101 tests pass (`tests/Feature/Cashier/CashierTest.php`); 21 click-through checks in headless
> Edge over DevTools (item sheet, POS ticket, charge, discount preview ₱587.14 = server figure, change). Fixed: the
> tap-twice state class `.btn.confirm` clashed with the mockup's payment button class — renamed to `.btn.armed`.

## Step 4 — Kitchen module (`kitchen.html`)
- [x] Board: New / Preparing / Ready columns (oldest first), ticket details, live timers (warn 10 min, late 15 min), COD flag;
      Start / Back / Mark ready / Served · Picked up · Handed to rider / Recall as PATCH forms, rules in `App\Support\KitchenActions` (controller → 403)
- [x] Recently done strip with Recall; per-line ticks (localStorage, survive refresh); sold-out drawer — each switch is a form
      submitted on change, page returns with the drawer open (`?drawer=1`)
- [x] Dark by default, own theme setting; header stats (in queue, avg prep, done today); sound + full screen;
      board parts re-fetched every 5 s from `kitchen.board` and swapped by `[data-k-part]`, beep when New grows
- [x] Rule from plan §3: unpaid cash on delivery in Ready shows "Waiting for cash on delivery" instead of a Done button;
      `recall` also moves Ready → Preparing (mockup) *(actual: add this transition to the plan's state table)*
- [x] `App\Support\CurrentStaff` (demo user for sidebar + kitchen header until Phase 0)

> Verified 2026-10-12: 103 tests pass (`tests/Feature/Kitchen/KitchenTest.php`); 10 headless-Edge checks (timers, line tick
> surviving the 5 s refresh, drawer switch, Start posts back with toast); screenshot matches the mockup.

## Step 5 — Inventory module (`inventory.html`)
- [x] Stock list: tabs (All / Low or out / In stock), category filter, search (two GET forms that keep each other's values), level bar, value,
      status chip, out-of-stock rows first, "menu items unavailable" banner, summary cards
- [x] One shared Restock modal: row button fills it (`data-fill` JSON in `core/modal.js`), mode switch suggests the usual quantity or the
      books' count, live "Stock becomes …" preview; a count that matches changes nothing; validated by `RestockRequest`
      (modal re-opens with the row's context after a failed save)
- [x] Add ingredient (unit + opening stock) / Edit ingredient (unit and stock locked) modals → POST / PUT, validated by `IngredientRequest`
      (duplicate names rejected, case-insensitive)
- [x] History page per ingredient *(actual: a page, not a modal — plan §4 route `inventory.ingredients.history`)*: used-in chips,
      last 40 movements newest first, change / balance / staff
- [x] `App\Services\StockUsage` *(actual: built now, Phase 2 item)* — recipe + cup/lid + add/replace options; `App\Support\DemoMovements`
      builds a sample history from the sample orders whose balances end at today's stock; enums `MovementType`, `Unit`
- [x] `core/autosubmit.js` shared (moved out of kitchen.js)

> Verified 2026-10-13: 123 tests pass (`tests/Feature/Inventory/InventoryTest.php`, `tests/Unit/StockTest.php`); 23 headless-Edge
> checks (modal fill, previews in delivery and count modes, switching rows, failed save re-open, edit prefill, filter keeps tab).
> Note: MySQL was not running on the test day, so the browser run used `SESSION_DRIVER=file` for the throwaway server only.

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
