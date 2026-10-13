@extends('layouts.staff', ['module' => 'admin', 'view' => 'dashboard', 'active' => 'dashboard'])

@php($k = $kpis)

@section('title', 'Dashboard · Brew & Bite Café')
@section('heading', $greeting)
@section('sub', 'Live numbers from paid orders')
@section('actions')
    <span class="clock" data-clock></span>
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.tables.qr') }}">Table QR codes</a>
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.audit-logs.index') }}">Audit log</a>
@endsection

@section('content')
    <p class="sample-note">Days before today use generated sample sales so the charts have history. Today’s numbers are the sample orders.
        @if (config('cafe.demo_mode'))<a class="link-btn" href="{{ route('admin.demo-reset.show') }}">Reset demo data</a>@endif</p>

    <div class="kpis">
        <x-stat-card label="Sales today" :value="peso($k['sales'])"><x-delta :now="$k['sales']" :before="$k['salesBefore']" /></x-stat-card>
        <x-stat-card label="Paid orders today" :value="$k['orders']"><x-delta :now="$k['orders']" :before="$k['ordersBefore']" /></x-stat-card>
        <x-stat-card label="Average order" :value="peso($k['average'])"><span class="hint">Same time last week {{ peso($k['averageBefore']) }}</span></x-stat-card>
        <x-stat-card label="Senior/PWD discounts today" :value="peso($k['discounts'])"><span class="hint">20% off the eligible share</span></x-stat-card>
    </div>

    <div class="row">
        <section class="panel panel-pad">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap"><h2>Sales, last 14 days</h2><span class="hint">Hover a bar for details · today is highlighted</span></div>
            <x-bar-chart :data="$salesChart" format="peso" :highlight="13" axis-label="Daily sales for the last 14 days" />
        </section>
        <section class="panel panel-pad">
            <h2>Payment methods</h2>
            <div><div class="hint" style="margin-bottom:6px">Last 30 days</div><x-share-bar :parts="$mixLast30" /></div>
            <div>
                <div class="hint" style="margin:6px 0">Today</div>
                @if ($mixToday)
                    <x-share-bar :parts="$mixToday" />
                @else
                    <p class="hint">No paid orders yet today.</p>
                @endif
            </div>
        </section>
    </div>

    <div class="row">
        <section class="panel panel-pad">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px"><h2>Orders by hour</h2><span class="hint">Average per day, last 7 days</span></div>
            @php($peak = collect($hoursChart)->search(collect($hoursChart)->sortByDesc('value')->first()))
            <x-bar-chart :data="$hoursChart" :height="180" :highlight="$peak" axis-label="Average orders per hour" />
        </section>
        <section class="panel panel-pad">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px"><h2>Best sellers</h2><span class="hint">Last 7 days, by cups/plates sold</span></div>
            <ol class="rank">
                @foreach ($best as $i => $item)
                    <li><span class="n">{{ $i + 1 }}</span><b>{{ $item['name'] }}</b><span class="v">{{ $item['qty'] }} sold · {{ peso($item['revenue'], false) }}</span><span class="meter"><i style="width:{{ $item['qty'] / $best[0]['qty'] * 100 }}%"></i></span></li>
                @endforeach
            </ol>
        </section>
    </div>

    <div class="row">
        <section class="panel panel-pad">
            <h2>Recent activity</h2>
            <ul class="feed">
                @forelse ($activity as $a)
                    <li><span><b>{{ $a['action'] }}</b><small>{{ $a['detail'] }} · {{ $a['user'] }}</small></span><time>{{ $a['ago'] }}</time></li>
                @empty
                    <li class="hint">No activity yet.</li>
                @endforelse
            </ul>
            <a class="link-btn" href="{{ route('admin.audit-logs.index') }}" style="font-weight:600;font-size:.88rem">See the full audit log →</a>
        </section>
        <section class="panel panel-pad">
            <h2>Needs attention</h2>
            <ul class="attn">
                @foreach ($attention as $row)
                    <li><a href="{{ $row['url'] }}"><span>{{ $row['label'] }}@if ($row['count'] && $row['note'])<small class="hint" style="display:block">{{ $row['note'] }}</small>@endif</span><span class="chip {{ $row['count'] ? $row['bad'] : $row['ok'] }}">{{ $row['count'] }}</span></a></li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
