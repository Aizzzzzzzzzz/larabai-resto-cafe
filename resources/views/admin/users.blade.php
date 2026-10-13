@extends('layouts.staff', ['module' => 'admin', 'view' => 'users', 'active' => 'users'])

@php
    // The modal re-opens after a failed save with what was typed, as an edit if it was one
    $reopen = old('_modal') === 'userModal';
    $mode = $reopen ? old('mode', 'add') : 'add';
    $self = $reopen && old('self') === '1';
    $typed = fn (string $field, $default = '') => $reopen ? old($field, $default) : $default;
    $addUser = ['mode' => 'add', 'self' => 0, 'full_name' => '', 'username' => '', 'role' => 'cashier', 'is_active' => 1];
@endphp

@section('title', 'Users & Roles · Brew & Bite Café')
@section('heading', 'Users & roles')
@section('sub', 'Who can log in and which screens they can open')
@section('actions')
    <button class="btn btn-primary btn-sm" type="button" data-modal-open="userModal" data-user-editor
            data-action="{{ route('admin.users.store') }}" data-method="POST" data-title="Add user" data-fill="{{ json_encode($addUser) }}">+ Add user</button>
@endsection

@section('content')
    <section class="panel">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>User</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td><div class="who"><span class="avatar">{{ $u['initials'] }}</span><span><b>{{ $u['full_name'] }}</b>@if ($u['is_you'])<small>You</small>@endif</span></div></td>
                            <td class="mono" style="font-size:.85rem">{{ $u['username'] }}</td>
                            <td>{{ $u['role_label'] }}</td>
                            <td><span class="chip {{ $u['is_active'] ? 'chip-green' : 'chip-red' }}">{{ $u['is_active'] ? 'Active' : 'Disabled' }}</span></td>
                            <td>{!! $u['last_login'] ? e($u['last_login']) : '<span class="muted">Never in this demo</span>' !!}</td>
                            <td class="r">
                                <button class="btn btn-ghost btn-sm" type="button" data-modal-open="userModal" data-user-editor
                                        data-action="{{ route('admin.users.update', $u['id']) }}" data-method="PUT" data-title="Edit {{ $u['full_name'] }}"
                                        data-fill="{{ json_encode(['mode' => 'edit', 'self' => (int) $u['is_you'], 'full_name' => $u['full_name'], 'username' => $u['username'], 'role' => $u['role'], 'is_active' => (int) $u['is_active']]) }}">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>What each role can do</h2></div>
        <div class="tbl-wrap">
            <table class="tbl matrix">
                <thead>
                    <tr>
                        <th>Can…</th>
                        @foreach ($roles as $role)
                            <th>{{ $role->label() }}<small style="display:block;font-family:var(--body);text-transform:none;letter-spacing:0">{{ $activePerRole[$role->value] ?? 0 }} active</small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $row)
                        <tr>
                            <td>{{ $row[0] }}</td>
                            @foreach (array_slice($row, 1) as $allowed)
                                <td>@if ($allowed)<span class="yes" aria-label="Yes">✓</span>@else<span class="no" aria-label="No">—</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('modals')
    <x-modal id="userModal" :title="$reopen ? ($mode === 'edit' ? 'Edit user' : 'Add user') : 'Add user'" :action="route('admin.users.store')" spoof submit="Save user" data-user-form>
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="self" value="{{ $self ? 1 : 0 }}">
        <div class="form-grid">
            <label class="lbl full">Full name<input class="input" name="full_name" value="{{ $typed('full_name') }}" autocomplete="off"></label>
            <label class="lbl">Username<input class="input" name="username" value="{{ $typed('username') }}" autocomplete="off" style="font-family:var(--mono)"></label>
            <label class="lbl">Role
                <select class="input" name="role">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($typed('role', 'cashier') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="lbl full"><span data-pass-label>{{ $mode === 'edit' ? 'New password (leave blank to keep)' : 'Password' }}</span>
                <input class="input" name="password" type="password" autocomplete="new-password">
                <span class="hint">Stored hashed. It can’t be read back, only replaced.</span></label>
            <label class="checkline full"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) $typed('is_active', 1))> Can log in</label>
        </div>
        <p class="hint" data-self-note hidden>This is your own account, so your role and login can’t be changed here.</p>
        @if ($reopen && $errors->any())
            @foreach ($errors->all() as $message)<p class="err-msg" role="alert">{{ $message }}</p>@endforeach
        @endif
    </x-modal>
@endpush
