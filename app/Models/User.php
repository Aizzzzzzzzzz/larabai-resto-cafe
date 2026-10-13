<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\FlushesCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A staff account (document.md §4.1). Customers never have one: they are known only by their session. */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use FlushesCatalog, HasFactory;

    protected $fillable = ['full_name', 'username', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Admin can open every module; the other roles only their own (document.md §3.3). */
    public function hasRole(Role ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }
}
