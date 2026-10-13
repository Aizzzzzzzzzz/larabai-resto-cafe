@extends('layouts.staff', ['module' => 'cashier', 'view' => 'orders', 'active' => 'orders'])

@php
    use App\Enums\PaymentMethod;
    use App\Support\CashierActions as Act;
    use App\Support\Format;
@endphp

@section('title', 'Orders · Brew & Bite Café')
@section('heading', 'Orders')
@section('sub', 'QR and counter orders · updates automatically')
@section('actions')
    <span class="clock" data-clock></span>
    <a class="btn btn-primary btn-sm" href="{{ route('cashier.pos.index') }}">+ New counter order</a>
@endsection

@section('content')
    <div class="kpis">
        <x-stat-card label="Awaiting payment" :value="$kpis['awaiting']" :tone="$kpis['awaiting'] ? 'alert' : null" />
        <x-stat-card label="Orders today" :value="$kpis['today']" />
        <x-stat-card label="Sales today" :value="peso($kpis['sales'])" />
        <x-stat-card label="Cash in drawer (today)" :value="peso($kpis['cash'])" />
    </div>

    <section class="panel">
        <div class="panel-head">
            {{-- Tabs and search are plain GET forms: the list is filtered on the server --}}
            <form class="tabs" method="GET" action="{{ route('cashier.orders.index') }}">
                @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                @foreach (\App\Http\Controllers\Cashier\OrderController::TABS as $id => $label)
                    <button type="submit" name="tab" value="{{ $id }}" @class(['on' => $id === $tab])>{{ $label }}<span class="n">{{ $counts[$id] }}</span></button>
                @endforeach
            </form>
            <form method="GET" action="{{ route('cashier.orders.index') }}" role="search">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input class="input" name="q" type="search" value="{{ $q }}" placeholder="Search order no." style="max-width:240px" aria-label="Search orders">
            </form>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Order</th><th>Type</th><th>Items</th><th class="r">Total</th><th>Payment</th><th>Status</th><th class="r">Actions</th></tr></thead>
                <tbody>
                    @forelse ($rows as $o)
                        @php($method = PaymentMethod::from($o['payment_method'])->label())
                        <tr @class(['waiting' => Act::unpaid($o)])>
                            <td><b class="mono">{{ $o['order_no'] }}</b><small>{{ Format::ago($o['created_at']) }}</small></td>
                            <td>{{ Format::orderType($o['order_type'], $o['table_no']) }}<small>{{ $o['source'] === 'pos' ? 'Counter' : 'QR order' }}</small></td>
                            <td class="items-cell">
                                {{ collect($o['items'])->map(fn ($l) => $l['qty'].'× '.$l['name'])->join(', ') }}
                                @if ($o['discount'])<small>{{ $o['discount']['discount_type'] }} discount</small>@endif
                                @if ($o['cancel_reason'])<small>{{ $o['cancel_reason'] }}</small>@endif
                            </td>
                            <td class="r mono">{{ peso($o['total']) }}</td>
                            <td>
                                @if ($o['paid_at'])
                                    {{ $method }}<small class="mono">{{ $o['payment']['reference_no'] }}</small>
                                @else
                                    <span class="muted">{{ $method }} · unpaid</span>
                                @endif
                            </td>
                            <td><x-status-pill :status="$o['status']" /></td>
                            <td>
                                <div class="acts">
                                    @if (Act::canTakePayment($o))
                                        <a class="btn btn-primary btn-sm" href="{{ route('cashier.payments.create', $o['id']) }}">{{ Act::isCodCollection($o) ? 'Collect COD payment' : 'Take payment' }}</a>
                                    @endif
                                    @if (Act::canComplete($o))
                                        <form method="POST" action="{{ route('cashier.orders.complete', $o['id']) }}" style="display:contents">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-primary btn-sm" type="submit">{{ Act::completeLabel($o) }}</button>
                                        </form>
                                    @endif
                                    @if (Act::hasReceipt($o))
                                        <a class="btn btn-ghost btn-sm" href="{{ route('cashier.receipt', $o['id']) }}">Receipt</a>
                                    @endif
                                    @if (Act::canCancel($o))
                                        <button class="btn btn-danger btn-sm" type="button" data-modal-open="reasonModal"
                                                data-action="{{ route('cashier.orders.cancel', $o['id']) }}"
                                                data-title="Cancel {{ $o['order_no'] }}?" data-submit="Cancel order"
                                                data-text="The order is removed from the queue. Nothing has been paid yet.">Cancel</button>
                                    @endif
                                    @if (Act::canVoid($o))
                                        <button class="btn btn-danger btn-sm" type="button" data-modal-open="reasonModal"
                                                data-action="{{ route('cashier.orders.void', $o['id']) }}"
                                                data-title="Void {{ $o['order_no'] }}?" data-submit="Void order"
                                                data-text="This order was already paid ({{ peso($o['total']) }} by {{ $method }}). Voiding marks the payment as voided and removes it from sales. Return the money to the customer.">Void</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row colspan="7">{{ $tab === 'awaiting' ? 'No orders waiting for payment.' : 'No orders here yet.' }}</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('modals')
    {{-- One modal for every row's Cancel / Void; the clicked button fills in the order (core/modal.js) --}}
    <x-modal id="reasonModal" title="Cancel order?" action="{{ route('cashier.orders.index') }}" shared>
        <p class="muted" style="font-size:.9rem" data-modal-text></p>
        <label class="lbl">Reason
            <select class="input" name="reason">
                @foreach ($reasons as $reason)
                    <option @selected(old('reason') === $reason)>{{ $reason }}</option>
                @endforeach
            </select>
        </label>
        <x-field-error name="reason" />
        <x-slot:footer>
            <button type="button" class="btn btn-ghost" data-modal-close>Keep order</button>
            <button type="submit" class="btn btn-danger" data-modal-submit>Confirm</button>
        </x-slot:footer>
    </x-modal>
@endpush
