@extends('layouts.staff', ['module' => 'admin', 'view' => 'table-qr', 'active' => 'dashboard'])

@section('title', 'Table QR Codes · Brew & Bite Café')
@section('heading', 'Table QR codes')
@section('sub', 'Print and place one standee on each table and at the counter')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="(new — not in mockup)" />
@endsection
