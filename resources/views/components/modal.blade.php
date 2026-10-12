@props(['id', 'title', 'action', 'method' => 'POST', 'wide' => false, 'submit' => 'Save', 'danger' => false])

{{--
    Staff modal holding one real form (mockup .modal-back > form.modal).
    Open it with any element carrying data-modal-open="{{ $id }}". If the form fails validation,
    the page comes back with the modal open (hidden _modal field + data-modal-autoopen).
    A shared modal (one per screen, many rows) gets its action from the opener's data-action —
    see resources/js/core/modal.js; _action keeps it across a failed validation.
    Slots: default = body fields; optional "footer" replaces the Cancel / Save buttons.
--}}
@php
    $reopen = isset($errors) && $errors->any() && old('_modal') === $id;
    // Re-use the row's action after a failed submit — only if it points back into this app
    $kept = $reopen ? (string) old('_action') : '';
    $formAction = $kept !== '' && str_starts_with($kept, url('/').'/') ? $kept : $action;
@endphp
<div class="modal-back" id="{{ $id }}" hidden @if ($reopen) data-modal-autoopen @endif>
    <form {{ $attributes->class(['modal', 'wide' => $wide]) }} method="POST" action="{{ $formAction }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" novalidate>
        @csrf
        @unless (strtoupper($method) === 'POST')
            @method($method)
        @endunless
        <input type="hidden" name="_modal" value="{{ $id }}">
        <input type="hidden" name="_action" value="{{ $formAction }}">
        <div class="modal-head">
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="x-btn" data-modal-close aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            {{ $slot }}
        </div>
        <div class="modal-foot">
            @isset($footer)
                {{ $footer }}
            @else
                <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn {{ $danger ? 'btn-danger' : 'btn-primary' }}" data-modal-submit>{{ $submit }}</button>
            @endisset
        </div>
    </form>
</div>
