@props(['item'])

{{-- Drawn menu art (mockup art()): a layered glass for drinks, a plate for food, in the item's two tones. --}}
@php([$dark, $light] = $item['tone'])
@if (! empty($item['image_url']))
    <img class="art-photo" src="{{ $item['image_url'] }}" alt="" loading="lazy">
@elseif ($item['is_drink'])
    <span class="art-glass" aria-hidden="true"><i style="height:22%;background:{{ $light }}"></i><i style="height:48%;background:linear-gradient(to top, {{ $light }}, {{ $dark }})"></i><i style="height:12%;background:{{ $light }};opacity:.55"></i></span>
@else
    <span class="art-plate" aria-hidden="true"><i style="background:radial-gradient(circle at 35% 35%, {{ $light }}, {{ $dark }})"></i></span>
@endif
