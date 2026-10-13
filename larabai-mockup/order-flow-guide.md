# Order flow guide — where every piece of data goes

Follow one order from the moment a customer scans the table QR until the sale shows up on the owner's dashboard.
For each step: **who does it → which screen → which tables are written → what the status becomes**.

> Names in `code style` are real table / column names in the MySQL database (`larabai_database`).
> The code that does each step is in the last column of the summary tables, so you can open it during a demo or a defense.

---

## 1. The big picture

```
 CUSTOMER (phone)              CASHIER (till)               KITCHEN (screen)            STOCK CLERK            OWNER
 ───────────────               ──────────────               ────────────────            ───────────            ─────
 scan QR ─► menu ─► cart
          │
          ▼
       checkout ───────────────────────────────┐
          │                                    │
   pay by GCash/Maya                      pay cash at counter
   (simulated wallet)                     (or COD to the rider)
          │                                    │
          ▼                                    ▼
   ┌─────────────────────────  ORDER IS PAID  ─────────────────────────┐
   │  payments row · stock deducted · status log · audit log          │
   └───────────────────────────────┬───────────────────────────────────┘
                                   ▼
                          Start ─► Preparing ─► Ready ─► Served / Picked up / Delivered
                          (kitchen)   (kitchen)   (kitchen or cashier)
                                   │
                                   ▼
                            COMPLETED  ─────────────────────────────────►  Dashboard (sales, best sellers)
                                                                           Audit log (who did what)
                                                                           Stock history (what was used)
```

Three rules hold everywhere:

1. **The browser never sends a price.** It only sends item codes, option codes, notes and quantities. The server looks
   up every name and price (`App\Services\OrderService`).
2. **Only one class changes an order's status:** `App\Services\OrderWorkflow`. Only one class changes stock:
   `App\Services\StockService`. Every change is written in one database transaction, so it is all-or-nothing.
3. **Every important action leaves a line in the audit log** (`audit_logs`), written by `App\Services\AuditLogger`.

---

## 2. Order statuses

`orders.status` is always one of these:

| Status | Shown to customer as | Meaning |
|---|---|---|
| `awaiting_payment` | Awaiting payment | Order placed, not paid yet. (Cash on delivery also sits here while it cooks.) |
| `paid` | Order received | Paid; waiting in the kitchen's **New** column |
| `preparing` | Preparing | Kitchen pressed **Start** |
| `ready` | Ready / Out for delivery / Ready for pickup | Kitchen pressed **Ready** |
| `completed` | Enjoy! | Served, picked up or delivered |
| `cancelled` | Order cancelled | Cancelled **before** it was paid |
| `voided` | Order voided | Cancelled **after** it was paid (money refunded, stock returned) |

"Is it paid?" is not a status. It is `orders.paid_at IS NOT NULL` (this is how a cash-on-delivery order can be
`preparing` and still unpaid).

### Allowed moves (the state table)

| # | From → To | Who | Button | Class method |
|---|---|---|---|---|
| 1 | — → `awaiting_payment` | Customer / Cashier | Place order / Charge | `OrderService::place` |
| 2 | `awaiting_payment` → `paid` | Customer | Pay (GCash / Maya) | `OrderWorkflow::payWithWallet` |
| 3 | `awaiting_payment` → `paid` | Cashier | Confirm payment | `OrderWorkflow::takePayment` |
| 4 | `awaiting_payment` → `cancelled` | Customer / Cashier | Cancel order | `OrderWorkflow::cancel` |
| 5 | `paid` (or COD) → `preparing` | Kitchen | Start preparing | `OrderWorkflow::kitchenMove('start')` |
| 6 | `preparing` → `paid` (or COD) | Kitchen | Back | `kitchenMove('back')` |
| 7 | `preparing` → `ready` | Kitchen | Mark ready | `kitchenMove('ready')` |
| 8 | `ready` → `completed` | Cashier or Kitchen | Mark served / picked up / delivered · Served | `OrderWorkflow::complete` · `kitchenMove('done')` |
| 9 | `completed` → `ready` | Kitchen | Recall | `kitchenMove('recall')` |
| 9b | `ready` → `preparing` | Kitchen | Recall | `kitchenMove('recall')` |
| 10 | `paid` / `preparing` → `voided` | Cashier | Void (reason required) | `OrderWorkflow::void` |

