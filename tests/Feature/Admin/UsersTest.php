<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Controller;
use Tests\TestCase;

/** UI scaffold Step 6 — Users & roles: list, role matrix, add/edit rules. */
class UsersTest extends TestCase
{
    private function user(array $overrides = []): array
    {
        return array_merge([
            '_modal' => 'userModal', 'mode' => 'add', 'full_name' => 'Pedro Reyes', 'username' => 'pedro.r',
            'role' => 'cashier', 'password' => 'secret1', 'is_active' => 1,
        ], $overrides);
    }

    public function test_list_shows_users_with_role_status_and_you(): void
    {
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Maria Dizon')->assertSee('Owner / Admin')->assertSee('Stock clerk')
            ->assertSeeInOrder(['Maria Dizon', 'You'])
            ->assertSee('data-action="'.route('admin.users.update', 1).'"', false)
            ->assertSee('data-action="'.route('admin.users.store').'"', false);
    }

    public function test_role_matrix_counts_active_users_per_role(): void
    {
        $this->get(route('admin.users.index'))
            ->assertSee('What each role can do')
            ->assertSeeInOrder(['Owner / Admin', '1 active', 'Cashier', '2 active', 'Kitchen', '1 active', 'Stock clerk', '1 active'])
            ->assertSee('Users and audit logs');
    }

    public function test_add_a_user(): void
    {
        $this->post(route('admin.users.store'), $this->user())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);

        // Usernames are lowercased and trimmed before they're checked
        $this->post(route('admin.users.store'), $this->user(['username' => '  PEDRO.R ']))->assertSessionHasNoErrors();
    }

    public function test_add_validation(): void
    {
        $url = route('admin.users.store');

        $this->post($url, $this->user(['full_name' => '']))->assertSessionHasErrors(['full_name' => 'Enter the person’s full name.']);
        $this->post($url, $this->user(['username' => 'ab']))->assertSessionHasErrors(['username' => 'Username: 3 to 20 lowercase letters, numbers, dots or underscores.']);
        $this->post($url, $this->user(['username' => 'has space']))->assertSessionHasErrors('username');
        $this->post($url, $this->user(['username' => 'Cashier']))->assertSessionHasErrors(['username' => 'cashier is already taken.']);
        $this->post($url, $this->user(['password' => '12345']))->assertSessionHasErrors(['password' => 'Password must be at least 6 characters.']);
        $this->post($url, $this->user(['password' => '']))->assertSessionHasErrors('password');
        $this->post($url, $this->user(['role' => 'owner']))->assertSessionHasErrors('role');
    }

    public function test_edit_a_user_without_changing_the_password(): void
    {
        $edit = $this->user(['mode' => 'edit', 'full_name' => 'Jessa M. Manalo', 'username' => 'cashier', 'password' => '']);

        $this->put(route('admin.users.update', 2), $edit)->assertRedirect(route('admin.users.index'))->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->put(route('admin.users.update', 2), ['password' => 'abc'] + $edit)->assertSessionHasErrors(['password' => 'New password must be at least 6 characters.']);
        $this->put(route('admin.users.update', 2), ['username' => 'cashier2'] + $edit)->assertSessionHasErrors('username'); // taken by Mark
        $this->put(route('admin.users.update', 99), ['full_name' => '', 'password' => '1'] + $edit)->assertNotFound(); // missing beats invalid
    }

    public function test_you_cannot_change_your_own_role_or_disable_yourself(): void
    {
        // Maria (id 1) is the demo's signed-in owner. The form leaves role and "can log in" out for her own account.
        $own = ['_modal' => 'userModal', 'mode' => 'edit', 'self' => 1, 'full_name' => 'Maria Dizon', 'username' => 'admin', 'password' => ''];

        $this->put(route('admin.users.update', 1), $own)->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', 1), $own + ['role' => 'cashier'])->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', 1), $own + ['is_active' => 0])->assertSessionHasErrors('role');
    }

    public function test_at_least_one_admin_must_stay_active(): void
    {
        // Mark (cashier) is promoted to admin first in the data; here Maria is the only admin
        $this->put(route('admin.users.update', 3), $this->user(['mode' => 'edit', 'username' => 'cashier2', 'password' => '', 'role' => 'admin']))->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', 4), $this->user(['mode' => 'edit', 'username' => 'kitchen', 'password' => '', 'role' => 'inventory', 'is_active' => 0]))->assertSessionHasNoErrors();
    }

    public function test_the_modal_locks_fields_on_your_own_account_and_reopens_after_a_failure(): void
    {
        $this->get(route('admin.users.index'))
            ->assertSee('data-pass-label', false)
            ->assertSee('data-self-note', false)
            ->assertSee('type="password"', false);

        $this->post(route('admin.users.store'), $this->user(['username' => 'ab']));
        $html = $this->get(route('admin.users.index'))->assertSee('Username: 3 to 20 lowercase letters')->assertSee('value="Pedro Reyes"', false)->getContent();

        $this->assertMatchesRegularExpression('/id="userModal"[^>]*data-modal-autoopen/', $html);
        $this->assertStringNotContainsString('value="secret1"', $html); // passwords are never sent back
    }
}
