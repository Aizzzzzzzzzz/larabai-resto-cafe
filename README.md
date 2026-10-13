# Brew & Bite Café — QR ordering, payment and inventory system

A Laravel web system for a small café in Tarlac City: customers scan the QR code on their table, order and pay
from their phone; the cashier, kitchen, stock clerk and owner each have their own screen. A final project for
**IT52 System Integration and Architecture**, **IT54 Advance Database System** and **IT55 Advance Web Development**
(Dominican College of Tarlac).

> **Status — UI scaffold complete.** Every screen from the static mockup is built as a Laravel page with
> sample data and working forms, but **nothing is saved yet**: forms answer with *"Demo only — nothing is saved"*.
> The database, real login and role protection come next (see [Where we are](#where-we-are)).

## What's in it

| Screen | For | Path |
|---|---|---|
| Café homepage | everyone | `/` |
| Table menu, cart, checkout, simulated GCash / Maya, receipt, order tracker | customers (scan a table QR) | `/order/…` |
| Point of sale, orders, payment with Senior/PWD discount, receipt | cashier | `/cashier/…` |
| Kitchen display, sold-out switches | kitchen | `/kitchen` |
| Stock, restocking, stock history | stock clerk | `/inventory/…` |
| Dashboard, menu & prices, users & roles, audit logs, table QR sheet | owner / admin | `/admin/…` |
| Staff login | staff | `/login` |
| System navigator (a launch pad for demos) | presenter, demo mode only | `/navigator` |

## Requirements

- PHP **8.2+** with the `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl` and `zip` extensions
- Composer 2, Node.js 20+ and npm
- MySQL 8 (Laragon's works out of the box)

## Setting up from a fresh clone

```bash
git clone https://github.com/Aizzzzzzzzzz/larabai-resto-cafe.git
cd larabai-resto-cafe

# 1. Create the database (Laragon: start MySQL first; the default user is root with no password)
mysql -uroot -e "CREATE DATABASE larabai_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install, configure, migrate and build — one command
composer setup
```

`composer setup` installs the PHP and JavaScript packages, copies `.env.example` to `.env`, generates the app key,
runs the migrations and builds the CSS/JS. Then open the site:

- **Laragon:** reload Laragon (Menu → Reload) and open `http://larabai-resto-cafe.test`
- **Anywhere else:** `php artisan serve` and open `http://127.0.0.1:8000` (set `APP_URL` in `.env` to that address,
  because the table QR codes are built from it)

For development with hot reload of the CSS/JS use `composer dev`.

> MySQL must be running whenever you open the site: the app keeps sessions in the database.

### Settings in `.env`

| Setting | Default | Meaning |
|---|---|---|
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `larabai_database`, `root`, *(empty)* | the MySQL connection |
| `APP_URL` | `http://larabai-resto-cafe.test` | the café's address; printed into the table QR codes |
| `CAFE_DEMO_MODE` | `true` | shows the system navigator, the demo accounts on the login page and *Reset demo data*. **Set to `false` for a real café.** |
| `APP_TIMEZONE` | `Asia/Manila` | the café's clock |

## Trying it

1. Open `/navigator` (or `/login`) and use the **demo accounts**:

   | Role | Username / password | Opens |
   |---|---|---|
   | Owner / admin | `admin` / `admin123` | Dashboard |
   | Cashier | `cashier` / `cashier123` (also `cashier2`) | Point of sale |
   | Kitchen | `kitchen` / `kitchen123` | Kitchen display |
   | Stock clerk | `stock` / `stock123` | Stock |

2. Click a table on the navigator's floor plan — that is the same as scanning its QR code — and place an order.
3. Print the real QR codes from **Admin → Table QR codes**.

The demo passwords are plain text in the sample data and shown only in demo mode; real accounts will be hashed.

## Tests

```bash
php artisan test
```

Over 200 tests cover every module, plus a crawler that follows every link and form on every page, a check that each
workflow step has a button, and a lock on the route list. They run without a database.

## How it is built

Server-rendered **Blade** pages, controllers and ordinary HTML forms with `@csrf` — **no Livewire**, no SPA.
A little plain JavaScript (`resources/js`) handles previews and small interactions; the server always re-checks
every price, total and quantity. The look comes from the mockup's stylesheet, kept unchanged in `resources/css/mockup.css`.

```
app/Http/Controllers/<Module>/   thin controllers: validate → check rules → redirect with a message
app/Http/Requests/<Module>/      form validation
app/Services/                    Pricing (discounts, VAT), CustomerCart, StockUsage, QrCodes
app/Support/                     sample data (DemoData…), rules shared by screens, formatting
resources/views/<module>/        Blade pages;  components/ = shared pieces
larabai-mockup/                  the static mockup, the project document and the build plan
```

## Where we are

The project follows [`larabai-mockup/development-playbook.md`](larabai-mockup/development-playbook.md):
spec → mockup → **UI scaffold (done)** → real build.

- Order flow guide: [`larabai-mockup/order-flow-guide.md`](larabai-mockup/order-flow-guide.md) — one order from QR scan to dashboard: statuses, tables written at each step, stock, audit
- Plan: [`larabai-mockup/development-plan.md`](larabai-mockup/development-plan.md) — data model, order state table, route map, build phases
- Scaffold checklist: [`larabai-mockup/ui-scaffold-checklist.md`](larabai-mockup/ui-scaffold-checklist.md)
- Project document: [`larabai-mockup/document.md`](larabai-mockup/document.md)

**Next — Phase 0 onward:** real login and hashed passwords, `auth` + role protection on every staff screen, the MySQL
tables and seeders (the sample data becomes the seed data), the order state machine, then each module's real writes.
Until Phase 0, any visitor can open any staff screen by its address.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
