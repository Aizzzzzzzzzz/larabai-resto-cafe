{{--
    Public pages: the café homepage (module 'home') and the demo system navigator (module 'index').
    Expects: $module. Each page draws its own top navigation.
--}}
@extends('layouts.base', ['view' => 'index', 'bodyClass' => ''])

@section('body')
    @yield('content')
@endsection
