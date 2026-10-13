<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The menu (document.md §4.2–4.5, 4.7–4.9): categories, items, their option groups and choices, and what
     * each item and option uses from stock. The spec's text ids (latte, coffee) are kept as a unique `code`.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 50)->unique();
            $table->boolean('is_drink')->default(false); // drinks also use a cup and a lid
            $table->string('icon', 20)->default('cup');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->json('tone'); // the two colours of the item's artwork
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('sold_out_override')->nullable(); // null = follow stock, true = sold out, false = always available
            $table->boolean('is_active')->default(true); // false = off the menu, past orders keep their lines
            $table->timestamps();
        });

        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 50);
            $table->string('selection_type', 10); // single | multi
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'code']);
        });

        Schema::create('option_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 60);
            $table->decimal('extra_price', 8, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['option_group_id', 'code']);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100)->unique();
            $table->string('category', 50);
            $table->string('unit', 5); // g | ml | pc
            $table->decimal('stock_qty', 12, 2)->default(0); // never below zero (StockService)
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->decimal('restock_qty', 12, 2)->default(0);
            $table->decimal('unit_cost', 10, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('item_ingredients', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_per_serving', 12, 2); // the same at every size
            $table->unsignedSmallInteger('position')->default(0); // the order the recipe is listed in
            $table->primary(['menu_item_id', 'ingredient_id']);
        });

        Schema::create('choice_ingredients', function (Blueprint $table) {
            $table->foreignId('option_choice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2)->nullable(); // for "add"; a "replace" takes over the replaced ingredient's quantity
            $table->string('action', 10); // add | replace
            $table->foreignId('replaces_id')->nullable()->constrained('ingredients')->restrictOnDelete();
            $table->primary(['option_choice_id', 'ingredient_id']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('table_no')->unique();
            $table->unsignedSmallInteger('seats');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dining_tables', 'choice_ingredients', 'item_ingredients', 'ingredients', 'option_choices', 'option_groups', 'menu_items', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
