@extends('layouts.staff', ['module' => 'admin', 'view' => 'branding', 'active' => 'branding'])

@section('title', 'Logo · '.config('cafe.name'))
@section('heading', 'Logo')
@section('sub', 'Shown in the headers, on the login page and as the browser tab icon')

@section('content')
    <section class="panel panel-pad branding">
        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            <div class="brand-preview" aria-label="How the logo looks in the header">
                <div class="bp light"><span class="brand"><x-brand-mark data-logo-preview-mark /><span class="brand-name">{{ config('cafe.name') }}</span></span></div>
                <div class="bp dark" data-theme="dark"><span class="brand"><x-brand-mark /><span class="brand-name">{{ config('cafe.name') }}</span></span></div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <label class="lbl full">Upload a new logo
                    <input class="input" type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-logo-input aria-describedby="logoHelp">
                    <span class="hint" id="logoHelp">JPG, PNG or WebP, up to 2 MB. A square picture with a transparent background (PNG or WebP) looks best. It is shown at about 36 pixels, so keep it simple.</span>
                </label>
                <div class="full" data-logo-chosen hidden>
                    <span class="photo-preview"><img alt="The chosen logo" data-logo-chosen-img></span>
                </div>
                @error('logo')<p class="err-msg full" role="alert">{{ $message }}</p>@enderror
                @if ($logo)
                    <label class="check full"><input type="hidden" name="remove_logo" value="0"><input type="checkbox" name="remove_logo" value="1"> Remove my logo and go back to the built-in “LB” mark</label>
                @endif
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save logo</button>
            </div>
        </form>
    </section>
@endsection
