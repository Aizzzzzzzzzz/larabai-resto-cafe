@extends('layouts.staff', ['module' => 'gallery', 'view' => 'staff', 'active' => '', 'badges' => ['awaiting' => 3, 'lowstock' => 6]])

@section('title', 'Components · Brew & Bite Café')
@section('heading', 'Shared components')
@section('sub', 'Staff side · sample data · demo mode only')
@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('navigator.components.customer') }}">Customer side →</a>
@endsection

@section('content')
    <div class="kpis">
        <x-stat-card label="Awaiting payment" value="3" tone="alert" />
        <x-stat-card label="Sales today" :value="peso(4120.14)">
            <x-delta :now="4120.14" :before="3680" />
        </x-stat-card>
        <x-stat-card label="Low stock" value="6" tone="warn" />
        <x-stat-card label="Average order" :value="peso(317)">
            <x-delta :now="317" :before="342" />
        </x-stat-card>
    </div>

    <section class="panel">
        <div class="panel-head"><h2>Status pills · order rows</h2></div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Order</th><th>Type</th><th>Items</th><th class="r">Total</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="mono">{{ $order['order_no'] }}</td>
                            <td>{{ \App\Support\Format::orderType($order['order_type'], $order['table_no']) }}</td>
                            <td>{{ collect($order['items'])->map(fn ($l) => $l['qty'].'× '.$l['name'])->join(', ') }}</td>
                            <td class="r num">{{ peso($order['total']) }}</td>
                            <td><x-status-pill :status="$order['status']" /></td>
                        </tr>
                    @endforeach
                    <x-empty-row colspan="5">Empty table row — e.g. "No orders waiting for payment."</x-empty-row>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel panel-pad" style="display:grid;gap:12px;justify-items:start">
        <h2>Modal with a real form</h2>
        <p class="hint">Posts to <code>admin.categories.store</code> with <code>@@csrf</code>; lands on Menu &amp; prices with the "nothing is saved" toast.</p>
        <button class="btn btn-primary btn-sm" type="button" data-modal-open="demoModal">Open modal</button>
        <button class="btn btn-ghost btn-sm" type="button" data-confirm-twice="Click again to confirm">Tap-twice button</button>
    </section>

    <section class="panel panel-pad" style="display:grid;gap:12px">
        <h2>Type-to-confirm</h2>
        <x-confirm-form :action="route('admin.demo-reset.store')" word="RESET" button="Reset demo data">
            <p class="hint">Clears every saved order, payment and stock change and restores the sample data.</p>
        </x-confirm-form>
    </section>
@endsection

@push('modals')
    <x-modal id="demoModal" title="Add category" :action="route('admin.categories.store')">
        <label class="lbl">Name<input class="input" name="name" value="{{ old('name') }}"></label>
        <x-field-error name="name" />
        <label class="lbl"><span><input type="checkbox" name="is_drink" value="1"> Drinks (needs a cup and lid)</span></label>
    </x-modal>
@endpush
