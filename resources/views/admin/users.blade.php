@extends('layouts.staff', ['module' => 'admin', 'view' => 'users', 'active' => 'users'])

@section('title', 'Users & Roles · Brew & Bite Café')
@section('heading', 'Users & roles')
@section('sub', 'Who can log in and which screens they can open')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="admin.html?view=users" />
@endsection
