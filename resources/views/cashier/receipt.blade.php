@extends('layouts.staff', ['module' => 'cashier', 'view' => 'receipt', 'active' => 'orders'])

@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Support\Format;

    $pay = $order['payment'];
    $d = $order['discount'];
@endphp

@section('title', 'Receipt · Brew & Bite Café')
@section('heading', 'Receipt')
@section('sub', $order['order_no'].' · '.Format::orderType($order['order_type'], $order['table_no']))
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <div class="rc-wrap">
        <article class="slip">
            <div class="c">
                <h2>{{ config('cafe.name') }}</h2>
                <div>{{ config('cafe.address') }}</div>
                <div>{{ config('cafe.phone') }}</div>
                <div>Demo receipt · not an official receipt</div>
            </div>
            @if ($order['status'] === 'voided')
                <div class="stamp">VOIDED · {{ $order['cancel_reason'] }}</div>
            @endif
            <hr>
            <div class="row"><span>Order no.</span><span>{{ $order['order_no'] }}</span></div>
            <div class="row"><span>Date</span><span>{{ Format::dateTime($pay['paid_at'] ?? $order['created_at']) }}</span></div>
            <div class="row"><span>Type</span><span>{{ Format::orderType($order['order_type'], $order['table_no']) }}</span></div>
            <div class="row"><span>Cashier</span><span>{{ $order['created_by'] ?? $pay['received_by'] ?? 'Self-service (QR)' }}</span></div>
            <hr>
            @foreach ($order['items'] as $line)
                @php($options = Format::optionText($line['options']))
                <div class="row"><span>{{ $line['qty'] }} × {{ $line['name'] }}</span><span>{{ peso($line['unit_price'] * $line['qty']) }}</span></div>
                @if ($options)<div class="opt">{{ $options }}</div>@endif
                @if ($line['notes'])<div class="opt">“{{ $line['notes'] }}”</div>@endif
            @endforeach
            <hr>
            <div class="row"><span>Subtotal</span><span>{{ peso($order['subtotal']) }}</span></div>
            @if ($d)
                <div class="row"><span>{{ $d['discount_type'] }} disc. 20% ({{ $d['eligible_count'] }}/{{ $d['diner_count'] }})</span><span>−{{ peso($d['amount']) }}</span></div>
                <div class="opt">ID {{ $d['id_no'] }} · {{ $d['holder_name'] }}</div>
            @endif
            @if ($order['delivery_fee'])
                <div class="row"><span>Delivery fee</span><span>{{ peso($order['delivery_fee']) }}</span></div>
            @endif
            <div class="row big"><span>TOTAL</span><span>{{ peso($order['total']) }}</span></div>
            <hr>
            @if ($pay)
                <div class="row"><span>Paid via</span><span>{{ PaymentMethod::from($pay['method'])->label() }}</span></div>
                <div class="row"><span>Reference</span><span>{{ $pay['reference_no'] }}</span></div>
                @if ($pay['method'] === 'CASH' && $pay['amount_tendered'] !== null)
                    <div class="row"><span>Cash</span><span>{{ peso($pay['amount_tendered']) }}</span></div>
                    <div class="row"><span>Change</span><span>{{ peso($pay['change_due']) }}</span></div>
                @endif
                <div class="row"><span>Status</span><span>{{ $pay['status'] }}</span></div>
            @else
                <div class="row"><span>Payment</span><span>UNPAID</span></div>
            @endif
            <hr>
            <div class="row"><span>VATable sales</span><span>{{ peso($vat['vatable']) }}</span></div>
            <div class="row"><span>VAT (12%)</span><span>{{ peso($vat['vat']) }}</span></div>
            <div class="row"><span>VAT-exempt sales</span><span>{{ peso($vat['exempt']) }}</span></div>
            <hr>
            <div class="c">Thank you, see you again!</div>
        </article>

        <div class="panel panel-pad side-actions no-print">
            <div class="done-box">
                @if ($pay && $pay['status'] === 'PAID')
                    <span class="chip chip-green" style="justify-self:start">Paid · sent to kitchen</span>
                @else
                    <span class="chip chip-red" style="justify-self:start">{{ OrderStatus::from($order['status'])->label() }}</span>
                @endif
                @if ($pay && $pay['method'] === 'CASH' && $pay['change_due'] !== null)
                    <small class="muted">Give change</small><span class="change-big">{{ peso($pay['change_due']) }}</span>
                @endif
            </div>
            <button class="btn btn-ghost" type="button" data-print>Print receipt</button>
            <a class="btn btn-primary" href="{{ route('cashier.pos.index') }}">New counter order</a>
            <a class="btn btn-ghost" href="{{ route('cashier.orders.index') }}">Back to orders</a>
        </div>
    </div>
@endsection
