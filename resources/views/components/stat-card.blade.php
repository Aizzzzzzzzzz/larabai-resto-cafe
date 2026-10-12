@props(['label', 'value', 'tone' => null])

{{-- KPI tile (mockup .kpi) — put several inside <div class="kpis">. tone: alert (red) | warn (caramel). Slot: delta or hint. --}}
<div {{ $attributes->class(['kpi', $tone]) }}>
    <small>{{ $label }}</small>
    <strong>{{ $value }}</strong>
    {{ $slot }}
</div>
