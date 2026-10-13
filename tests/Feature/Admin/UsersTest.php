<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Users & roles: list, role matrix, add/edit rules that really change the accounts. */
class UsersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

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
            ->assertSessionHas('status', 'Added Pedro Reyes.');

        $pedro = User::where('username', 'pedro.r')->firstOrFail();
        $this->assertSame(['Pedro Reyes', 'cashier', true], [$pedro->full_name, $pedro->role->value, $pedro->is_active]);
        $this->assertNotSame('secret1', $pedro->password, 'stored hashed');
        $this->assertTrue(Hash::check('secret1', $pedro->password));
        $this->assertSame(['Added user', 'Pedro Reyes · Cashier'], [AuditLog::latest('id')->first()->action, AuditLog::latest('id')->first()->detail]);

        // Usernames are lowercased and trimmed before they're checked
        $this->post(route('admin.users.store'), $this->user(['username' => '  PEDRO.R ']))->assertSessionHasErrors(['username' => 'pedro.r is already taken.']);
    }

    public function test_a_new_account_can_sign_in_and_a_switched_off_one_cannot(): void
    {
        $this->post(route('admin.users.store'), $this->user(['role' => 'kitchen']));
        $this->post(route('admin.users.store'), $this->user(['username' => 'off.user', 'full_name' => 'Off User', 'is_active' => 0]));
        Auth::logout();

        $this->post(route('login.store'), ['username' => 'pedro.r', 'password' => 'secret1'])->assertRedirect(route('kitchen.display', absolute: false));
        Auth::logout();
        $this->post(route('login.store'), ['username' => 'off.user', 'password' => 'secret1'])->assertSessionHasErrors('credentials');
        $this->assertGuest();
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
        $hash = User::find(2)->password;

        $this->put(route('admin.users.update', 2), $edit)->assertRedirect(route('admin.users.index'))->assertSessionHas('status', 'Saved Jessa M. Manalo.');
        $this->assertSame('Jessa M. Manalo', User::find(2)->full_name);
        $this->assertSame($hash, User::find(2)->password, 'a blank password keeps the old one');

        $this->put(route('admin.users.update', 2), ['password' => 'abc'] + $edit)->assertSessionHasErrors(['password' => 'New password must be at least 6 characters.']);
        $this->put(route('admin.users.update', 2), ['username' => 'cashier2'] + $edit)->assertSessionHasErrors('username'); // taken by Mark
        $this->put(route('admin.users.update', 99), ['full_name' => '', 'password' => '1'] + $edit)->assertNotFound(); // missing beats invalid
    }

    public function test_resetting_a_password_and_changing_a_role_are_audited(): void
    {
        $edit = $this->user(['mode' => 'edit', 'full_name' => 'Mark Bautista', 'username' => 'cashier2', 'role' => 'kitchen', 'password' => 'newpass1']);

        $this->put(route('admin.users.update', 3), $edit)->assertSessionHasNoErrors();

        $mark = User::find(3);
        $this->assertSame('kitchen', $mark->role->value);
        $this->assertTrue(Hash::check('newpass1', $mark->password));
        $this->assertSame('Mark Bautista · role Cashier → Kitchen, password reset', AuditLog::latest('id')->first()->detail);
        $this->assertStringNotContainsString('newpass1', AuditLog::latest('id')->first()->detail);
    }

    public function test_you_cannot_change_your_own_role_or_disable_yourself(): void
    {
        // Maria (id 1) is the signed-in owner. The form leaves role and "can log in" out for her own account.
        $own = ['_modal' => 'userModal', 'mode' => 'edit', 'self' => 1, 'full_name' => 'Maria Dizon', 'username' => 'admin', 'password' => ''];

        $this->put(route('admin.users.update', 1), $own)->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', 1), $own + ['role' => 'cashier'])->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', 1), $own + ['is_active' => 0])->assertSessionHasErrors('role');

        $maria = User::find(1);
        $this->assertSame(['admin', true], [$maria->role->value, $maria->is_active]);
    }

    public function test_at_least_one_admin_must_stay_active(): void
    {
        // Promote Mark first, so there are two admins: now Maria's co-admin can be demoted, but not the last one
        $this->put(route('admin.users.update', 3), $this->user(['mode' => 'edit', 'username' => 'cashier2', 'password' => '', 'role' => 'admin']))->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', 4), $this->user(['mode' => 'edit', 'username' => 'kitchen', 'password' => '', 'role' => 'inventory', 'is_active' => 0]))->assertSessionHasNoErrors();

        $this->signIn('cashier2')->put(route('admin.users.update', 1), $this->user(['mode' => 'edit', 'username' => 'admin', 'password' => '', 'role' => 'cashier']))->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', 3), $this->user(['mode' => 'edit', 'username' => 'cashier2', 'password' => '', 'role' => 'admin']))->assertSessionHasNoErrors(); // self, unchanged

        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
        $this->put(route('admin.users.update', 3), $this->user(['mode' => 'edit', 'username' => 'cashier2', 'password' => '', 'role' => 'cashier']))->assertSessionHasErrors('role'); // the last admin can't demote himself
    }

    public function test_a_switched_off_account_is_signed_out_on_its_next_request(): void
    {
        // A real sign-in, so each request reloads the account from the database
        Auth::logout();
        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123']);
        $this->get(route('cashier.orders.index'))->assertOk();

        User::where('username', 'cashier')->update(['is_active' => false]);
        Auth::forgetGuards(); // the test app lives on between requests; a real request starts without the user in memory

        $this->get(route('cashier.orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_only_the_owner_manages_users(): void
    {
        foreach (['cashier', 'kitchen', 'stock'] as $username) {
            $this->signIn($username)->get(route('admin.users.index'))->assertForbidden();
            $this->post(route('admin.users.store'), $this->user())->assertForbidden();
            $this->put(route('admin.users.update', 1), $this->user(['mode' => 'edit', 'username' => 'admin', 'role' => 'cashier']))->assertForbidden();
        }

        $this->assertNull(User::where('username', 'pedro.r')->first());
        $this->assertSame('admin', User::find(1)->role->value);
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
