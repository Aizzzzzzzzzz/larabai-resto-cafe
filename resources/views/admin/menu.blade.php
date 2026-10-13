@extends('layouts.staff', ['module' => 'admin', 'view' => 'menu', 'active' => 'menu'])

@php
    use App\Support\Format;

    // The item editor re-opens after a failed save with what was typed, as an edit if it was one
    $reopenItem = old('_modal') === 'itemModal';
    $mode = $reopenItem ? old('mode', 'add') : 'add';
    $typed = fn (string $field, $default = '') => $reopenItem ? old($field, $default) : $default;
    $reopenCategories = old('_modal') === 'categoriesModal';
    $rowParams = array_filter(['cat' => $cat === 'all' ? null : $cat, 'q' => $q ?: null]);

    $addItem = [
        'mode' => 'add', 'has_size' => 1, 'name' => '', 'category' => $cat !== 'all' ? $cat : $categories[0]['code'], 'price' => '', 'description' => '',
        'large' => 20, 'template' => array_key_first($templates->all()), 'is_best_seller' => 0, 'is_new' => 1,
        'ingredients' => [['code' => $firstIngredient, 'qty' => '']],
    ];
@endphp

@section('title', 'Menu & Prices · Brew & Bite Café')
@section('heading', 'Menu & prices')
@section('sub', 'Each item lists the ingredients it uses; selling it deducts them from Stock')
@section('actions')
    <button class="btn btn-ghost btn-sm" type="button" data-modal-open="categoriesModal">Categories</button>
    <button class="btn btn-primary btn-sm" type="button" data-modal-open="itemModal" data-item-editor
            data-action="{{ route('admin.menu-items.store') }}" data-method="POST" data-title="Add menu item" data-fill="{{ json_encode($addItem) }}">+ Add item</button>
@endsection

