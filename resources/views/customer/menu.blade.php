@extends('layouts.customer', ['view' => 'menu'])

@section('title', 'Menu · Brew & Bite Café')

@section('content')
    <div class="app">
        <header class="c-head">
            <div class="c-head-row">
                <span class="brand"><span class="brand-mark">B&amp;B</span><span class="brand-name">Brew &amp; Bite Café</span></span>
                <span class="head-right">
                    <span class="session-pill">{{ $sessionLabel }}</span>
                    <x-theme-toggle />
                </span>
            </div>
            <div class="c-head-row">
                <h1>What are you craving today?</h1>
                @if ($myOrders)
                    @php($active = collect($myOrders)->whereNotIn('status', ['completed', 'cancelled'])->count())
                    <a class="my-orders-link" href="{{ route('customer.orders.index') }}">{{ $active ? "My orders ({$active} active)" : 'My orders' }}</a>
                @endif
            </div>
            <div class="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="search" type="search" placeholder="Search drinks, meals, pastries" autocomplete="off" aria-label="Search the menu" data-menu-search>
            </div>
        </header>

        <nav class="cat-bar" aria-label="Menu categories">
            @foreach ($categories as $category)
                <button type="button" data-cat="{{ $category['code'] }}" @class(['on' => $loop->first])>{{ $category['name'] }}</button>
            @endforeach
        </nav>

        @if ($errors->any())
            <p class="msg bad" role="alert" style="margin:12px 16px 0">{{ $errors->first() }}</p>
        @endif

        <main id="menu">
            @foreach ($categories as $category)
                <section class="menu-section" id="sec-{{ $category['code'] }}" data-cat="{{ $category['code'] }}">
                    <h2>{{ $category['name'] }}</h2>
                    <div class="menu-list">
                        @foreach ($items[$category['code']] ?? [] as $item)
                            <button class="m-item" type="button" data-item="{{ $item['code'] }}" data-search="{{ Str::lower($item['name'].' '.$item['description']) }}" @disabled($item['sold_out'])>
                                <span class="thumb"><x-art :item="$item" /></span>
                                <span class="m-body">
                                    <span class="m-name">{{ $item['name'] }}</span>
                                    @if ($item['is_best_seller'] || $item['is_new'] || $item['sold_out'])
                                        <span class="tags">
                                            @if ($item['is_best_seller'])<span class="chip chip-gold">Best seller</span>@endif
                                            @if ($item['is_new'])<span class="chip chip-green">New</span>@endif
                                            @if ($item['sold_out'])<span class="chip chip-red">Sold out</span>@endif
                                        </span>
                                    @endif
                                    <span class="m-desc">{{ $item['description'] }}</span>
                                    <span class="m-foot">
                                        @php($hasRange = collect($item['options'])->contains(fn ($g) => collect($g['choices'])->contains(fn ($c) => $c['price'] > 0)))
                                        <span class="m-price">{{ $hasRange ? 'from ' : '' }}{{ peso($item['price'], false) }}</span>
                                        @unless ($item['sold_out'])<span class="m-add" aria-hidden="true">+</span>@endunless
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </main>
        <p class="empty-search" id="noResults" hidden>No items match your search.</p>
    </div>

    @if ($cartCount)
        <div class="cart-bar">
            <a href="{{ route('customer.cart.index') }}">
                <span><span class="cart-count">{{ $cartCount }}</span>View your order</span>
                <span class="num">{{ peso($cartSubtotal, false) }}</span>
            </a>
        </div>
    @endif

    {{-- Each item's sheet content, rendered by the server; resources/js/modules/customer-menu.js copies one into the sheet. --}}
    @foreach ($items->flatten(1) as $item)
        @continue($item['sold_out'])
        <template id="item-{{ $item['code'] }}" data-name="{{ $item['name'] }}" data-description="{{ $item['description'] }}" data-price="{{ $item['price'] }}">
            <x-art :item="$item" />
            @foreach ($item['options'] as $group)
                @php($single = $group['type'] === 'single')
                <fieldset class="opt-group">
                    <legend>{{ $group['name'] }}<small>{{ $group['required'] ? 'Required' : 'Optional' }}</small></legend>
                    <div @class(['opt-list', 'pills' => $single && count($group['choices']) > 3])>
                        @foreach ($group['choices'] as $choice)
                            <label class="opt">
                                <input type="{{ $single ? 'radio' : 'checkbox' }}" name="options[{{ $group['code'] }}]{{ $single ? '' : '[]' }}" value="{{ $choice['code'] }}" data-price="{{ $choice['price'] }}" @checked($single && $choice['code'] === $group['default'])>
                                <span>{{ $choice['name'] }}</span>
                                @if ($choice['price'])<em>+{{ peso($choice['price'], false) }}</em>@endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </template>
    @endforeach

    {{-- Item sheet: one real form; JS fills in the chosen item. --}}
    <div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
    <form class="sheet" id="sheet" method="POST" action="{{ route('customer.cart.store') }}" hidden aria-modal="true" role="dialog" aria-labelledby="sheetTitle">
        @csrf
        <input type="hidden" name="menu_item" id="sheetItem">
        <input type="hidden" name="qty" id="sheetQty" value="1">
        <div class="sheet-scroll">
            <div class="sheet-hero" id="sheetHero">
                <button class="sheet-close" type="button" id="sheetClose" aria-label="Close">×</button>
            </div>
            <div class="sheet-info">
                <h2 id="sheetTitle"></h2>
                <p id="sheetDesc"></p>
                <span class="m-price" id="sheetBase"></span>
            </div>
            <div id="sheetOptions"></div>
            <div class="notes">
                <label for="sheetNotes">Notes for the kitchen <span class="muted" style="font-weight:400">(optional)</span></label>
                <textarea id="sheetNotes" name="notes" maxlength="120" placeholder="e.g. less ice, no onions"></textarea>
            </div>
        </div>
        <div class="sheet-foot">
            <div class="stepper">
                <button type="button" data-qty="-1" aria-label="Decrease quantity">−</button>
                <output id="qty">1</output>
                <button type="button" data-qty="1" aria-label="Increase quantity">+</button>
            </div>
            <button class="btn btn-primary" type="submit" id="sheetAdd">Add to order</button>
        </div>
    </form>
@endsection
