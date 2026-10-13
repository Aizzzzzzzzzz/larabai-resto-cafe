{{-- Uses view id "ingredients" so the Stock screen's styles (.mv-in, .mv-out, .used) apply --}}
@extends('layouts.staff', ['module' => 'inventory', 'view' => 'ingredients', 'active' => 'ingredients'])

@php
    use App\Support\Format;
    use App\Support\StockLevel;

    $unit = $ingredient['unit'];
@endphp

@section('title', $ingredient['name'].' · Stock History · LaraBai Baristro')
@section('heading', $ingredient['name'])
@section('sub', 'Stock history · the last 40 movements, newest first')
@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('inventory.ingredients.index') }}">← Back to stock</a>
@endsection

@section('content')
    <section class="panel panel-pad">
        <div class="toolbar">
            <span class="chip {{ StockLevel::chip($level) }}">{{ StockLevel::label($level) }}</span>
            <span>On hand <b>{{ StockLevel::format($ingredient['stock_qty'], $unit) }}</b> · reorder at {{ StockLevel::format($ingredient['reorder_level'], $unit) }}</span>
        </div>
        <div>
            <div class="lbl" style="margin-bottom:6px">Used in</div>
            <div class="used">
                @forelse ($usedIn as $item)
                    <span class="chip chip-muted">{{ $item['name'] }}</span>
                @empty
                    <span class="muted">Add-ons only, or not in any recipe yet</span>
                @endforelse
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>When</th><th>What</th><th>Reference</th><th class="r">Change</th><th class="r">Balance</th><th>By</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td>{{ Format::dateTime($m['moved_at']) }}</td>
                            <td>{{ $m['type']->label() }}@if ($m['note'])<small>{{ $m['note'] }}</small>@endif</td>
                            <td class="mono" style="font-size:.8rem">{{ $m['reference'] }}</td>
                            <td @class(['r', 'num', 'mv-in' => $m['qty'] > 0, 'mv-out' => $m['qty'] < 0])>{{ $m['qty'] > 0 ? '+' : '' }}{{ StockLevel::format($m['qty'], $unit) }}</td>
                            <td class="r num">{{ StockLevel::format($m['qty_after'], $unit) }}</td>
                            <td>{{ $m['user'] }}</td>
                        </tr>
                    @empty
                        <x-empty-row colspan="6">No stock movements yet. Paid orders and restocks will show here.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
