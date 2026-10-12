@props(['colspan'])

{{-- Staff table with no rows (mockup .empty-row): <x-empty-row colspan="6">No ingredients match.</x-empty-row> --}}
<tr><td colspan="{{ $colspan }}" class="empty-row">{{ $slot }}</td></tr>