@section('content')
    <section class="panel">
        {{-- Category tabs (keeping the search) and the search (keeping the category): two plain GET forms --}}
        <div class="panel-head">
            <form class="tabs" method="GET" action="{{ route('admin.menu-items.index') }}">
                @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                <button type="submit" name="cat" value="all" @class(['on' => $cat === 'all'])>All<span class="n">{{ $total }}</span></button>
                @foreach ($categories as $category)
                    <button type="submit" name="cat" value="{{ $category['code'] }}" @class(['on' => $cat === $category['code']])>{{ $category['name'] }}<span class="n">{{ $counts[$category['code']] ?? 0 }}</span></button>
                @endforeach
            </form>
            <form method="GET" action="{{ route('admin.menu-items.index') }}" role="search">
                @if ($cat !== 'all')<input type="hidden" name="cat" value="{{ $cat }}">@endif
                <input class="input" name="q" type="search" value="{{ $q }}" placeholder="Search items" style="max-width:220px" aria-label="Search items">
            </form>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Item</th><th>Category</th><th class="r">Price</th><th>Options</th><th>Ingredients per serving</th><th>Availability</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $item)
                        <tr>
                            <td>
                                <div class="item-cell">
                                    <span class="sw" style="background:linear-gradient(135deg, {{ $item['tone'][0] }}, {{ $item['tone'][1] }})"></span>
                                    <span><b>{{ $item['name'] }}</b>
                                        @if ($item['is_best_seller']) <span class="chip chip-gold">Best seller</span>@endif
                                        @if ($item['is_new']) <span class="chip chip-green">New</span>@endif
                                        <small>{{ $item['description'] }}</small></span>
                                </div>
                            </td>
                            <td>{{ $item['category_name'] }}</td>
                            <td class="r num price-cell"><b>{{ peso($item['price'], false) }}</b>@if ($item['large_adds'] !== null)<small>Large {{ peso($item['price'] + $item['large_adds'], false) }}</small>@endif</td>
                            <td><small style="color:var(--ink)">{!! $item['options_text'] ? e($item['options_text']) : '<span class="muted">None</span>' !!}</small></td>
                            <td class="ing-cell">
                                @if ($item['ingredients_text'])
                                    <small style="color:var(--ink)">{{ $item['ingredients_text'] }}</small>
                                @else
                                    <span class="chip chip-red">No ingredients</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.menu-items.availability', [$item['code']] + $rowParams) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select class="input avail-sel" name="availability" data-autosubmit aria-label="Availability of {{ $item['name'] }}">
                                        <option value="auto" @selected($item['availability'] === 'auto')>Auto (by stock)</option>
                                        <option value="on" @selected($item['availability'] === 'on')>Always available</option>
                                        <option value="off" @selected($item['availability'] === 'off')>Sold out</option>
                                    </select>
                                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Set</button></noscript>
                                </form>
                                <small @class(['margin-low' => $item['sold_now']])>{{ $item['sold_now'] ? 'Hidden from ordering' : 'On sale' }}</small>
                            </td>
                            <td class="r">
                                <button class="btn btn-ghost btn-sm" type="button" data-modal-open="itemModal" data-item-editor
                                        data-action="{{ route('admin.menu-items.update', $item['code']) }}" data-method="PUT" data-title="Edit {{ $item['name'] }}"
                                        data-fill="{{ json_encode([
                                            'mode' => 'edit', 'has_size' => $item['large_adds'] !== null ? 1 : 0, 'name' => $item['name'], 'category' => $item['category'],
                                            'price' => $item['price'], 'description' => $item['description'], 'large' => $item['large_adds'] ?? 20,
                                            'is_best_seller' => (int) $item['is_best_seller'], 'is_new' => (int) $item['is_new'], 'ingredients' => $item['recipe'],
                                        ]) }}">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row colspan="7">No items match.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('modals')
    {{-- Item editor: one modal for adding and editing. Its opener fills it and picks POST or PUT. --}}
    <x-modal id="itemModal" :title="$reopenItem ? ($mode === 'edit' ? 'Edit menu item' : 'Add menu item') : 'Add menu item'" :action="route('admin.menu-items.store')" spoof wide data-item-form>
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="has_size" value="{{ $typed('has_size', 1) }}">
        <div class="form-grid">
            <label class="lbl full">Name<input class="input" name="name" value="{{ $typed('name') }}"></label>
            <label class="lbl">Category
                <select class="input" name="category" data-category>
                    @foreach ($categories as $category)
                        <option value="{{ $category['code'] }}" data-drink="{{ (int) $category['is_drink'] }}" @selected($typed('category') === $category['code'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="lbl">Price (Medium / single) ₱<input class="input" name="price" type="number" min="0" step="1" value="{{ $typed('price') }}"></label>
            <label class="lbl full">Description<textarea class="input" name="description" rows="2">{{ $typed('description') }}</textarea></label>
            <label class="lbl" data-large>Large size adds ₱<input class="input" name="large" type="number" min="0" step="1" value="{{ $typed('large', 20) }}"></label>
            <label class="lbl" data-template>Options
                <select class="input" name="template">
                    @foreach ($templates as $key => $template)
                        <option value="{{ $key }}" data-has-size="{{ (int) $template['has_size'] }}" @selected($typed('template', array_key_first($templates->all())) === $key)>{{ $template['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="full">
                <div class="lbl" style="margin-bottom:6px">Labels on the menu</div>
                <div class="checks">
                    <label><input type="hidden" name="is_best_seller" value="0"><input type="checkbox" name="is_best_seller" value="1" @checked((bool) $typed('is_best_seller', 0))> Best seller</label>
                    <label><input type="hidden" name="is_new" value="0"><input type="checkbox" name="is_new" value="1" @checked((bool) $typed('is_new', 1))> New</label>
                </div>
            </div>
        </div>
        <div class="ing-block">
            <div class="ing-head"><span class="lbl">Ingredients per serving</span><span class="hint">Deducted from Stock each time it sells</span></div>
            <div class="ing-rows" data-ing-rows>
                @foreach ($reopenItem ? old('ingredients', []) : [] as $i => $row)
                    @include('admin.partials.ingredient-row', ['i' => $i, 'code' => $row['code'] ?? '', 'qty' => $row['qty'] ?? ''])
                @endforeach
            </div>
            <div class="ing-foot"><button type="button" class="btn btn-ghost btn-sm" data-ing="add">+ Add ingredient</button><span class="hint" data-cup-note></span></div>
        </div>
        @if ($reopenItem && $errors->any())
            @foreach ($errors->all() as $message)<p class="err-msg" role="alert">{{ $message }}</p>@endforeach
        @endif
        <x-slot:footer>
            {{-- Removing is its own DELETE form (below); this button submits it and needs a second tap --}}
            <button type="submit" form="itemDeleteForm" class="btn btn-danger" style="margin-right:auto" data-item-delete data-confirm-twice="Click again to remove">Remove from menu</button>
            <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary" data-modal-submit>Save</button>
        </x-slot:footer>
    </x-modal>
    <form id="itemDeleteForm" method="POST" action="{{ route('admin.menu-items.index') }}" hidden>
        @csrf
        @method('DELETE')
    </form>

    <template id="ingRowTemplate">@include('admin.partials.ingredient-row', ['i' => '__i__', 'code' => '', 'qty' => ''])</template>

    {{-- Categories: rename and delete each have their own small form, so this isn't one big form --}}
    <div class="modal-back" id="categoriesModal" hidden @if ($reopenCategories) data-modal-autoopen @endif>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="categoriesModal-title">
            <div class="modal-head"><h2 id="categoriesModal-title">Categories</h2><button type="button" class="x-btn" data-modal-close aria-label="Close">×</button></div>
            <div class="modal-body">
                @if ($reopenCategories && $errors->any())
                    @foreach ($errors->all() as $message)<p class="err-msg" role="alert">{{ $message }}</p>@endforeach
                @endif
                <div>
                    @foreach ($categories as $category)
                        @php($n = $counts[$category['code']] ?? 0)
                        <div class="cat-row">
                            <form method="POST" action="{{ route('admin.categories.update', $category['code']) }}" style="display:flex;gap:6px">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_modal" value="categoriesModal">
                                <input class="input" name="name" value="{{ $category['name'] }}" aria-label="Category name">
                                <button class="btn btn-ghost btn-sm" type="submit">Save</button>
                            </form>
                            <span class="hint">{{ $n }} {{ Str::plural('item', $n) }}</span>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category['code']) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_modal" value="categoriesModal">
                                <button class="btn btn-ghost btn-sm" type="submit" data-confirm-twice="Tap again to delete" title="{{ $n ? 'Move or remove its items first' : 'Delete this category' }}">Delete</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.categories.store') }}" style="display:grid;grid-template-columns:1fr auto;gap:8px">
                    @csrf
                    <input type="hidden" name="_modal" value="categoriesModal">
                    <input class="input" name="name" placeholder="New category name" aria-label="New category name">
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
            </div>
        </div>
    </div>
@endpush
