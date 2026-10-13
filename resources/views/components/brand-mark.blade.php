{{-- The café's logo mark: the owner's uploaded picture (Admin → Logo), or the built-in "LB" badge. --}}
@php($logo = \App\Services\Branding::logoUrl())
@if ($logo)
    <img {{ $attributes->class(['brand-mark', 'brand-logo']) }} src="{{ $logo }}" alt="{{ config('cafe.name') }}">
@else
    <span {{ $attributes->class(['brand-mark']) }}>LB</span>
@endif
