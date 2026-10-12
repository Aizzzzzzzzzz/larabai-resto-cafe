@props(['status', 'label' => null])

{{-- Order status chip: <x-status-pill :status="$order['status']" /> (string or App\Enums\OrderStatus) --}}
@php($status = $status instanceof \App\Enums\OrderStatus ? $status : \App\Enums\OrderStatus::from($status))
<span {{ $attributes->class(['chip', $status->chip()]) }}>{{ $label ?? $status->label() }}</span>
