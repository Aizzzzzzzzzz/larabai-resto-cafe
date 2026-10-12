{{-- Staff login (not logged in yet). Standalone page: plain links, no sidebar. --}}
@extends('layouts.base', ['module' => 'login', 'view' => 'login', 'bodyClass' => 'm-staff'])

@section('body')
    @yield('content')
@endsection
