@extends('layouts.staff', ['module' => 'inventory', 'view' => 'ingredients', 'active' => 'ingredients'])

@php
    use App\Support\StockLevel;

    // Values for the Add / Edit modals: show what was typed again only when that modal's form failed validation
    $typed = fn (string $modal, string $field, $default = '') => old('_modal') === $modal ? old($field, $default) : $default;
@endphp

@section('title', 'Stock · Brew & Bite Café')
@section('heading', 'Stock')
@section('sub', 'Ingredients are deducted automatically as orders are paid')
@section('actions')
    <span class="clock" data-clock></span>
    <button class="btn btn-primary btn-sm" type="button" data-modal-open="ingredientAdd">+ Add ingredient</button>
@endsection

@section('content')
    <div class="kpis">
        <x-stat-card label="Ingredients tracked" :value="$kpis['tracked']" />
        <x-stat-card label="Low stock" :value="$kpis['low']" :tone="$kpis['low'] ? 'warn' : null" />
        <x-stat-card label="Out of stock" :value="$kpis['out']" :tone="$kpis['out'] ? 'alert' : null" />
        <x-stat-card label="Stock value" :value="peso($kpis['value'])" />
    </div>

    @if ($blocked->isNotEmpty())
        <section class="impact" aria-live="polite">
            <h2>{{ $blocked->count() }} menu {{ Str::plural('item', $blocked->count()) }} unavailable to customers</h2>
            <div class="chips">
                @foreach ($blocked as $b)
                    <span class="chip">{{ $b['name'] }} · needs {{ implode(', ', $b['needs']) }}</span>
                @endforeach
            </div>
            <span class="hint">These show as Sold out on the customer menu and the POS until you restock.</span>
        </section>
    @endif

    <section class="panel">
        {{-- The list is filtered on the server by two plain GET forms: tabs (keeping category + search) and category + search (keeping the tab) --}}
        <div class="panel-head">
            <form class="tabs" method="GET" action="{{ route('inventory.ingredients.index') }}">
                @if ($category !== '')<input type="hidden" name="cat" value="{{ $category }}">@endif
                @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                @foreach (\App\Http\Controllers\Inventory\IngredientController::TABS as $id => $label)
                    <button type="submit" name="tab" value="{{ $id }}" @class(['on' => $id === $tab])>{{ $label }}<span class="n">{{ $counts[$id] }}</span></button>
                @endforeach
            </form>
            <form class="toolbar" method="GET" action="{{ route('inventory.ingredients.index') }}" role="search">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select class="input" name="cat" aria-label="Category" data-autosubmit>
                    <option value="">All categories</option>
                    @foreach ($categories as $name)
                        <option @selected($name === $category)>{{ $name }}</option>
                    @endforeach
                </select>
                <input class="input" name="q" type="search" value="{{ $q }}" placeholder="Search ingredients" aria-label="Search ingredients">
                <noscript><button class="btn btn-ghost btn-sm" type="submit">Filter</button></noscript>
            </form>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Ingredient</th><th>In stock</th><th class="r">Reorder at</th><th class="r">Stock value</th><th>Status</th><th class="r"></th></tr></thead>
                <tbody>
                    @forelse ($rows as $i)
                        @php
                            $pct = max(0, min(100, $i['stock_qty'] / (($i['reorder_level'] * 2) ?: 1) * 100));
                            $usedText = in_array($i['code'], ['cup12', 'cup16', 'lid'], true)
                                ? 'used in every drink'
                                : ($i['used_in'] ? 'used in '.$i['used_in'].' '.Str::plural('item', $i['used_in']) : 'used in add-ons');
                        @endphp
                        <tr @class(['need-'.$i['level'] => $i['level'] !== 'ok'])>
                            <td class="name-cell"><b>{{ $i['name'] }}</b><small>{{ $i['category'] }} · {{ $usedText }}</small></td>
                            <td><div class="lvl"><span class="num">{{ StockLevel::format($i['stock_qty'], $i['unit']) }}</span><span class="lvl-bar"><i class="{{ $i['level'] }}" style="width:{{ $pct }}%"></i></span></div></td>
                            <td class="r num">{{ StockLevel::format($i['reorder_level'], $i['unit']) }}</td>
                            <td class="r num">{{ peso(max(0, $i['stock_qty']) * $i['unit_cost']) }}<small class="mono">{{ StockLevel::costText($i['unit_cost'], $i['unit']) }}</small></td>
                            <td><span class="chip {{ StockLevel::chip($i['level']) }}">{{ StockLevel::label($i['level']) }}</span></td>
                            <td class="r" style="white-space:nowrap">
                                <button type="button" @class(['btn', 'btn-sm', 'btn-ghost' => $i['level'] === 'ok', 'btn-primary' => $i['level'] !== 'ok']) data-modal-open="restockModal" data-restock
                                        data-action="{{ route('inventory.ingredients.restock', $i['code']) }}"
                                        data-title="Restock {{ $i['name'] }}"
                                        data-fill="{{ json_encode(['mode' => 'add', 'qty' => $i['restock_qty'], 'note' => '', 'ctx_name' => $i['name'], 'ctx_stock' => $i['stock_qty'], 'ctx_reorder' => $i['reorder_level'], 'ctx_unit' => $i['unit'], 'ctx_restock' => $i['restock_qty']]) }}">Restock</button>
                                <a class="btn btn-ghost btn-sm" href="{{ route('inventory.ingredients.history', $i['code']) }}">History</a>
                                <button type="button" class="btn btn-ghost btn-sm" data-modal-open="ingredientEdit"
                                        data-action="{{ route('inventory.ingredients.update', $i['code']) }}"
                                        data-title="Edit {{ $i['name'] }}"
                                        data-fill="{{ json_encode(['name' => $i['name'], 'category' => $i['category'], 'reorder_level' => $i['reorder_level'], 'restock_qty' => $i['restock_qty'], 'unit_cost' => $i['unit_cost']]) }}">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row colspan="6">No ingredients match.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('modals')
    <datalist id="categoryList">
        @foreach ($categories as $name)<option value="{{ $name }}">@endforeach
    </datalist>

    {{-- Restock: one modal for every row. The row button fills it (core/modal.js); the ctx_* fields keep what
         the preview needs, so it still works after a failed submit. --}}
    <x-modal id="restockModal" :title="old('_modal') === 'restockModal' && old('ctx_name') ? 'Restock '.old('ctx_name') : 'Restock'" :action="route('inventory.ingredients.index')" shared data-restock-form>
        <input type="hidden" name="ctx_name" value="{{ old('ctx_name') }}">
        <input type="hidden" name="ctx_stock" value="{{ old('ctx_stock') }}">
        <input type="hidden" name="ctx_reorder" value="{{ old('ctx_reorder') }}">
        <input type="hidden" name="ctx_unit" value="{{ old('ctx_unit') }}">
        <input type="hidden" name="ctx_restock" value="{{ old('ctx_restock') }}">
        <p class="hint" data-restock-onhand></p>
        <div class="seg" role="radiogroup" aria-label="What happened">
            <label><input type="radio" name="mode" value="add" @checked($typed('restockModal', 'mode', 'add') === 'add')> Add a delivery</label>
            <label><input type="radio" name="mode" value="count" @checked($typed('restockModal', 'mode', 'add') === 'count')> Set counted amount</label>
        </div>
        <label class="lbl"><span data-restock-label>Quantity received</span>
            <span class="unit-input"><input class="input" name="qty" type="number" min="0" step="any" value="{{ $typed('restockModal', 'qty') }}"><span data-restock-unit></span></span>
        </label>
        <label class="lbl">Note (optional)<input class="input" name="note" value="{{ $typed('restockModal', 'note') }}" placeholder="e.g. delivered by Central Luzon Dairy"></label>
        <p class="preview" data-restock-preview></p>
        @if (old('_modal') === 'restockModal')<x-field-error name="qty" />@endif
    </x-modal>

    {{-- Add ingredient --}}
    <x-modal id="ingredientAdd" title="Add ingredient" :action="route('inventory.ingredients.store')">
        <div class="form-grid">
            <label class="lbl full">Name<input class="input" name="name" value="{{ $typed('ingredientAdd', 'name') }}" required></label>
            <label class="lbl">Category<input class="input" name="category" list="categoryList" value="{{ $typed('ingredientAdd', 'category') }}" required></label>
            <label class="lbl">Unit
                <select class="input" name="unit">
                    @foreach ($units as $unit)
                        <option value="{{ $unit->value }}" @selected($typed('ingredientAdd', 'unit', 'g') === $unit->value)>{{ $unit->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="lbl">Reorder level<input class="input" name="reorder_level" type="number" min="0" step="any" value="{{ $typed('ingredientAdd', 'reorder_level') }}"></label>
            <label class="lbl">Usual restock quantity<input class="input" name="restock_qty" type="number" min="0" step="any" value="{{ $typed('ingredientAdd', 'restock_qty') }}"></label>
            <label class="lbl full">Cost per unit (₱)<input class="input" name="unit_cost" type="number" min="0" step="0.001" value="{{ $typed('ingredientAdd', 'unit_cost') }}"></label>
            <label class="lbl full">Opening stock<input class="input" name="opening_stock" type="number" min="0" step="any" value="{{ $typed('ingredientAdd', 'opening_stock', 0) }}"><span class="hint">Use Restock to add stock later.</span></label>
        </div>
        @if ($errors->any() && old('_modal') === 'ingredientAdd')
            @foreach ($errors->all() as $message)<p class="err-msg" role="alert">{{ $message }}</p>@endforeach
        @endif
    </x-modal>

    {{-- Edit ingredient: the unit is fixed (changing it would break recipes) and stock changes go through Restock --}}
    <x-modal id="ingredientEdit" title="Edit ingredient" :action="route('inventory.ingredients.index')" method="PUT" shared>
        <div class="form-grid">
            <label class="lbl full">Name<input class="input" name="name" value="{{ $typed('ingredientEdit', 'name') }}" required></label>
            <label class="lbl">Category<input class="input" name="category" list="categoryList" value="{{ $typed('ingredientEdit', 'category') }}" required></label>
            <div class="lbl">Unit<span class="hint" style="padding:9px 0">Can’t be changed once in use</span></div>
            <label class="lbl">Reorder level<input class="input" name="reorder_level" type="number" min="0" step="any" value="{{ $typed('ingredientEdit', 'reorder_level') }}"></label>
            <label class="lbl">Usual restock quantity<input class="input" name="restock_qty" type="number" min="0" step="any" value="{{ $typed('ingredientEdit', 'restock_qty') }}"></label>
            <label class="lbl full">Cost per unit (₱)<input class="input" name="unit_cost" type="number" min="0" step="0.001" value="{{ $typed('ingredientEdit', 'unit_cost') }}"></label>
        </div>
        @if ($errors->any() && old('_modal') === 'ingredientEdit')
            @foreach ($errors->all() as $message)<p class="err-msg" role="alert">{{ $message }}</p>@endforeach
        @endif
    </x-modal>
@endpush
