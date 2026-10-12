@extends('layouts.staff', ['module' => 'admin', 'view' => 'demo-reset', 'active' => 'dashboard'])

@section('title', 'Reset Demo Data · Brew & Bite Café')
@section('heading', 'Reset demo data')
@section('sub', 'Clears every saved record and restores the sample data')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="admin.html (Reset demo data)" />
@endsection
