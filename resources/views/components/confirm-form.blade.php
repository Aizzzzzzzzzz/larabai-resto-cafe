@props(['action', 'method' => 'POST', 'word' => 'CONFIRM', 'button' => 'Confirm', 'label' => null])

{{--
    Destructive action guarded by typing a word (e.g. RESET). The button stays enabled: the JS
    (resources/js/core/confirm.js) stops the submit and explains if the word doesn't match, and the
    controller checks the word again — typing is UX, not security. Slot: what will happen.
--}}
<form {{ $attributes->class('confirm-form') }} method="POST" action="{{ $action }}" data-confirm-word="{{ $word }}" style="display:grid;gap:12px">
    @csrf
    @unless (strtoupper($method) === 'POST')
        @method($method)
    @endunless
    {{ $slot }}
    <label class="lbl">{{ $label ?? "Type {$word} to confirm" }}
        <input class="input" name="confirm" autocomplete="off" spellcheck="false">
    </label>
    <span class="err-msg" data-confirm-msg hidden>Type {{ $word }} exactly to continue.</span>
    <x-field-error name="confirm" />
    <div><button type="submit" class="btn btn-danger">{{ $button }}</button></div>
</form>
