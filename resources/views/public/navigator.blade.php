@extends('layouts.public', ['module' => 'index'])

@section('title', 'System Navigator · Brew & Bite Café')

@section('content')
    <header class="topbar">
        <div class="container">
            <a class="brand" href="{{ route('navigator') }}">
                <span class="brand-mark">B&amp;B</span>
                <span class="brand-name">Brew &amp; Bite Café</span>
            </a>
            <div class="topbar-right">
                <span class="tag">Demo navigator</span>
                <x-theme-toggle />
            </div>
        </div>
    </header>

    <main class="container">
        <x-scaffold-note step="7" mockup="index.html">
            <span>Until then, every screen is linked here.</span>
        </x-scaffold-note>

        <section class="section">
            <h2>Scan a table</h2>
            <ul>
                @foreach (range(1, 5) as $table)
                    <li><a href="{{ URL::signedRoute('customer.session.table', ['table' => $table]) }}">Table {{ $table }} QR</a></li>
                @endforeach
                <li><a href="{{ URL::signedRoute('customer.session.counter') }}">Counter QR (take-out / delivery)</a></li>
            </ul>
        </section>

        <section class="section">
            <h2>Screens</h2>
            <ul>
                <li><a href="{{ route('home') }}">Homepage</a> · <a href="{{ route('login') }}">Staff login</a></li>
                <li>Cashier: <a href="{{ route('cashier.pos.index') }}">Point of sale</a> · <a href="{{ route('cashier.orders.index') }}">Orders</a></li>
                <li>Kitchen: <a href="{{ route('kitchen.display') }}">Kitchen display</a></li>
                <li>Inventory: <a href="{{ route('inventory.ingredients.index') }}">Stock</a></li>
                <li>Shared components: <a href="{{ route('navigator.components.staff') }}">Staff side</a> · <a href="{{ route('navigator.components.customer') }}">Customer side</a></li>
                <li>Admin: <a href="{{ route('admin.dashboard') }}">Dashboard</a> · <a href="{{ route('admin.menu-items.index') }}">Menu &amp; prices</a> · <a href="{{ route('admin.users.index') }}">Users &amp; roles</a> · <a href="{{ route('admin.audit-logs.index') }}">Audit logs</a></li>
            </ul>
        </section>
    </main>
@endsection
