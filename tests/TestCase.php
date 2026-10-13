<?php

namespace Tests;

use App\Models\User;
use App\Services\Catalog;
use Database\Seeders\DiningTableSeeder;
use Database\Seeders\IngredientSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SampleOrdersSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every test starts from a fresh in-memory database holding the café's sample data: the 5 demo staff accounts,
 * the menu, the stock, the 5 tables and today's 13 sample orders (SampleOrdersSeeder). Time stands still at
 * 2:00 PM today, so "today" and "minutes ago" don't depend on when the tests run.
 *
 * Seeding goes through the seeders once per run; later tests copy the rows back in, which is much quicker.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Set to false in a test class that wants an empty database. */
    protected bool $seedSampleData = true;

    private const TABLE_ORDER = [
        'users', 'categories', 'dining_tables', 'ingredients', 'menu_items', 'option_groups', 'option_choices',
        'item_ingredients', 'choice_ingredients', 'orders', 'order_items', 'order_item_options', 'order_status_logs',
        'payments', 'discounts', 'stock_movements', 'audit_logs', 'daily_sequences',
    ];

    /** @var array{date: string, rows: array<string, array>}|null */
    private static ?array $snapshot = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages ask Vite for the built CSS/JS; tests don't need it, so they run on a fresh clone before `npm run build`
        $this->withoutVite();
        $this->travelTo(Carbon::today()->setTime(14, 0));
        Catalog::flush();

        if ($this->seedSampleData) {
            $this->loadSampleData();
            Catalog::flush();
        }
    }

    /** Signs in as one of the demo staff by username (admin, cashier, cashier2, kitchen, stock). */
    protected function signIn(string $username): static
    {
        return $this->actingAs(User::where('username', $username)->firstOrFail());
    }

    protected function asAdmin(): static
    {
        return $this->signIn('admin');
    }

    protected function asCashier(): static
    {
        return $this->signIn('cashier');
    }

    protected function asKitchen(): static
    {
        return $this->signIn('kitchen');
    }

    protected function asStockClerk(): static
    {
        return $this->signIn('stock');
    }

    private function loadSampleData(): void
    {
        $today = Carbon::today()->toDateString();

        if (self::$snapshot === null || self::$snapshot['date'] !== $today) {
            $this->seed([UserSeeder::class, DiningTableSeeder::class, IngredientSeeder::class, MenuSeeder::class, SampleOrdersSeeder::class]);

            self::$snapshot = ['date' => $today, 'rows' => collect(self::TABLE_ORDER)->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->map(fn ($r) => (array) $r)->all()])->all()];

            return;
        }

        // Parents before children, so the foreign keys hold while the rows go back in
        foreach (self::TABLE_ORDER as $table) {
            foreach (array_chunk(self::$snapshot['rows'][$table] ?? [], 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