Anything not in this table is refused (`IllegalTransition`, shown as a 403). Examples: paying twice, cancelling a paid
order, voiding a ready order, marking an unpaid cash-on-delivery order as delivered.

---

## 3. The journey, step by step

### Step 0 — Scan the QR code

| | |
|---|---|
| **Who / screen** | Customer · `GET /t/{table}` (signed link) or `GET /counter` |
| **Reads** | `dining_tables` — the table must exist and be `is_active` |
| **Writes to the database** | Nothing |
| **Writes to the session** | `qr = {type: dine-in, table: 3}` (counter QR: `{type: counter}`) |
| **Good to know** | The link is **signed**, so changing `/t/3` to `/t/4` by hand is refused (403). A table QR allows *dine-in* or *take-out*; the counter QR allows *take-out* or *delivery*. Scanning a different QR starts an empty cart. |

### Step 1 — The menu

| | |
|---|---|
| **Screen** | `GET /order/menu` |
| **Reads** | `categories`, `menu_items` (only `is_active`), `option_groups`, `option_choices`, `item_ingredients` + `ingredients` |
| **Sold out?** | `menu_items.sold_out_override` wins if set (kitchen / owner switch). Otherwise the item is sold out when any ingredient of **one serving** is short — the recipe, plus a 12 oz cup and a lid for drinks. (`App\Services\Catalog`) |
| **Photo** | `menu_items.image_path` if the owner uploaded one; otherwise the drawn glass / plate |

### Step 2 — Customise and add to the cart

| | |
|---|---|
| **Screen** | Item sheet → `POST /order/cart` |
| **Writes to the database** | Nothing |
| **Writes to the session** | `cart` = list of lines `{menu_item code, choices, notes, qty, name, unit_price}` |
| **Checks** | Item still on the menu and not sold out; every option belongs to that item; required options (e.g. size) chosen; quantity 1–20 |
| **Re-pricing** | Each time the cart or checkout opens, prices are re-read from `menu_items` and `option_choices`. If a price changed or an item was removed, the customer sees a notice. |

### Step 3 — Checkout: the order is created

| | |
|---|---|
| **Screen** | `GET /order/checkout` → `POST /order/checkout` |
| **Customer chooses** | Dine-in / take-out / delivery (+ address), and GCash / Maya / Cash |
| **Class** | `OrderService::place()` — one transaction |

Rows written, in order:

| # | Table | What is written |
|---|---|---|
| 1 | `daily_sequences` | Counter for prefix `BB` today is increased (row-locked, so two orders never get the same number) |
| 2 | `orders` | `order_no` = `BB-20261014-0001`, `public_token` (random, 40 chars), `source=qr`, `order_type`, `dining_table_id`, address, `subtotal`, `delivery_fee` (₱50 for delivery), `total`, `payment_method`, **`status=awaiting_payment`**, `stock_deducted=0`, `created_by=NULL` |
| 3 | `order_items` | One row per cart line: `menu_item_id`, **`item_name`** and **`unit_price` copied at this moment**, `qty`, `notes` |
| 4 | `order_item_options` | One row per chosen option: `option_choice_id`, `group_code`, `choice_code`, **`choice_name`**, **`extra_price`** (also copied) |
| 5 | `order_status_logs` | `awaiting_payment`, `changed_by=NULL` (the customer) |

Why names and prices are **copied**: if the owner changes a price or renames an item later, old orders and receipts
must not change.

Also at this step:

- **Session:** the order's token is added to `my_orders`. A customer can open an order link **only** if its token is in
  their own session — a forwarded or guessed link gives a 404.
- **Cash on delivery** (delivery + cash): the kitchen starts cooking before the rider is paid, so stock is deducted
  **now** (see step 4c).
- **Cart:** cleared for cash orders. For GCash / Maya it stays until the wallet screen confirms.

### Step 4 — Payment (three ways)

#### 4a. GCash / Maya (simulated wallet) — by the customer

