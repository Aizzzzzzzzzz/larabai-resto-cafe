@props(['step', 'mockup'])

{{-- UI scaffold placeholder: marks a screen whose markup hasn't been ported from the mockup yet. --}}
<div class="scaffold-note">
    <b>Screen ported in Step {{ $step }}</b>
    <span>Mockup source: <code>larabai-mockup/{{ $mockup }}</code></span>
    {{ $slot }}
</div>
