# Brew & Bite Café (Larabai Resto Cafe) — Development Plan

**Stack baseline:** Laravel 12 · PHP 8.4 · **Blade + controllers + plain HTML forms (`@csrf`) — no Livewire, no Inertia, no Alpine** · plain JavaScript (ported from the mockup's `app.js`) · MySQL 8 `larabai_database` (Laragon, local) · Vite 7
**References:** `document.md` (behavior spec — features §3, data dictionary §4, diagrams §5) · `larabai-mockup/*.html` + `js/app.js` (UI contract — each `<template data-view="…">` becomes a real route; `BB_DATA` in `app.js` becomes the seeders) · `development-playbook.md` (the method)

> **Status update (2026-10-12):** Stage 1 (spec + static mockup) is done. Stage 2 (UI scaffold)
> has **not** started — it comes first, tracked in `ui-scaffold-checklist.md` (to be written), and
> produces every Blade view, route and controller listed in §4 with hardcoded mockup data and
> "Demo only — nothing is saved" flash toasts. After the scaffold, §5's phases **swap data in under
> the existing markup** rather than build screens from scratch.
> Log deviations inline as *(actual: …)* as they happen.
>
> **2026-10-12 — Scaffold Step 0 done:** shell, layouts, sidebar, core JS, all 57 routes and their
> controllers/views (see `ui-scaffold-checklist.md`). Tailwind and axios removed; mockup CSS kept
> unchanged as `resources/css/mockup.css`.
>
> **2026-10-12 — Scaffold Step 1 done:** shared Blade components, `App\Support\DemoData` (the
> mockup's `BB_DATA` + 13 sample orders), `peso()` helper, enums `OrderStatus` / `OrderType` /
> `PaymentMethod` *(actual: created before Phase 2)*, demo-only component gallery routes
> `navigator.components.staff` / `navigator.components.customer` *(actual: not in §4)*.

---

## 0. UI approach — Laravel's default form flow (no Livewire)

Every screen is a server-rendered Blade view. Every write is a normal HTML form.

| Concern | How it's done |
|---|---|
| Reading a page | `GET` named route → controller → `view('module.page', [...])` |
| Writing data | `<form method="POST" action="{{ route('…') }}">` + `@csrf`; edits/deletes add `@method('PATCH'\|'PUT'\|'DELETE')` |
| After a write | **Post/Redirect/Get:** controller does the work, then `return redirect()->route(…)->with('status', '…')` — never render a view from a POST |
| Toasts | Layout reads `session('status')` / `session('error')` and shows the mockup's `.toast-pop` |
| Validation | Form Request classes (`app/Http/Requests/…`); views use `@error('field')` and `old('field')`. A modal whose form failed validation re-opens itself (layout checks `$errors->any()` + `old('_modal')`) |
| Modals | Blade component `<x-modal id="…">` holding a real `<form>`; plain JS only opens/closes it |
| Interactivity | Small vanilla JS modules, bound with **document-level event delegation**: live price preview in item customization, cash-change preview, restock before/after preview, type-to-confirm, dynamic form rows. JS is **display-only** — the server recomputes every price, total and quantity |
| Many-line forms (POS ticket, menu-item editor) | JS adds/removes rows of hidden/array inputs (`items[0][menu_item_id]`, `groups[1][choices][0][name]`) inside **one** form, submitted with one POST |
| Live screens (kitchen board, customer tracker) | Plain JS polls a `GET` route every ~10 s that returns a rendered Blade partial (or small JSON) and swaps it in. No WebSockets for now |
| Per-device conveniences | `localStorage` only for UI state that doesn't matter if lost: dark/light theme, kitchen per-line "done" ticks (mockup's `bb_kds`) |
| Styling | Port the mockup's `css/style.css` into `resources/css/app.css` as-is. **Remove the Tailwind import** — Tailwind's reset would fight the mockup's CSS *(confirm)* |

---

## 1. Recommended tech per function

| Function | Default recommendation | Alternative / notes |
|---|---|---|
| Auth | Laravel's built-in session auth with a hand-written `Auth\LoginController` (`Auth::attempt(['username' => …, 'password' => …, 'is_active' => true])`); after login, redirect to the role's home screen (spec §3.3) | Laravel Breeze (Blade) — but it brings Tailwind views and email-based flows this app doesn't need |
| Roles & permissions | `role` column cast to `App\Enums\Role` (admin, cashier, kitchen, inventory) + a `role:` route middleware per route group + **Policies** per model. `Gate::before` lets admin through everywhere (spec: owner can open every module) | `spatie/laravel-permission` — overkill for four fixed roles |
| Core domain state machine | `App\Enums\OrderStatus` + `App\Services\OrderWorkflow` owning every transition in §3's state table; illegal moves throw `IllegalTransition` | — |
| Notifications | None outside the app. Flash toasts for staff; kitchen board and customer tracker refresh by polling | Laravel Reverb (WebSockets) later — spec §7.2 rec. 4 |
| File attachments | None. Menu items use the mockup's colour-tone art (`tone` field), not uploaded photos | Add `image_path` + `storage:link` (already linked) if photos are wanted |
| Backups + health check | `php artisan backup:db` custom command wrapping `mysqldump` into `storage/app/backups`; Laravel's built-in `/up` health route | `spatie/laravel-backup` |
| Import/export | Audit log CSV export via `response()->streamDownload()` using the list's current filters — no package | `league/csv` if exports grow |
| Audit trail | `audit_logs` table (spec §4.17) written by `App\Services\AuditLogger`, called **explicitly** from services/controllers at each business action (payment, void, discount, menu change, stock change) | Model observers — rejected: audit entries are business events, not row changes |
| Access log | Listeners on Laravel's `Login`, `Logout` and `Failed` auth events → `AuditLogger` ("Logged in", "Logged out", "Failed login"); also updates `users.last_login_at` | — |
| Sensitive fields | Passwords: `hashed` cast. Senior/PWD `id_no` and `holder_name`: `encrypted` cast (personal data, Data Privacy Act; must be readable on receipt, so encrypt not hash) | — |
| Danger zone (destructive ops) | "Reset demo data" (spec §3.7): admin-only `POST`, preview of row counts → type `RESET` to confirm → server re-counts and runs `migrate:fresh --seed`. Enabled only when `config('cafe.demo_mode')` is true | — |
| Queue driver | `sync` is enough — nothing slow or scheduled yet. `QUEUE_CONNECTION=database` is already set up if needed | — |
| Testing | PHPUnit feature tests (already installed) against a separate MySQL test DB `larabai_testing` | Pest |
| QR codes | `chillerlan/php-qrcode` to print table/counter QR standees from **signed** URLs (spec §7.2 rec. 3) | `simplesoftwareio/simple-qrcode` |
| Money | `DECIMAL` columns, `decimal:2` casts; all maths in `App\Services\Pricing` with explicit rounding to 2 dp, matching the mockup's `round2` | — |

---

## 2. Folder structure

```
app/
├── Enums/
│   ├── Role.php                  # admin, cashier, kitchen, inventory
│   ├── OrderStatus.php           # awaiting_payment, paid, preparing, ready, completed, cancelled, voided
│   ├── OrderType.php             # dine-in, take-out, delivery
│   ├── OrderSource.php           # qr, pos
│   ├── PaymentMethod.php         # GCASH, MAYA, CASH (+ reference prefix GC/MY/CS)
│   ├── PaymentStatus.php         # PAID, VOIDED
│   ├── DiscountType.php          # Senior, PWD
│   ├── MovementType.php          # SALE, RETURN, PURCHASE, ADJUST
│   └── Unit.php                  # g, ml, pc
├── Http/
│   ├── Controllers/
│   │   ├── Public/               # HomeController, NavigatorController
│   │   ├── Auth/                 # LoginController
│   │   ├── Customer/             # SessionController, MenuController, CartController,
│   │   │                         # CheckoutController, PaymentController, OrderController
│   │   ├── Cashier/              # PosController, OrderController, PaymentController, ReceiptController
│   │   ├── Kitchen/              # DisplayController, OrderStatusController, AvailabilityController
│   │   ├── Inventory/            # IngredientController, RestockController, MovementController
│   │   └── Admin/                # DashboardController, CategoryController, MenuItemController,
│   │                             # UserController, AuditLogController, DemoResetController
│   ├── Requests/                 # one Form Request per write, same sub-folders as Controllers
│   └── Middleware/
│       └── EnsureRole.php        # alias `role` — `role:cashier`, `role:kitchen`, …
├── Models/                       # see §3
├── Services/
│   ├── OrderWorkflow.php         # state machine — the only code that changes orders.status
│   ├── Pricing.php               # line price, subtotal, Senior/PWD discount, delivery fee, total
│   ├── PaymentService.php        # record payment + reference no., void payment
│   ├── StockService.php          # usage per line (recipes, cup/lid, add/replace options), deduct, return, restock, count
│   ├── MenuAvailability.php      # sold-out = kitchen override ?? "not enough stock for one serving"
│   ├── CustomerCart.php          # session-backed cart, re-syncs lines against current menu (spec §6 #9)
│   ├── Sequence.php              # daily numbers: BB-YYYYMMDD-NNNN, GC-/MY-/CS-YYYYMMDD-NNNN
│   └── AuditLogger.php
├── Policies/                     # OrderPolicy, PaymentPolicy, MenuItemPolicy, IngredientPolicy, UserPolicy, AuditLogPolicy
├── Exceptions/                   # IllegalTransition, InsufficientStock
└── Listeners/                    # LogSuccessfulLogin, LogLogout, LogFailedLogin

resources/
├── css/app.css                   # ported mockup style.css
├── js/
│   ├── app.js                    # entry: imports the modules below
│   ├── core/                     # toast, modal, theme toggle, type-to-confirm, poller, peso formatter
│   └── modules/                  # customer-item.js, pos-ticket.js, cashier-payment.js, kitchen-board.js,
│                                 # inventory-restock.js, admin-menu-editor.js, admin-charts.js
└── views/
    ├── layouts/                  # staff.blade.php (sidebar per role), customer.blade.php (phone),
    │                             # kitchen.blade.php (dark display), guest.blade.php (login), public.blade.php
    ├── components/               # status-pill, modal, toast, stat-card, money, empty-state,
    │                             # confirm-form (type-to-confirm), order-lines, tracker, sidebar
    ├── public/                   # home, navigator
    ├── auth/                     # login
    ├── customer/                 # menu, cart, checkout, pay, success, track, my-orders, partials/
    ├── cashier/                  # pos, orders, payment, receipt, partials/
    ├── kitchen/                  # display, partials/board
    ├── inventory/                # ingredients, history, partials/
    └── admin/                    # dashboard, menu, users, audit-logs, demo-reset, table-qr, partials/

routes/web.php                    # thin: grouped by module, every group behind auth + role middleware
database/seeders/                 # one seeder per BB_DATA block (see §3)
tests/Feature/<Module>/ · tests/Unit/Services/
```

**Conventions to commit to early**

- **Controllers stay thin:** validate (Form Request) → authorize (Policy) → call a Service → redirect with flash. Business rules live in `app/Services`, never in controllers or Blade.
- **Only `OrderWorkflow` changes `orders.status`.** Only `StockService` changes `ingredients.stock_qty`, and it always writes a `stock_movements` row in the same DB transaction.
- **Authorization happens in Policies** (`$this->authorize(…)` / `can:` middleware) on every entry point — list, detail, receipt, export, tracker poll — never only by hiding sidebar links or buttons.
- **Route names mirror folders:** `customer.cart.index`, `cashier.orders.void`, `kitchen.orders.status`, `admin.menu-items.update`. Views and controllers use names, never hardcoded URLs.
- **Row action grammar** (from the mockup): primary verb for the order's current stage + secondary links; no disabled buttons — a status pill explains why nothing can be done; destructive actions (void, cancel, deactivate, reset) need a reason or type-to-confirm.
- **Prices are never trusted from the browser.** Forms submit IDs and quantities; the server re-prices.
- **Sample data stays identical to the mockup** (`BB_DATA` → seeders) so screens can be compared by eye.
- Edit source files with the editor/Edit tooling, not PowerShell `Set-Content` (₱, —, ñ in "Tañedo" would turn into mojibake).

---

## 3. Data model — migrations & relationships

Follows `document.md` §4. Laravel conventions applied on top *(confirm — the spec is a graded IT54 deliverable)*:
every table gets an auto-increment `id` and `created_at`/`updated_at`; the spec's string IDs (`latte`, `coffee`, `beans`) become a unique **`code`** column used for route binding and seeders; table names are plural (`order_status_logs`).

### Migration order (respects FK dependencies)

```
0. (existing) cache, jobs, sessions
1.  users               (modify default: full_name, username UQ, role, is_active, last_login_at; drop email & password_reset_tokens)
2.  categories          (code UQ, name UQ, is_drink, sort_order, icon)
3.  menu_items          (code UQ, category_id FK, name UQ, description, price, tone, is_best_seller, is_new, sold_out_override NULL, is_active)
4.  option_groups       (menu_item_id FK, code, name, selection_type single|multi, is_required, sort_order)
5.  option_choices      (option_group_id FK, code, name, extra_price, is_default, sort_order)
6.  ingredients         (code UQ, name UQ, category, unit, stock_qty ≥ 0, reorder_level, restock_qty, unit_cost)
7.  item_ingredients    (menu_item_id + ingredient_id PK, qty_per_serving > 0)
8.  choice_ingredients  (option_choice_id + ingredient_id PK, qty, action add|replace, replaces_id FK NULL)
9.  dining_tables       (table_no UQ, seats, is_active)                 — qr_url is generated (signed), not stored
10. orders              (order_no UQ, public_token UQ, source, order_type, dining_table_id NULL, delivery_address,
                         delivery_landmark, subtotal, discount_amount, delivery_fee, total, payment_method NULL,
                         status, cancel_reason, stock_deducted, created_by NULL, paid_at NULL)
11. order_items         (order_id FK, menu_item_id FK, item_name, unit_price, qty > 0, notes)
12. order_item_options  (order_item_id + option_choice_id PK, choice_name, extra_price)
13. order_status_logs   (order_id FK, status, changed_by NULL, changed_at)
14. payments            (order_id FK UQ, method, amount, reference_no UQ, status, amount_tendered, change_due,
                         received_by NULL, paid_at, voided_at, void_reason)
15. discounts           (order_id FK UQ, discount_type, id_no [encrypted], holder_name [encrypted], eligible_count ≥ 1,
                         diner_count ≥ eligible_count, amount)
16. stock_movements     (ingredient_id FK, type, qty signed, qty_before, qty_after, cost, order_id NULL, note, user_id NULL, moved_at)
17. audit_logs          (user_id NULL, role, module, action, detail, logged_at)
18. daily_sequences     (prefix + date PK, last_no)                     — (actual: added; not in spec §4)
```

### Relationship map

```
categories  1─* menu_items  1─* option_groups  1─* option_choices
menu_items  *─* ingredients        (item_ingredients: qty_per_serving)
option_choices *─* ingredients     (choice_ingredients: add qty | replace X with Y)

dining_tables 1─* orders
users  1─* orders (created_by) · 1─* payments (received_by) · 1─* stock_movements · 1─* audit_logs · 1─* order_status_logs

orders 1─* order_items 1─* order_item_options *─1 option_choices
orders 1─* order_status_logs
orders 1─0..1 payments
orders 1─0..1 discounts
orders 1─* stock_movements (SALE / RETURN)
ingredients 1─* stock_movements
```

### Order state table (the test list for `OrderWorkflow`)

Taken from spec §3–§5.4 and the mockup's actual buttons (`app.js` cashier/kitchen handlers).
"COD" = delivery + cash: it goes to the kitchen **unpaid**, still in `awaiting_payment`.

| # | From | To | Who | Control | Side effects |
|---|---|---|---|---|---|
| 1 | — | `awaiting_payment` | Customer (QR checkout) / Cashier (POS charge) | Checkout / Charge | order no., status log; **COD: deduct stock now** |
| 2 | `awaiting_payment` | `paid` | Customer (GCash/Maya pay) | Pay on wallet screen | payment PAID + ref no., `paid_at`, deduct stock, audit |
| 3 | `awaiting_payment` | `paid` | Cashier | Take payment (cash/GCash/Maya, ± Senior/PWD) | same as #2 + discount row, change due |
| 4 | `awaiting_payment` | `cancelled` | Customer (non-COD; COD only until kitchen starts) / Cashier | Cancel order | reason; return stock if deducted (COD) |
| 5 | `paid` · COD `awaiting_payment` | `preparing` | Kitchen | Start | audit "Started preparing" |
| 6 | `preparing` | `paid` · COD `awaiting_payment` | Kitchen | Back | — |
| 7 | `preparing` | `ready` | Kitchen | Ready | audit "Marked ready" |
| 8 | `ready` | `completed` | Cashier (served / picked up / delivered) or Kitchen (done) | Mark served/picked up/delivered · Done | — |
| 9 | `completed` | `ready` | Kitchen | Recall (↺ "Recently done") | audit "Recalled order" |
| 10 | `paid` · `preparing` | `voided` | Cashier | Void (reason required) | payment → VOIDED, return stock, audit |
| — | COD (any kitchen stage) | (status unchanged) | Cashier | Collect COD payment | payment PAID, `paid_at`; **COD cannot reach `completed` unpaid** *(rule added — mockup allows "Mark delivered" before collecting)* |

Everything not in this table throws `IllegalTransition` (e.g. voiding a `ready` or `completed` order, cancelling a `paid` one, paying twice).

### Modeling decisions worth locking in

- **One `status` column** on `orders` (spec enum) instead of separate paid/prepared/served flags; "is it paid?" is `paid_at IS NOT NULL`, which also covers COD orders moving through the kitchen unpaid.
- **Snapshots on order lines:** `item_name`, `unit_price`, `choice_name`, `extra_price` are copied at order time so later menu/price edits never change past orders or receipts.
- **Stock usage per line** (mirrors `inventory.usageForLine`): item recipe × qty, plus a 12 oz or 16 oz cup and a lid for drink categories (`categories.is_drink`, size = Large → `cup16`), plus `add` option ingredients; `replace` options swap one ingredient's quantity for another (oat milk for fresh milk). Same quantity at every size (spec §4.7).
- **`stock_deducted` flag** guards against double deduction and drives returns on cancel/void. Deduct/return runs inside the same DB transaction as the status change, with `lockForUpdate()` on the ingredient rows.
- **Stock never goes below 0** (spec: `stock_qty ≥ 0`): availability is re-checked at checkout, at POS charge and at payment; if stock ran out in between, the action is refused with a message naming the item — enforced in `StockService`, not just greyed-out menu cards.
- **Sold-out is computed:** `sold_out_override` (kitchen switch) wins when set; when NULL the item is sold out if any recipe ingredient can't cover one serving. Used by customer menu, POS and checkout validation.
- **Senior/PWD discount** (mirrors mockup `seniorDiscount`): `share = subtotal × min(eligible, diners) ÷ diners`; `discount = share − (share ÷ 1.12 × 0.80)`; rounded to 2 dp. `total = subtotal − discount + delivery_fee` (₱50 for delivery), never below 0.
- **Numbers:** `BB-YYYYMMDD-NNNN` for orders and `GC-/MY-/CS-YYYYMMDD-NNNN` for payments, from `daily_sequences` with a row lock (safe under two cashiers + QR at once).
- **Customer privacy:** no name or phone on orders (spec obj. 7). Customers are identified only by the Laravel session; "My orders" = order ids stored in that session.
- **Customer gating — every entry point:** pay page, success page, tracker, tracker poll and cancel use `orders.public_token` (random, unguessable) **and** require the token to be in the visitor's session. Order IDs never appear in customer URLs.
- **QR codes are signed URLs** (`URL::signedRoute('customer.session.table', ['table' => 3])`), so a customer can't change `?table=3` to another table (spec §7.2 rec. 3).
- **Spec ↔ mockup mismatches to settle *(confirm)*:** mockup `cafe.tables: 14` vs spec's **5** tables (seed 5); mockup history labels a `WASTAGE` movement type that spec §4.16 doesn't have (leave it out); audit log gets a `module` column because the admin filter needs it (spec §3.7) but §4.17 lacks it.

### Seeders (mirror `BB_DATA` in `js/app.js`)

`UserSeeder` (5 demo staff: admin/admin123, cashier, cashier2, kitchen, stock) · `CategorySeeder` (6) · `MenuItemSeeder` (items + option groups from the option templates) · `IngredientSeeder` (stock, reorder, restock qty, unit cost) · `RecipeSeeder` (`recipes` + `optionRecipes`) · `DiningTableSeeder` (5: two 2-seaters, two 4-seaters, one 6-seater) · `SampleHistorySeeder` (port of `history.sample()` — 14 days of paid orders with the same weighting, so the dashboard isn't empty; demo mode only).

---

## 4. UI module ↔ mockup mapping

Each mockup `<template data-view>` (shown as in-page tabs for convenience) becomes its own route.
All staff routes sit behind `auth` + `role:` middleware **and** a policy check.

| Mockup view (static) | Real route (method · URI · name) | Controller@action → Blade view |
|---|---|---|
| `home.html` | GET `/` · `home` | `Public\HomeController@index` → `public/home` |
| `index.html` (navigator, demo) | GET `/navigator` · `navigator` (demo mode only) | `Public\NavigatorController@index` → `public/navigator` |
| `login.html` | GET `/login` · `login` · POST `/login` · POST `/logout` · `logout` | `Auth\LoginController@create/store/destroy` → `auth/login` |
| *(table QR)* `customer.html?table=3` | GET `/t/{table}` (signed) · `customer.session.table` | `Customer\SessionController@table` → redirect to menu |
| *(counter QR)* `customer.html?type=takeout` | GET `/counter` (signed) · `customer.session.counter` | `Customer\SessionController@counter` → redirect to menu |
| `customer` · menu (+ item customization modal) | GET `/order/menu` · `customer.menu` | `Customer\MenuController@index` → `customer/menu` |
| `customer` · add to cart | POST `/order/cart` · `customer.cart.store` | `Customer\CartController@store` |
| `customer` · cart | GET `/order/cart` · PATCH/DELETE `/order/cart/{line}` | `Customer\CartController@index/update/destroy` → `customer/cart` |
| `customer` · checkout | GET/POST `/order/checkout` · `customer.checkout.*` | `Customer\CheckoutController@create/store` → `customer/checkout` |
| `customer` · pay (simulated GCash/Maya) | GET/POST `/order/{order:public_token}/pay` · `customer.pay.*` | `Customer\PaymentController@create/store` → `customer/pay` |
| `customer` · success + receipt | GET `/order/{order:public_token}/done` · `customer.orders.success` | `Customer\OrderController@success` → `customer/success` |
| `customer` · track | GET `/order/{order:public_token}` · `customer.orders.show` (+ GET `…/status` poll) | `Customer\OrderController@show/status` → `customer/track` |
| `customer` · my orders | GET `/order/mine` · `customer.orders.index` | `Customer\OrderController@index` → `customer/my-orders` |
| `customer` · cancel order | POST `/order/{order:public_token}/cancel` | `Customer\OrderController@cancel` |
| `cashier` · pos | GET/POST `/cashier/pos` · `cashier.pos.*` | `Cashier\PosController@index/store` → `cashier/pos` |
| `cashier` · orders (tabs = `?tab=` filters, not routes) | GET `/cashier/orders` · `cashier.orders.index` | `Cashier\OrderController@index` → `cashier/orders` |
| `cashier` · orders row actions | PATCH `/cashier/orders/{order}/complete` · POST `…/cancel` · POST `…/void` | `Cashier\OrderController@complete/cancel/void` |
| `cashier` · payment (cash, e-wallet, COD, Senior/PWD) | GET/POST `/cashier/orders/{order}/payment` · `cashier.payments.*` | `Cashier\PaymentController@create/store` → `cashier/payment` |
| `cashier` · receipt (printable) | GET `/cashier/orders/{order}/receipt` · `cashier.receipt` | `Cashier\ReceiptController@show` → `cashier/receipt` |
| `kitchen` · display (New / Preparing / Ready + recently done) | GET `/kitchen` · `kitchen.display` (+ GET `/kitchen/board` poll partial) | `Kitchen\DisplayController@index/board` → `kitchen/display` |
| `kitchen` · ticket actions | PATCH `/kitchen/orders/{order}/status` · `kitchen.orders.status` (`to=preparing\|ready\|completed\|back\|recall`) | `Kitchen\OrderStatusController@update` |
| `kitchen` · sold-out switches | PATCH `/kitchen/menu-items/{menuItem}/availability` | `Kitchen\AvailabilityController@update` |
| `inventory` · ingredients (list, alerts, filters) | GET `/inventory/ingredients` · `inventory.ingredients.index` | `Inventory\IngredientController@index` → `inventory/ingredients` |
| `inventory` · add / edit ingredient (modal) | POST `/inventory/ingredients` · PUT `/inventory/ingredients/{ingredient}` | `Inventory\IngredientController@store/update` |
| `inventory` · restock / set counted amount (modal) | POST `/inventory/ingredients/{ingredient}/restock` | `Inventory\RestockController@store` |
| `inventory` · history | GET `/inventory/ingredients/{ingredient}/history` | `Inventory\MovementController@index` → `inventory/history` |
| `admin` · dashboard | GET `/admin` · `admin.dashboard` | `Admin\DashboardController@index` → `admin/dashboard` |
| `admin` · menu & prices | resource `/admin/menu-items` (index, store, update, destroy) · resource `/admin/categories` (store, update, destroy) | `Admin\MenuItemController`, `Admin\CategoryController` → `admin/menu` *(actual: no separate `/admin/menu` route or `menu-item-form` view — the item editor and categories are modals on the list, as in the mockup)* |
| `admin` · users & roles | resource `/admin/users` (index, store, update — add/edit is a modal) · PATCH `…/{user}/password` · PATCH `…/{user}/active` | `Admin\UserController` → `admin/users` |
| `admin` · audit logs | GET `/admin/audit-logs` · GET `/admin/audit-logs/export` (CSV) | `Admin\AuditLogController@index/export` → `admin/audit-logs` |
| `admin` · reset demo data | GET `/admin/demo-reset` (preview) · POST `/admin/demo-reset` | `Admin\DemoResetController@show/store` |
| *(new)* printable QR standees | GET `/admin/tables/qr` | `Admin\TableQrController@index` → `admin/table-qr` |

Role → route groups: `cashier.*` → cashier + admin · `kitchen.*` → kitchen + admin · `inventory.*` → inventory + admin · `admin.*` → admin only.
Login redirect: cashier → `cashier.pos.index`, kitchen → `kitchen.display`, inventory → `inventory.ingredients.index`, admin → `admin.dashboard`.

---

## 5. Build order

Each phase ends runnable and demoable. Don't start a phase before the previous one's tests pass.

> **Note (post-scaffold):** once the UI scaffold exists, "build X module" means: add the
> migrations/models/policies, then swap the controller's hardcoded arrays for queries and its
> "nothing is saved" redirects for real service calls — keeping Blade markup and
> sample-data-as-seeders intact.

### Stage 2 — UI scaffold *(before Phase 0; steps tracked in `ui-scaffold-checklist.md`)*
- Step 0 — Shell: port `style.css`, drop Tailwind, layouts (staff/customer/kitchen/guest/public), sidebar per role, theme toggle, toast + modal JS, every route in §4 stubbed with its controller.
- Step 1 — Shared components: status pill, modal, stat card, money, order lines, tracker, confirm-form, empty state.
- Step 2 — Customer module · Step 3 — Cashier · Step 4 — Kitchen · Step 5 — Inventory · Step 6 — Admin.
- Step 7 — Home, navigator, login (login form posts, controller just redirects by a `?as=role` demo choice).
- Step 8 — Polish: every mockup screen reachable, every transition has a visible form/button, all routes 200, side-by-side check against the mockup.
- Controllers return hardcoded arrays copied from `BB_DATA`; every form POSTs (`@csrf`) to its real route and gets redirected back with "Demo only — nothing is saved".

### Phase 0 — Foundation *(everything depends on this)*
- `users` migration rework + `Role` enum + `UserSeeder`; `LoginController` (username + `is_active`), role-based redirect, logout.
- `EnsureRole` middleware, `Gate::before` for admin, policies registered (empty rules failing closed).
- `audit_logs` + `AuditLogger` + login/logout/failed listeners; `last_login_at`.
- `larabai_testing` DB + `phpunit.xml` pointing at it; feature tests: each role reaches its home, is refused elsewhere (403), deactivated user can't log in.

### Phase 1 — Reference data
- Migrations 2–9 + models + seeders mirroring `BB_DATA`.
- Admin **Menu & prices** (items, categories, option groups/choices, ingredients per serving) and **Users & roles** CRUD — real forms, Form Requests, audit entries.
- Inventory **ingredient add/edit** (no stock movements yet beyond opening stock as `ADJUST`).

### Phase 2 — Core domain *(tests before any screen touches it)*
- Migrations 10–16, 18 + models + enums.
- `OrderWorkflow` with the §3 state table — one test per row, plus a test per illegal move.
- `Pricing` (line price, Senior/PWD examples incl. 1 of 4 diners, delivery fee), `Sequence` (concurrency-safe), `StockService` (usage per line: cup size, lid, add, replace; deduct/return with movements; refuse below zero), `MenuAvailability`, `PaymentService` (record, void).

### Phase 3 — Customer ordering (spec §3.2)
- Signed QR session (table / counter), session cart with re-sync notices, checkout (dine-in/take-out/delivery + address), simulated GCash/Maya pay, cash → `awaiting_payment`, COD → kitchen, success/receipt, tracker with polling, my orders, cancel.

### Phase 4 — Cashier (spec §3.4)
- POS ticket (one form, JS-built rows) → order; orders list tabs + search; take payment (cash change, e-wallet ref, Senior/PWD with ID details); collect COD; mark served/picked up/delivered; cancel; void with reason + stock return; printable receipt.

### Phase 5 — Kitchen (spec §3.5)
- Board columns from real orders (`paid` + COD), Start/Back/Ready/Done/Recall via forms, polling partial + new-order beep, sold-out switches feeding `MenuAvailability`.

### Phase 6 — Inventory (spec §3.6)
- Stock list with OK/Low/Out, value, filters; summary cards and "can't be made" banner; restock (delivery = `PURCHASE`, count = `ADJUST`) with before/after preview; history per ingredient.

### Phase 7 — Admin dashboard & audit (spec §3.7)
- Dashboard queries (today vs same time last week, 14-day sales, orders by hour, payment mix, best sellers, needs-attention list) passed to the ported chart JS via `@json`; audit log filters (period, module, user, search) + CSV export.

### Phase 8 — Documents & live updates
- Receipt print stylesheet, printable table/counter QR sheet, tune polling intervals; optional Reverb if polling isn't enough.

### Phase 9 — Maintenance
- Reset demo data (preview → type `RESET` → server re-counts → `migrate:fresh --seed`), `backup:db` command.

### Phase 10 — Hardening *(checklist-driven)*
- [ ] Every route/action authorized by policy, not menu visibility (incl. receipt, CSV export, kitchen board poll).
- [ ] No order reachable by a customer except through a `public_token` that's in their session; no staff page reachable without login.
- [ ] Every workflow transition in §3 has a reachable control with a real handler — and nothing else can change status.
- [ ] Prices, discounts, totals and stock quantities computed server-side only; tampered hidden inputs are ignored.
- [ ] Every form has `@csrf`; every destructive action needs a reason or type-to-confirm *and* is re-validated server-side.
- [ ] Stock can't go negative; no double deduction; cancel/void always returns stock for deducted orders.
- [ ] Test coverage: full happy path per order type × payment method, every backward branch (Back, Recall, cancel, void), every role × route combination.
- [ ] Senior/PWD `id_no` / `holder_name` encrypted; passwords hashed; `APP_DEBUG=false` outside local.
- [ ] Session timeout for staff; login throttling (`RateLimiter` on `POST /login`).

---

## 6. Suggested first week

1. Write `ui-scaffold-checklist.md` from §5 Stage 2; do Step 0 (shell, layouts, all routes stubbed) — one step per session.
2. Finish the scaffold steps until every mockup view is a route that returns 200.
3. Phase 0 in full (login, roles, audit log) with its tests.
4. Migrations + seeders that mirror `BB_DATA`; compare seeded screens to the mockup by eye.
5. Write the §3 state table as tests first, then `OrderWorkflow` until they pass.
