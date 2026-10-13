<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Services\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit a staff account (document.md §3.7). Rules from the mockup: usernames are 3–20 lowercase
 * letters, numbers, dots or underscores and unique; a password is required for a new user and
 * optional (to reset it) when editing; you can't change your own role or disable yourself; and at
 * least one admin must stay active.
 */
class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        abort_if($this->route('user') && ! $this->target(), 404); // a missing account is a 404, not a validation error

        if ($this->has('username')) {
            $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')))]);
        }
    }

    public function rules(): array
    {
        $editing = $this->target();

        return [
            'full_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:/^[a-z0-9._]{3,20}$/', function ($attribute, $value, $fail) use ($editing) {
                $taken = collect(Catalog::users())->contains(fn ($u) => $u['username'] === $value && $u['id'] !== ($editing['id'] ?? null));
                if ($taken) {
                    $fail("{$value} is already taken.");
                }
            }],
            // Your own role can't change, so the field is left out of your own edit form
            'role' => $this->editingSelf() ? ['nullable', Rule::enum(Role::class)] : ['required', Rule::enum(Role::class)],
            'password' => $editing ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Enter the person’s full name.',
            'username.required' => 'Enter a username.',
            'username.regex' => 'Username: 3 to 20 lowercase letters, numbers, dots or underscores.',
            'password.required' => 'Password must be at least 6 characters.',
            'password.min' => $this->target() ? 'New password must be at least 6 characters.' : 'Password must be at least 6 characters.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! ($user = $this->target())) {
                return;
            }

            $role = $this->input('role', $user['role']);
            $active = $this->boolean('is_active', $user['is_active']);

            if ($this->editingSelf() && ($role !== $user['role'] || ! $active)) {
                $validator->errors()->add('role', 'You can’t change your own role or disable your own account.');

                return;
            }

            $otherAdmins = collect(Catalog::users())->filter(fn ($u) => $u['role'] === 'admin' && $u['is_active'] && $u['id'] !== $user['id'])->count();
            if ($user['role'] === 'admin' && ($role !== 'admin' || ! $active) && $otherAdmins < 1) {
                $validator->errors()->add('role', 'Keep at least one active admin.');
            }
        }];
    }

    /** The account being edited, or null when adding. */
    public function target(): ?array
    {
        return $this->route('user') ? collect(Catalog::users())->firstWhere('id', (int) $this->route('user')) : null;
    }

    public function editingSelf(): bool
    {
        return $this->target() !== null && $this->target()['id'] === $this->user()?->id;
    }
}
