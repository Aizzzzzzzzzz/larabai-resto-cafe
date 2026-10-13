<?php

namespace Tests\Feature\Public;

use App\Enums\Role;
use App\Support\StaffScreens;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Staff login: the form, demo accounts, role landing, safe "next", throttling, logout. */
class LoginTest extends TestCase
{
    public function test_login_page_shows_the_shift_board_and_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="m-staff v-login-login"', false)
            ->assertSee('Good shift ahead.')
            ->assertSeeInOrder(['waiting for payment', 'in the kitchen', 'low on stock'])
            ->assertSee('name="_token"', false)
            ->assertSee('type="password"', false)
            ->assertSee('Every login is recorded in the audit log');
    }

    public function test_demo_accounts_are_listed_only_in_demo_mode(): void
    {
        $this->get(route('login'))
            ->assertSee('Demo accounts')
            ->assertSeeInOrder(['Maria Dizon', 'Owner / Admin · opens Dashboard', 'Jessa Manalo', 'Cashier · opens Point of sale', 'Ramon Cruz', 'Kitchen · opens Kitchen display', 'Liza Garcia', 'Stock clerk · opens Stock']);

        config(['cafe.demo_mode' => false]);
        $this->get(route('login'))->assertDontSee('Demo accounts')->assertDontSee('cashier123');
    }

    #[DataProvider('landings')]
    public function test_each_role_lands_on_its_own_screen(string $username, string $password, string $path): void
    {
        $this->post(route('login.store'), ['username' => $username, 'password' => $password])->assertRedirect($path);
    }

    public static function landings(): array
    {
        return [
            'owner' => ['admin', 'admin123', '/admin'],
            'cashier' => ['cashier', 'cashier123', '/cashier/pos'],
            'second cashier' => ['cashier2', 'cashier123', '/cashier/pos'],
            'kitchen' => ['kitchen', 'kitchen123', '/kitchen'],
            'stock clerk' => ['stock', 'stock123', '/inventory/ingredients'],
        ];
    }

    public function test_login_is_remembered_and_changes_the_sidebar(): void
    {
        $this->post(route('login.store'), ['username' => 'CASHIER ', 'password' => 'cashier123']); // trimmed and lowercased

        $this->get(route('cashier.pos.index'))
            ->assertSee('Jessa Manalo')->assertSee('Cashier')
            ->assertDontSee('Audit logs')   // not in a cashier's sidebar
            ->assertDontSee('Maria Dizon');
    }

    public function test_wrong_details_say_so_without_saying_which_part(): void
    {
        $message = 'That username and password don’t match an active account. Check them, or pick a demo account below.';

        foreach ([['cashier', 'wrong'], ['nobody', 'cashier123'], ['Cashier', 'CASHIER123']] as [$u, $p]) {
            $this->from(route('login'))->post(route('login.store'), ['username' => $u, 'password' => $p])
                ->assertRedirect(route('login'))->assertSessionHasErrors(['credentials' => $message]);
        }
        $this->assertGuest();
    }

    public function test_the_failed_login_keeps_the_username_and_marks_the_fields(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['username' => 'cashier', 'password' => 'nope']);

        $this->get(route('login'))
            ->assertSee('value="cashier"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('role="alert"', false)
            ->assertDontSee('value="nope"', false);
    }

    public function test_both_fields_are_required(): void
    {
        $this->post(route('login.store'), ['username' => '', 'password' => 'x'])->assertSessionHasErrors(['username' => 'Enter your username.']);
        $this->post(route('login.store'), ['username' => 'cashier'])->assertSessionHasErrors(['password' => 'Enter your password.']);
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'bad'.$i]);
        }

        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123'])->assertStatus(429);
    }

    // ---- Where to go next ----------------------------------------------------------------------

    public function test_login_returns_to_the_screen_that_was_asked_for(): void
    {
        $this->get(route('login', ['next' => '/admin/audit-logs']))->assertSee('Log in to open <b>Audit logs</b>', false)->assertSee('name="next" value="/admin/audit-logs"', false);

        $this->post(route('login.store'), ['username' => 'admin', 'password' => 'admin123', 'next' => '/admin/audit-logs?area=Sales'])
            ->assertRedirect('/admin/audit-logs?area=Sales');
    }

    public function test_next_survives_a_failed_attempt(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['username' => 'admin', 'password' => 'bad', 'next' => '/kitchen']);

        $this->get(route('login'))->assertSee('Log in to open <b>Kitchen display</b>', false);
    }

    #[DataProvider('unsafeNext')]
    public function test_next_can_not_send_people_elsewhere(string $next): void
    {
        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123', 'next' => $next])->assertRedirect('/cashier/pos');
        $this->get(route('login', ['next' => $next]))->assertDontSee('Log in to open <b>', false);
    }

    public static function unsafeNext(): array
    {
        return [
            'another site' => ['https://evil.example/phish'],
            'protocol-relative' => ['//evil.example'],
            'backslash trick' => ['/\\evil.example'],
            'javascript' => ['javascript:alert(1)'],
            'a page that is not a staff screen' => ['/order/menu'],
            'a path that does not exist' => ['/nope/nothing'],
            'the login page itself' => ['/login'],
        ];
    }

    public function test_staff_screens_are_named_by_route(): void
    {
        $this->assertSame('Point of sale', StaffScreens::nameOf('/cashier/pos'));
        $this->assertSame('Take payment', StaffScreens::nameOf('/cashier/orders/12/payment'));
        $this->assertSame('Stock', StaffScreens::nameOf('/inventory/ingredients?tab=low'));
        $this->assertNull(StaffScreens::nameOf('/'));
        $this->assertNull(StaffScreens::nameOf(null));
        $this->assertSame('Point of sale', StaffScreens::homeName(Role::Cashier));
        $this->assertSame('/kitchen', StaffScreens::destination(Role::Kitchen, 'https://evil.example'));
    }

    // ---- Already logged in, and logging out --------------------------------------------------

    public function test_a_logged_in_visitor_can_continue_or_switch_user(): void
    {
        $this->post(route('login.store'), ['username' => 'kitchen', 'password' => 'kitchen123']);

        $this->get(route('login'))
            ->assertSee('You’re logged in as Ramon Cruz')
            ->assertSee('Continue to Kitchen display')
            ->assertSee('Switch user')
            ->assertDontSee('type="password"', false);
    }

    public function test_logout_forgets_the_user_and_says_so(): void
    {
        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123']);

        $this->assertAuthenticated();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionHas('status', 'You’re logged out.');
        $this->assertGuest();
    }

    public function test_a_demo_password_is_offered_only_while_it_is_still_the_password(): void
    {
        $this->get(route('login'))->assertSee('data-password="cashier123"', false);

        \App\Models\User::where('username', 'cashier')->first()->update(['password' => 'changed-by-the-owner']);

        $this->get(route('login'))->assertDontSee('changed-by-the-owner')->assertSee('data-username="cashier" data-password=""', false)
            ->assertSee('data-username="cashier2" data-password="cashier123"', false); // another account still on its demo password
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $stored = \App\Models\User::where('username', 'admin')->value('password');

        $this->assertStringNotContainsString('admin123', $stored);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('admin123', $stored));
    }

    public function test_a_switched_off_account_cannot_sign_in(): void
    {
        \App\Models\User::where('username', 'cashier')->update(['is_active' => false]);

        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123'])->assertSessionHasErrors('credentials');
        $this->assertGuest();
    }

    public function test_signing_in_starts_a_new_session(): void
    {
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123']);

        $this->assertNotSame($before, session()->getId(), 'against session fixation');
    }

    public function test_a_role_card_prefills_the_username(): void
    {
        $this->get(route('login', ['role' => 'inventory']))->assertSee('value="stock"', false);
        $this->get(route('login', ['role' => 'nonsense']))->assertDontSee('value="stock"', false);
    }
}