`GET /order/{token}/pay` → `POST /order/{token}/pay` · `OrderWorkflow::payWithWallet()` (one transaction)

| # | Table | What changes |
|---|---|---|
| 1 | `ingredients` | `stock_qty` goes **down** by what the order uses (see section 5) |
| 2 | `stock_movements` | One `SALE` row per ingredient: `qty` (negative), `qty_before`, `qty_after`, `cost`, `order_id` |
| 3 | `orders` | `stock_deducted=1` |
| 4 | `daily_sequences` | Counter for `GC` (GCash) or `MY` (Maya) increases |
| 5 | `payments` | `method`, `amount`, `reference_no` = `GC-20261014-0001`, **`status=PAID`**, `received_by=NULL`, `paid_at` |
| 6 | `orders` | **`paid_at`** set, **`status=paid`** |
| 7 | `order_status_logs` | `paid` |
| 8 | `audit_logs` | `Payment recorded` — by *Customer (QR)* |

If stock ran out between ordering and paying, **everything above is rolled back**: nothing is charged, the order stays
`awaiting_payment`, and the customer is told which item can't be made. Pressing Pay twice does not charge twice.

#### 4b. Cash (or any method) at the counter — by the cashier

`GET /cashier/orders/{id}/payment` → `POST …/payment` · `OrderWorkflow::takePayment()`

Same as 4a, plus:

| Extra | Table | What |
|---|---|---|
| Senior / PWD discount (optional) | `discounts` | `discount_type`, **`id_no` and `holder_name` stored encrypted**, `eligible_count`, `diner_count`, `amount` |
| | `orders` | `discount_amount` and a lower `total` (server-computed: share = subtotal × eligible ÷ diners; remove 12% VAT; take 20% off) |
| Cash | `payments` | `amount_tendered`, `change_due` (worked out by the server; must cover the total) |
| Who | `payments.received_by`, `order_status_logs.changed_by` | The cashier's user id |
| | `audit_logs` | `Applied discount` (only the last 4 characters of the ID), `Payment recorded` |
| Method | `orders.payment_method` | Updated to what was actually used at the counter |

A **point-of-sale order** (cashier charges a ticket) starts the same way as step 3 but with `source=pos`,
`created_by=<cashier>` and audit `Created counter order`; the cashier then lands on this payment screen.

#### 4c. Cash on delivery — collected by the cashier later

The order was created in step 3 with stock already deducted and went to the kitchen **unpaid**. When the rider returns
with the cash, the cashier uses *Collect COD payment* (same screen as 4b). The order **keeps its kitchen status**;
only `payments`, `orders.paid_at`, the audit log and the `CS` counter change. An unpaid COD order **cannot** be marked
delivered.

### Step 5 — The kitchen

`GET /kitchen` (the board refreshes every ~5 seconds) · buttons post to `PATCH /kitchen/orders/{id}/status`
· `OrderWorkflow::kitchenMove()`

| Column on the board | Which orders | Button | New status |
|---|---|---|---|
| **New** | `paid`, plus unpaid COD | Start preparing | `preparing` |
| **Preparing** | `preparing` | Mark ready / Back | `ready` / back to `paid` (or `awaiting_payment` for COD) |
| **Ready** | `ready` | Served / Picked up / Handed to rider · Recall | `completed` / back to `preparing` |
| *Recently done* strip | `completed` | ↺ Recall | back to `ready` |

Each move writes: `orders.status` → a new row in `order_status_logs` (who + when) → an `audit_logs` line
(`Started preparing`, `Marked ready`, `Completed order`, `Moved back to new orders`, `Recalled order`).

The timers use `orders.paid_at` (or `created_at` for COD) as "arrived in the kitchen"; the average prep time on the board
is the gap from that moment to the `ready` log line.

The kitchen's sold-out drawer sets `menu_items.sold_out_override` (and logs `Marked sold out` / `Marked available`).
It takes effect on the customer menu and the POS immediately.

### Step 6 — Serve and complete

