@extends('layouts.staff', ['module' => 'admin', 'view' => 'audit', 'active' => 'audit'])

@php
    use App\Support\Format;
@endphp

@section('title', 'Audit Logs · LaraBai Baristro')
@section('heading', 'Audit logs')
@section('sub', 'Every login, payment, discount, void, stock change and setting change')
@section('actions')
    {{-- Exports what the filters show --}}
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.audit-logs.export', array_filter(['range' => $range, 'area' => $area, 'user' => $user, 'q' => $q])) }}">Export CSV</a>
@endsection

@section('content')
    <section class="panel">
        <form class="panel-head" method="GET" action="{{ route('admin.audit-logs.index') }}" role="search">
            <div class="filters">
                <select class="input" name="range" aria-label="Period" data-autosubmit>
                    @foreach (\App\Http\Controllers\Admin\AuditLogController::RANGES as $value => $label)
                        <option value="{{ $value }}" @selected($range === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="input" name="area" aria-label="Area" data-autosubmit>
                    <option value="">All areas</option>
                    @foreach ($areas as $name)
                        <option @selected($area === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                <select class="input" name="user" aria-label="User" data-autosubmit>
                    <option value="">Everyone</option>
                    @foreach ($users as $name)
                        <option @selected($user === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                <input class="input" name="q" type="search" value="{{ $q }}" placeholder="Search details, e.g. an order number" style="min-width:240px" aria-label="Search">
                <noscript><button class="btn btn-ghost btn-sm" type="submit">Filter</button></noscript>
            </div>
            <span class="hint">{{ $total }} {{ $total === 1 ? 'entry' : 'entries' }}</span>
        </form>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>When</th><th>User</th><th>Area</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($rows as $a)
                        <tr>
                            <td style="white-space:nowrap">{{ Format::dateTime($a['logged_at']) }}</td>
                            <td><b>{{ $a['user'] }}</b><small>{{ $a['role_label'] }}</small></td>
                            <td><span class="chip area-chip {{ $a['area'][1] }}">{{ $a['area'][0] }}</span></td>
                            <td>{{ $a['action'] }}</td>
                            <td>{{ $a['detail'] }}</td>
                        </tr>
                    @empty
                        <x-empty-row colspan="5">No entries match these filters.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($nextLimit)
            <div style="padding:12px 16px"><a class="btn btn-ghost btn-sm" href="{{ request()->fullUrlWithQuery(['limit' => $nextLimit]) }}">Show more</a></div>
        @endif
    </section>
@endsection
