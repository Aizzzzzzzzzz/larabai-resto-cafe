@extends('layouts.staff', ['module' => 'cashier', 'view' => 'receipt', 'active' => 'orders'])

@section('title', 'Receipt · Brew & Bite Café')
@section('heading', 'Receipt')
@section('sub', 'Order '.$order)
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="3" mockup="cashier.html?view=receipt" />
@endsection