| | |
|---|---|
| **Cashier** | *Orders → In progress* → Mark served / picked up / delivered · `PATCH /cashier/orders/{id}/complete` |
| **Or the kitchen** | The "Served / Picked up / Handed to rider" button |
| **Writes** | `orders.status=completed`, `order_status_logs`, `audit_logs` (`Completed order`) |
| **Customer** | The tracker (`/order/{token}`, polls every few seconds) changes to "Enjoy!" |

### Step 7 — The receipt

`GET /cashier/orders/{id}/receipt` — read-only. Built from `orders`, `order_items`, `order_item_options`, `payments`
and `discounts` (decrypted here). VAT lines are computed (prices include 12% VAT; the delivery fee is outside it; the
Senior/PWD share is VAT-exempt). A voided order's receipt is stamped VOIDED with the reason.

---

## 4. What can go wrong — and where the data goes

| Situation | Who | What happens to data |
|---|---|---|
| Customer cancels before paying | Customer / Cashier | `status=cancelled`, `cancel_reason`; if COD, stock is **returned** (`RETURN` movements, `stock_deducted=0`); log + audit `Cancelled order` |
| Paid order is voided | Cashier (reason required) | `payments.status=VOIDED`, `voided_at`, `void_reason`; `orders.status=voided`; stock **returned**; audit `Voided order`. Only `paid` / `preparing` orders can be voided. |
| Not enough stock at payment | Customer / Cashier | Whole payment rolled back: no payment row, no stock change, order still unpaid; an error toast names the item |
| Item runs out while in someone's cart | Customer | Checkout is refused with "Sorry, X just sold out" |
| Two people press buttons at once | Anyone | The order row is locked; the second one is refused (403) |
| Someone opens an order link that isn't theirs | Anyone | 404 for customers; staff pages need a login and the right role (403 otherwise) |

---

## 5. Stock: what an order uses

`StockService` deducts stock **once** when an order is paid (or when a COD order is placed) and returns it **once** on
cancel/void. `orders.stock_deducted` prevents doing either twice.

For each order line, quantity used = (recipe + cup + lid + option changes) × qty:

| Part | Where it comes from |
|---|---|
| Recipe | `item_ingredients.qty_per_serving` (same at every size) |
| Cup and lid | Drinks only: a 12 oz cup (Large = 16 oz cup) and 1 lid — fixed rule |
| Add-ons | `choice_ingredients` with `action=add` (e.g. extra espresso shot = +9 g beans) |
| Swaps | `choice_ingredients` with `action=replace` (oat milk **replaces** the fresh milk, taking the same amount) |

Example: **2 × iced large café latte with oat milk** → beans 36 g, **oat milk 360 ml (no fresh milk)**, 2 × 16 oz cups,
2 lids. Stock never goes below zero.

Every change to `ingredients.stock_qty` writes a row in `stock_movements` (`qty_before` → `qty_after`), so each
ingredient's history always adds up:

| `type` | When | Sign |
|---|---|---|
| `SALE` | Order paid / COD placed | − |
| `RETURN` | Order cancelled or voided after stock was taken | + |
| `PURCHASE` | Stock clerk adds a delivery | + |
| `ADJUST` | Stock clerk's physical count, or an ingredient's opening stock | + or − |

---

## 6. Numbers you will see

| Number | Format | Table.column | Counter in `daily_sequences` |
|---|---|---|---|
| Order | `BB-20261014-0001` | `orders.order_no` | prefix `BB`, restarts each day |
| GCash payment | `GC-20261014-0001` | `payments.reference_no` | prefix `GC` |
| Maya payment | `MY-20261014-0001` | `payments.reference_no` | prefix `MY` |
| Cash payment | `CS-20261014-0001` | `payments.reference_no` | prefix `CS` |
| Customer's link | 40 random characters | `orders.public_token` | — (never shows the order id) |

---

## 7. Which screen reads what

