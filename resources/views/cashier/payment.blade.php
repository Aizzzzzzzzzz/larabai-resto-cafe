@extends('layouts.staff', ['module' => 'cashier', 'view' => 'payment', 'active' => 'orders'])

@section('title', 'Take Payment · Brew & Bite Café')
@section('heading', 'Take payment')
@section('sub', 'Order '.$order)
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="3" mockup="cashier.html?view=payment">
        <form method="POST" action="{{ route('cashier.payments.store', $order) }}">
            @csrf
            <input type="hidden" name="method" value="CASH">
            <input type="hidden" name="amount_tendered" value="200">
            <button class="btn btn-primary" type="submit">Record cash payment (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
