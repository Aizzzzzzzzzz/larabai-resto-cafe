@extends('layouts.staff', ['module' => 'admin', 'view' => 'audit', 'active' => 'audit'])

@section('title', 'Audit Logs · Brew & Bite Café')
@section('heading', 'Audit logs')
@section('sub', 'Every login, payment, discount, void, stock change and setting change')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="admin.html?view=audit" />
@endsection
