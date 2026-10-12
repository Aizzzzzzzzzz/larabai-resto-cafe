@extends('layouts.staff', ['module' => 'cashier', 'view' => 'pos', 'active' => 'pos'])

@section('title', 'Point of Sale · Brew & Bite Café')
@section('heading', 'Point of sale')
@section('sub', 'Walk-in and counter orders')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="3" mockup="cashier.html">
        <form method="POST" action="{{ route('cashier.pos.store') }}">
            @csrf
            <input type="hidden" name="order_type" value="take-out">
            <input type="hidden" name="items[0][menu_item]" value="latte">
            <input type="hidden" name="items[0][qty]" value="1">
            <button class="btn btn-primary" type="submit">Charge a Café Latte (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
