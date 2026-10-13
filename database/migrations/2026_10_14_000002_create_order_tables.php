<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders and everything that hangs off them (document.md §4.10–4.17). Order lines copy the item and
     * choice names and prices at order time, so later menu changes never rewrite a past order or receipt.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique(); // BB-20261014-0001
            $table->string('public_token', 40)->unique(); // the only id a customer ever sees in a link
            $table->string('source', 5); // qr | pos
            $table->string('order_type', 10); // dine-in | take-out | delivery
            $table->foreignId('dining_table_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_landmark', 150)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 5)->nullable(); // GCASH | MAYA | CASH — what the customer chose
            $table->string('status', 20)->index();
            $table->string('cancel_reason')->nullable(); // cancel or void reason
            $table->boolean('stock_deducted')->default(false); // guards against deducting twice
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('paid_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 100);
            $table->decimal('unit_price', 8, 2); // item price + the chosen options' extras
            $table->unsignedSmallInteger('qty');
            $table->string('notes', 120)->nullable();
        });

        Schema::create('order_item_options', function (Blueprint $table) {
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_choice_id')->constrained()->restrictOnDelete();
            $table->string('group_code', 30);
            $table->string('choice_code', 30);
            $table->string('choice_name', 60);
            $table->decimal('extra_price', 8, 2)->default(0);
            $table->primary(['order_item_id', 'option_choice_id']);
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 5);
            $table->decimal('amount', 10, 2);
            $table->string('reference_no', 24)->unique(); // GC-20261014-0001
            $table->string('status', 6); // PAID | VOIDED
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->decimal('change_due', 10, 2)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index('paid_at');
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('discount_type', 6); // Senior | PWD
            $table->text('id_no'); // encrypted: personal data
            $table->text('holder_name'); // encrypted: personal data
            $table->unsignedSmallInteger('eligible_count');
            $table->unsignedSmallInteger('diner_count');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->string('type', 8); // SALE | RETURN | PURCHASE | ADJUST
            $table->decimal('qty', 12, 2); // signed: negative takes stock out
            $table->decimal('qty_before', 12, 2);
            $table->decimal('qty_after', 12, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('moved_at');

            $table->index(['ingredient_id', 'moved_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 100); // kept as text so the log still reads if the account changes
            $table->string('role', 20); // admin | cashier | kitchen | inventory | customer
            $table->string('module', 20); // the area the owner filters by (Sales, Kitchen, …)
            $table->string('action', 60);
            $table->text('detail')->nullable();
            $table->timestamp('logged_at');

            $table->index('logged_at');
            $table->index('module');
        });

        // The running number behind BB-… order numbers and GC-/MY-/CS-… payment references, one per prefix per day
        Schema::create('daily_sequences', function (Blueprint $table) {
            $table->string('prefix', 4);
            $table->date('seq_date');
            $table->unsignedInteger('last_no')->default(0);
            $table->primary(['prefix', 'seq_date']);
        });
    }

    public function down(): void
    {
        foreach (['daily_sequences', 'audit_logs', 'stock_movements', 'discounts', 'payments', 'order_status_logs', 'order_item_options', 'order_items', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
