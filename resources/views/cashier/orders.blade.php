@extends('layouts.staff', ['module' => 'cashier', 'view' => 'orders', 'active' => 'orders'])

@section('title', 'Orders · Brew & Bite Café')
@section('heading', 'Orders')
@section('sub', 'QR and counter orders · updates automatically')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="3" mockup="cashier.html?view=orders">
        <a href="{{ route('cashier.payments.create', 'demo') }}">Take payment →</a>
        <a href="{{ route('cashier.receipt', 'demo') }}">Receipt →</a>
        <form method="POST" action="{{ route('cashier.orders.complete', 'demo') }}">
            @csrf
            @method('PATCH')
            <button class="btn btn-ghost btn-sm" type="submit">Mark served (demo)</button>
        </form>
        <form method="POST" action="{{ route('cashier.orders.void', 'demo') }}">
            @csrf
            <input type="hidden" name="reason" value="Wrong item">
            <button class="btn btn-danger btn-sm" type="submit">Void (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
