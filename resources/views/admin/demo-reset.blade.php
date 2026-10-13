@extends('layouts.staff', ['module' => 'admin', 'view' => 'demo-reset', 'active' => 'dashboard'])

@section('title', 'Reset Demo Data · Brew & Bite Café')
@section('heading', 'Reset demo data')
@section('sub', 'Clears every saved record and restores the sample data')
@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.dashboard') }}">← Back to the dashboard</a>
@endsection

@section('content')
    <section class="panel panel-pad reset-panel">
        <h2>This will erase</h2>
        <ul class="reset-list">
            @foreach ($counts as $label => $n)
                <li><b>{{ number_format($n) }}</b> {{ $label }}</li>
            @endforeach
        </ul>
        <p class="hint">Menu items, prices, ingredients and staff accounts go back to the sample set too. Demo mode only — a live café never shows this page.</p>

        <x-confirm-form :action="route('admin.demo-reset.store')" word="RESET" button="Reset demo data" />
    </section>
@endsection
