<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\IllegalTransition;
use App\Exceptions\InsufficientStock;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\Sequence;
use App\Services\StockService;
use Tests\TestCase;

/**
 * The order state table (development-plan.md §3), one test per row, then the moves it forbids, then the stock
 * rules underneath: once-only deduction, giving back, refusal below zero, and a history that always adds up.
 */
class WorkflowTest extends TestCase
{
    private OrderWorkflow $workflow;

    private OrderService $orders;

    private User $cashier;

    private User $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = app(OrderWorkflow::class);
        $this->orders = app(OrderService::class);
        $this->cashier = User::where('username', 'cashier')->first();
        $this->kitchen = User::where('username', 'kitchen')->first();
    }

    /** A new order for the given type and payment method. One iced large oat latte ×2 unless told otherwise. */
    private function place(string $type = 'dine-in', string $method = 'CASH', string $source = 'qr', ?int $table = 3, ?array $lines = null): Order
    {
        return $this->orders->place($lines ?? [[
            'menu_item' => 'latte', 'qty' => 2, 'notes' => null,
            'options' => [['group' => 'temp', 'choice' => 'iced'], ['group' => 'size', 'choice' => 'l'], ['group' => 'addons', 'choice' => 'oat']],
        ]], [
            'source' => $source, 'order_type' => $type, 'table_no' => $type === 'dine-in' ? $table : null, 'payment_method' => $method,
            'delivery_address' => $type === 'delivery' ? '1 Rizal St.' : null,
        ], $source === 'pos' ? $this->cashier : null);
    }

    private function stock(string $code): float
    {
        return Ingredient::where('code', $code)->value('stock_qty');
    }

    private function stages(Order $order): array
    {
        return $order->fresh()->statusLogs->pluck('status')->map->value->all();
    }

    private function audit(): array
    {
        return AuditLog::orderByDesc('id')->limit(5)->pluck('action')->all();
    }

    // ---- The state table, row by row -----------------------------------------------------------

    public function test_1_an_order_starts_awaiting_payment_with_a_number_and_a_log(): void
    {
        $order = $this->place();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(['awaiting_payment'], $this->stages($order));
        $this->assertMatchesRegularExpression('/^BB-\d{8}-0014$/', $order->order_no);
        $this->assertFalse($order->stock_deducted);
    }

    public function test_1_cash_on_delivery_takes_the_stock_out_when_it_is_placed(): void
    {
        $beans = $this->stock('beans');
        $order = $this->place('delivery', 'CASH');

        $this->assertTrue($order->stock_deducted);
        $this->assertSame($beans - 36, $this->stock('beans'));
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status, 'COD stays unpaid');
    }

    public function test_2_a_wallet_payment_moves_to_paid_and_deducts_stock(): void
    {
        $order = $this->place('dine-in', 'GCASH');
        $beans = $this->stock('beans');

        $payment = $this->workflow->payWithWallet($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertTrue($order->stock_deducted);
        $this->assertSame($beans - 36, $this->stock('beans'));
        $this->assertMatchesRegularExpression('/^GC-\d{8}-0009$/', $payment->reference_no);
        $this->assertSame(['awaiting_payment', 'paid'], $this->stages($order));
        $this->assertContains('Payment recorded', $this->audit());
    }

    public function test_3_the_cashier_takes_payment(): void
    {
        $order = $this->place('take-out', 'CASH', 'pos');

        $payment = $this->workflow->takePayment($order, $this->cashier, PaymentMethod::Cash, null, 500);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame([410.0, 500.0, 90.0], [$payment->amount, $payment->amount_tendered, $payment->change_due]);
        $this->assertSame($this->cashier->id, $payment->received_by);
    }

    public function test_3_the_senior_pwd_discount_is_worked_out_on_the_server(): void
    {
        $order = $this->place('take-out', 'CASH', 'pos');
        $this->assertSame(410.0, $order->total);

        $this->workflow->takePayment($order, $this->cashier, PaymentMethod::Cash, ['type' => DiscountType::Pwd, 'id_no' => 'PWD-1', 'holder' => 'Ana', 'eligible' => 1, 'diners' => 2], 500);

        $order->refresh();
        // ₱410 shared by 2, one PWD: share ₱205 → ÷1.12 → ×0.80 → discount ₱58.57
        $this->assertSame([58.57, 351.43], [$order->discount_amount, $order->total]);
        $this->assertSame(351.43, $order->payment->amount);
        $this->assertSame(148.57, $order->payment->change_due);
    }

    public function test_3_cash_must_cover_the_total(): void
    {
        $order = $this->place('take-out', 'CASH', 'pos');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->workflow->takePayment($order, $this->cashier, PaymentMethod::Cash, null, 100);
        } finally {
            $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status, 'nothing was half-done');
            $this->assertNull($order->fresh()->payment);
            $this->assertFalse($order->fresh()->stock_deducted);
        }
    }

    public function test_4_an_unpaid_order_can_be_cancelled_and_cod_stock_comes_back(): void
    {
        $plain = $this->place();
        $this->workflow->cancel($plain, 'Duplicate order', $this->cashier);
        $this->assertSame(OrderStatus::Cancelled, $plain->fresh()->status);

        $beans = $this->stock('beans');
        $cod = $this->place('delivery', 'CASH');
        $this->assertSame($beans - 36, $this->stock('beans'));

        $this->workflow->cancel($cod, 'Cancelled by customer', null);

        $this->assertSame($beans, $this->stock('beans'));
        $this->assertFalse($cod->fresh()->stock_deducted);
        $this->assertSame(['awaiting_payment', 'cancelled'], $this->stages($cod));
    }

    public function test_5_to_9_the_kitchen_moves_through_every_stage_and_back(): void
    {
        $order = $this->place('dine-in', 'GCASH');
        $this->workflow->payWithWallet($order);

        $move = fn (string $m) => $this->workflow->kitchenMove($order, $m, $this->kitchen)->value;

        $this->assertSame('preparing', $move('start'));   // 5
        $this->assertSame('paid', $move('back'));         // 6
        $this->assertSame('preparing', $move('start'));
        $this->assertSame('ready', $move('ready'));       // 7
        $this->assertSame('preparing', $move('recall'));  // 9b
        $this->assertSame('ready', $move('ready'));
        $this->assertSame('completed', $move('done'));    // 8 (kitchen side)
        $this->assertSame('ready', $move('recall'));      // 9

        $this->assertSame(['awaiting_payment', 'paid', 'preparing', 'paid', 'preparing', 'ready', 'preparing', 'ready', 'completed', 'ready'], $this->stages($order));
        $this->assertContains('Recalled order', $this->audit());
    }

    public function test_8_the_cashier_completes_a_ready_order(): void
    {
        $this->workflow->complete(Order::find(6), $this->cashier);

        $this->assertSame(OrderStatus::Completed, Order::find(6)->status);
        $this->assertContains('Completed order', $this->audit());
    }

    public function test_10_a_paid_order_can_be_voided_and_everything_goes_back(): void
    {
        $order = $this->place('dine-in', 'GCASH');
        $beans = $this->stock('beans');
        $this->workflow->payWithWallet($order);
        $this->workflow->kitchenMove($order, 'start', $this->kitchen);
        $this->assertSame($beans - 36, $this->stock('beans'));

        $this->workflow->void($order, 'Wrong item entered', $this->cashier);

        $order->refresh();
        $this->assertSame(OrderStatus::Voided, $order->status);
        $this->assertSame('VOIDED', $order->payment->status->value);
        $this->assertSame($beans, $this->stock('beans'));
        $this->assertFalse($order->stock_deducted);
        $this->assertSame('Voided order', $this->audit()[0]);
    }

    public function test_cod_collection_keeps_the_stage_and_cannot_complete_before_the_cash(): void
    {
        $order = $this->place('delivery', 'CASH');
        $this->workflow->kitchenMove($order, 'start', $this->kitchen);
        $this->workflow->kitchenMove($order, 'ready', $this->kitchen);

        try {
            $this->workflow->complete($order, $this->cashier);
            $this->fail('an unpaid COD order was delivered');
        } catch (IllegalTransition) {
            $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        }

        $this->workflow->takePayment($order, $this->cashier, PaymentMethod::Cash, null, 1000);
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status, 'collecting cash does not move the order');
        $this->workflow->complete($order, $this->cashier);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    // ---- Everything the table does not allow -------------------------------------------------------

    public static function illegalMoves(): array
    {
        return [
            'pay twice' => [8, fn (OrderWorkflow $w, Order $o, User $c) => $w->takePayment($o, $c, PaymentMethod::Cash, null, 5000)],
            'wallet-pay a cash order' => [12, fn (OrderWorkflow $w, Order $o) => $w->payWithWallet($o)],
            'wallet-pay a paid order' => [8, fn (OrderWorkflow $w, Order $o) => $w->payWithWallet($o)],
            'cancel a paid order' => [8, fn (OrderWorkflow $w, Order $o, User $c) => $w->cancel($o, 'x', $c)],
            'cancel a completed order' => [1, fn (OrderWorkflow $w, Order $o, User $c) => $w->cancel($o, 'x', $c)],
            'void an unpaid order' => [12, fn (OrderWorkflow $w, Order $o, User $c) => $w->void($o, 'x', $c)],
            'void a ready order' => [6, fn (OrderWorkflow $w, Order $o, User $c) => $w->void($o, 'x', $c)],
            'void a completed order' => [1, fn (OrderWorkflow $w, Order $o, User $c) => $w->void($o, 'x', $c)],
            'void a voided order' => [4, fn (OrderWorkflow $w, Order $o, User $c) => $w->void($o, 'x', $c)],
            'pay a cancelled order' => [5, fn (OrderWorkflow $w, Order $o, User $c) => $w->takePayment($o, $c, PaymentMethod::Cash, null, 5000)],
            'complete a preparing order' => [8, fn (OrderWorkflow $w, Order $o, User $c) => $w->complete($o, $c)],
            'start a cooking order' => [8, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'start', $c)],
            'start an unpaid dine-in order' => [12, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'start', $c)],
            'ready a paid order' => [10, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'ready', $c)],
            'finish a preparing order' => [8, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'done', $c)],
            'back a ready order' => [6, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'back', $c)],
            'recall a cancelled order' => [5, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'recall', $c)],
            'recall a voided order' => [4, fn (OrderWorkflow $w, Order $o, User $c) => $w->kitchenMove($o, 'recall', $c)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('illegalMoves')]
    public function test_the_moves_the_table_forbids_throw_and_change_nothing(int $id, callable $move): void
    {
        $before = Order::find($id);
        $stock = Ingredient::pluck('stock_qty', 'code')->all();
        $movements = StockMovement::count();
        $logs = $before->statusLogs->count();

        try {
            $move($this->workflow, $before, $this->cashier);
            $this->fail('the move was allowed');
        } catch (IllegalTransition $e) {
            $this->assertStringContainsString($before->order_no, $e->getMessage());
        }

        $after = Order::find($id);
        $this->assertSame($before->status, $after->status);
        $this->assertSame($logs, $after->statusLogs->count());
        $this->assertSame($stock, Ingredient::pluck('stock_qty', 'code')->all());
        $this->assertSame($movements, StockMovement::count());
    }

    // ---- Stock rules ---------------------------------------------------------------------------

    public function test_stock_is_deducted_once_and_returned_once(): void
    {
        $order = $this->place('delivery', 'CASH');
        $stock = app(StockService::class);
        $milkAfterOrder = $this->stock('oat');

        $stock->deduct($order->fresh());
        $stock->deduct($order->fresh());
        $this->assertSame($milkAfterOrder, $this->stock('oat'), 'a second deduct does nothing');

        $stock->giveBack($order->fresh(), null, 'test');
        $stock->giveBack($order->fresh(), null, 'test');
        $this->assertSame($milkAfterOrder + 360, $this->stock('oat'), 'a second give-back does nothing');
    }

    public function test_an_order_that_cannot_be_covered_is_refused_without_a_partial_deduction(): void
    {
        $order = $this->place('dine-in', 'GCASH');
        Ingredient::where('code', 'oat')->update(['stock_qty' => 100]); // beans are fine, the oat milk is not
        $beans = $this->stock('beans');
        $movements = StockMovement::count();

        try {
            $this->workflow->payWithWallet($order);
            $this->fail('payment went through');
        } catch (InsufficientStock $e) {
            $this->assertStringContainsString('Café Latte', $e->getMessage());
        }

        $this->assertSame($beans, $this->stock('beans'), 'the beans were not taken either');
        $this->assertSame(100.0, $this->stock('oat'));
        $this->assertSame($movements, StockMovement::count());
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->payment);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_the_last_serving_can_be_sold_but_not_one_more(): void
    {
        Ingredient::where('code', 'beans')->update(['stock_qty' => 36]); // exactly two lattes
        $first = $this->place('dine-in', 'GCASH');
        $second = $this->place('dine-in', 'GCASH');

        $this->workflow->payWithWallet($first);
        $this->assertSame(0.0, $this->stock('beans'));

        $this->expectException(InsufficientStock::class);
        $this->workflow->payWithWallet($second);
    }

    public function test_every_ingredients_history_adds_up_to_its_stock(): void
    {
        // A busy hour: pay, cook, void, cancel a COD order, restock, count
        $a = $this->place('dine-in', 'GCASH');
        $this->workflow->payWithWallet($a);
        $this->workflow->void($a, 'Duplicate order', $this->cashier);
        $b = $this->place('delivery', 'CASH');
        $this->workflow->cancel($b, 'Cancelled by customer', null);
        $c = $this->place('dine-in', 'MAYA');
        $this->workflow->payWithWallet($c);
        app(StockService::class)->restock(Ingredient::where('code', 'beans')->first(), 500, 'Delivery', User::where('username', 'stock')->first());
        app(StockService::class)->count(Ingredient::where('code', 'oat')->first(), 1000, null, User::where('username', 'stock')->first());

        foreach (Ingredient::all() as $ingredient) {
            $moves = $ingredient->movements()->orderBy('moved_at')->orderBy('id')->get();
            if ($moves->isEmpty()) {
                continue; // nobody has touched it yet
            }

            $balance = $moves->first()->qty_before;
            foreach ($moves as $move) {
                $this->assertEqualsWithDelta($balance, $move->qty_before, 0.005, "{$ingredient->code}: a movement starts where the last one ended");
                $balance += $move->qty;
                $this->assertEqualsWithDelta($balance, $move->qty_after, 0.005, $ingredient->code);
                $this->assertGreaterThanOrEqual(0, $move->qty_after, "{$ingredient->code} never goes below zero");
            }
            $this->assertEqualsWithDelta($ingredient->stock_qty, $balance, 0.005, "{$ingredient->code}: the history ends at the stock");
        }
    }

    // ---- Numbers -------------------------------------------------------------------------------

    public function test_daily_numbers_count_up_per_prefix_and_restart_each_day(): void
    {
        $today = now();

        $this->assertSame('ZZ-'.$today->format('Ymd').'-0001', Sequence::next('ZZ'));
        $this->assertSame('ZZ-'.$today->format('Ymd').'-0002', Sequence::next('ZZ'));
        $this->assertSame('YY-'.$today->format('Ymd').'-0001', Sequence::next('YY'), 'each prefix has its own count');
        $this->assertSame('ZZ-'.$today->copy()->addDay()->format('Ymd').'-0001', Sequence::next('ZZ', $today->copy()->addDay()), 'a new day starts again');
    }

    public function test_no_two_orders_or_payments_share_a_number(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $order = $this->place('dine-in', 'GCASH');
            $this->workflow->payWithWallet($order);
        }

        $this->assertSame(Order::count(), Order::distinct()->count('order_no'));
        $this->assertSame(\App\Models\Payment::count(), \App\Models\Payment::distinct()->count('reference_no'));
        $this->assertSame(Order::count(), Order::distinct()->count('public_token'));
    }

    // ---- Menu edits never rewrite history --------------------------------------------------------

    public function test_a_past_order_keeps_the_names_and_prices_it_was_sold_at(): void
    {
        $order = $this->place();
        \App\Models\MenuItem::where('code', 'latte')->update(['name' => 'Renamed Latte', 'price' => 999]);
        \App\Models\OptionChoice::where('code', 'oat')->update(['name' => 'Oat?', 'extra_price' => 500]);

        $line = $order->fresh()->items->first();
        $this->assertSame(['Café Latte', 205.0], [$line->item_name, $line->unit_price]);
        $this->assertContains('Oat milk', $line->options->pluck('choice_name')->all());
        $this->assertSame(410.0, $order->fresh()->total);
    }
}
