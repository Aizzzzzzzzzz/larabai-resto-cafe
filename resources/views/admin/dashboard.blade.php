@extends('layouts.staff', ['module' => 'admin', 'view' => 'dashboard', 'active' => 'dashboard'])

@section('title', 'Dashboard · Brew & Bite Café')
@section('heading', 'Dashboard')
@section('sub', 'Live numbers from paid orders')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="admin.html" />
@endsection