| Screen | Path | Role | Main tables read |
|---|---|---|---|
| Menu / cart / checkout | `/order/…` | Customer | `menu_items`, `option_*`, `ingredients`, `item_ingredients` |
| Tracker / my orders | `/order/{token}` | Customer (own token) | `orders`, `order_items`, `order_status_logs`, `payments` |
| Point of sale | `/cashier/pos` | Cashier, Owner | same as the menu |
| Orders list, payment, receipt | `/cashier/orders…` | Cashier, Owner | `orders`, `payments`, `discounts`, `order_*` (today's orders + any still open) |
| Kitchen display | `/kitchen` | Kitchen, Owner | `orders` (today + open), `order_status_logs`, `menu_items` |
| Stock, restock, history | `/inventory/…` | Stock clerk, Owner | `ingredients`, `stock_movements` |
| Dashboard | `/admin` | Owner | `orders` + `payments` (paid, not voided, by `paid_at`), `order_items`, `audit_logs` |
| Menu & prices, categories | `/admin/menu-items` | Owner | `menu_items`, `categories`, recipes |
| Users & roles | `/admin/users` | Owner | `users` |
| Audit logs (+ CSV) | `/admin/audit-logs` | Owner | `audit_logs` |
| Table QR sheet | `/admin/tables/qr` | Owner | `dining_tables` |

Dashboard figures count an order when it has `paid_at` and is not `voided`, grouped by the day/hour it was paid.

---

## 8. All tables at a glance

```
users ───────────────┬──────────────────────────────┐
                     │ created_by / received_by /    │ user_id
                     │ changed_by                    ▼
categories ─► menu_items ─► option_groups ─► option_choices          audit_logs
                 │  │                              │
                 │  └─► item_ingredients ─┐        └─► choice_ingredients ─┐
                 │                        ▼                                ▼
                 │                    ingredients ◄───────────── stock_movements
                 │                                                    ▲ order_id
dining_tables ─► orders ─┬─► order_items ─► order_item_options (copies of names/prices)
                         ├─► order_status_logs   (every status change: who, when)
                         ├─► payments            (one per order; PAID or VOIDED)
                         └─► discounts           (Senior/PWD; ID and name encrypted)

daily_sequences   (the running BB / GC / MY / CS numbers)
sessions · cache · jobs   (Laravel's own)
```

---

## 9. Peek at the data yourself (MySQL)

```sql
-- One order from top to bottom (change the number)
SELECT order_no, status, total, payment_method, paid_at, stock_deducted FROM orders WHERE order_no = 'BB-20261014-0001';
SELECT item_name, qty, unit_price, notes FROM order_items WHERE order_id = 14;
SELECT status, changed_at, changed_by FROM order_status_logs WHERE order_id = 14 ORDER BY id;
SELECT method, amount, reference_no, status, amount_tendered, change_due FROM payments WHERE order_id = 14;

-- What the order took out of stock
SELECT i.name, m.type, m.qty, m.qty_before, m.qty_after
FROM stock_movements m JOIN ingredients i ON i.id = m.ingredient_id WHERE m.order_id = 14;

-- Who did what, newest first
SELECT logged_at, user_name, role, action, detail FROM audit_logs ORDER BY id DESC LIMIT 20;

-- Today's sales (what the dashboard shows)
SELECT COUNT(*) AS orders, SUM(total) AS sales FROM orders
WHERE paid_at >= CURDATE() AND status <> 'voided';
```

---

## 10. Quick demo script

1. Open `/navigator`, click **Table 5** (same as scanning its QR), order a latte, choose **GCash**, press **Pay**.
   → `orders` (paid), `payments` (GC-…), stock down, audit line.
2. Log in as `kitchen` (`kitchen123`): the order is in **New** → Start → Mark ready.
   → `order_status_logs` grows; the customer's tracker follows.
3. Log in as `cashier` (`cashier123`): **Orders → In progress** → *Mark served*. → `completed`.
4. As `stock` (`stock123`): open the latte's ingredients → **History**: the `SALE` rows are there.
5. As `admin` (`admin123`): the dashboard shows the sale; **Audit logs** lists every step with who did it.
6. Try the exceptions: pay with not enough stock, void a paid order (stock returns), cancel an unpaid one.

Class map for questions: **OrderService** (create) · **OrderWorkflow** (status) · **PaymentService** (payments, discount)
· **StockService** (stock) · **AuditLogger** (audit) · **Sequence** (numbers) · **Catalog** (menu / sold out) ·
**Reports** (dashboard) — all in `app/Services`.
