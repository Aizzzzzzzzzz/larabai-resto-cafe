@props(['name'])

{{-- Validation message for one field: <x-field-error name="qty" /> --}}
@isset($errors)
    @error($name)
        <span class="err-msg" role="alert">{{ $message }}</span>
    @enderror
@endisset
