<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Data\SampleData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('cafe.demo_mode')) {
            // A real café starts with one owner account and a password nobody has seen but the person running this
            $password = Str::password(14, symbols: false);
            User::create(['full_name' => 'Owner', 'username' => 'admin', 'password' => $password, 'role' => 'admin']);
            $this->command?->warn("Created the owner account — username: admin, password: {$password} (change it in Users & roles).");

            return;
        }

        foreach (SampleData::users() as $u) {
            User::create([
                'full_name' => $u['full_name'],
                'username' => $u['username'],
                'password' => $u['password'], // hashed by the model
                'role' => $u['role'],
                'is_active' => $u['is_active'],
                'last_login_at' => $u['last_login_at'],
            ]);
        }
    }
}
