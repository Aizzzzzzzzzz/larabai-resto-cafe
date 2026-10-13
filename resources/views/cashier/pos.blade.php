@extends('layouts.staff', ['module' => 'cashier', 'view' => 'pos', 'active' => 'pos'])

@section('title', 'Point of Sale · LaraBai Baristro')
@section('heading', 'Point of sale')
@section('sub', 'Walk-in and counter orders')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

{{--
    The ticket is built in the browser (resources/js/modules/cashier.js) and kept there so a refresh
    doesn't lose it. "Charge" posts the whole ticket as one form; the server re-prices every line.
--}}
@section('content')
    <form class="pos" method="POST" action="{{ route('cashier.pos.store') }}" data-pos @if ($resetTicket) data-reset @endif>
        @csrf
        <section class="pos-menu">
            <div class="pos-tools">
                <input class="input" type="search" placeholder="Search items" aria-label="Search items" data-pos-search>
                <div class="tabs">
                    <button type="button" data-cat="all" class="on">All</button>
                    @foreach ($categories as $category)
                        <button type="button" data-cat="{{ $category['code'] }}">{{ $category['name'] }}</button>
                    @endforeach
                </div>
            </div>
            <div class="grid">
                @foreach ($items as $item)
                    <button class="tile" type="button" data-item="{{ $item['code'] }}" data-cat="{{ $item['category'] }}" data-search="{{ Str::lower($item['name']) }}" @disabled($item['sold_out'])>
                        <span class="tile-sw"><x-art :item="$item" />@if ($item['sold_out'])<span class="chip chip-red">Sold out</span>@endif</span>
                        <span class="tile-body"><b>{{ $item['name'] }}</b><span>{{ peso($item['price'], false) }}</span></span>
                    </button>
                @endforeach
                <p class="muted" data-pos-none hidden>No items match.</p>
            </div>
        </section>

        <aside class="panel ticket">
            <div>
                <div class="ticket-sec">
                    <div class="seg" role="radiogroup" aria-label="Order type">
                        <label><input type="radio" name="order_type" value="take-out" @checked(old('order_type', 'take-out') === 'take-out')> Take-out</label>
                        <label><input type="radio" name="order_type" value="dine-in" @checked(old('order_type') === 'dine-in')> Dine-in</label>
                    </div>
                    <div class="two">
                        <label class="lbl" data-pos-table @unless (old('order_type') === 'dine-in') hidden @endunless>Table
                            <select class="input" name="table_no">
                                @foreach ($tables as $table)
                                    <option value="{{ $table['table_no'] }}" @selected((int) old('table_no') === $table['table_no'])>Table {{ $table['table_no'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
            </div>
            <div class="lines" data-pos-lines><p class="no-lines">Tap an item on the left to start an order.</p></div>
            <div data-pos-inputs hidden></div>
            <div class="ticket-foot">
                @if ($errors->any())
                    <p class="err-msg" role="alert">{{ $errors->first() }}</p>
                @endif
                <div class="totals"><div class="big"><span>Total</span><span data-pos-total>{{ peso(0) }}</span></div></div>
                <div class="foot-btns">
                    <button class="btn btn-ghost" type="button" data-pos-clear>Clear</button>
                    <button class="btn btn-primary" type="submit" data-pos-charge>Charge</button>
                </div>
                <small class="muted" style="font-size:.76rem">Senior citizen and PWD discounts are applied on the payment screen.</small>
            </div>
        </aside>
    </form>
@endsection

@push('modals')
    {{-- Each item's options, rendered by the server; the JS copies one into the options modal. --}}
    @foreach ($items as $item)
        @continue($item['sold_out'] || ! $item['options'])
        <template id="pos-item-{{ $item['code'] }}">
            @foreach ($item['options'] as $group)
                @php($single = $group['type'] === 'single')
                <fieldset class="og">
                    <legend>{{ $group['name'] }}<small>{{ $group['required'] ? 'Required' : 'Optional' }}</small></legend>
                    <div class="og-list">
                        @foreach ($group['choices'] as $choice)
                            <label class="og-opt"><input type="{{ $single ? 'radio' : 'checkbox' }}" name="{{ $group['code'] }}" value="{{ $choice['code'] }}" data-name="{{ $choice['name'] }}" data-price="{{ $choice['price'] }}" @checked($single && $choice['code'] === $group['default'])>{{ $choice['name'] }}@if ($choice['price']) <em>+{{ $choice['price'] }}</em>@endif</label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </template>
    @endforeach

    {{-- Items: code → name and base price, for the ticket preview (prices are re-checked on Charge) --}}
    <script type="application/json" id="pos-menu">@json(collect($items)->mapWithKeys(fn ($i) => [$i['code'] => ['name' => $i['name'], 'price' => $i['price']]]))</script>

    {{-- Options modal: adds a line to the browser-side ticket only (nothing is sent until Charge) --}}
    <div class="modal-back" id="posModal" hidden>
        <form class="modal" role="dialog" aria-modal="true" aria-labelledby="posModalTitle" data-pos-modal>
            <div class="modal-head"><h2 id="posModalTitle"></h2><button type="button" class="x-btn" data-modal-close aria-label="Close">×</button></div>
            <div class="modal-body">
                <div data-pos-options style="display:grid;gap:14px"></div>
                <label class="lbl">Notes for the kitchen<input class="input" name="notes" maxlength="120" placeholder="e.g. less ice"></label>
                <div class="qty-row"><span class="lbl" style="margin-right:6px">Quantity</span>
                    <button type="button" data-qty="-1" aria-label="Decrease">−</button><output data-pos-qty>1</output><button type="button" data-qty="1" aria-label="Increase">+</button></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" data-pos-add>Add</button>
            </div>
        </form>
    </div>
@endpush
