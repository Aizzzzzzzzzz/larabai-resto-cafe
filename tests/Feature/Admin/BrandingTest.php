<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Services\Branding;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Admin → Logo: upload, replace and reset the café's logo, and see it everywhere the "LB" badge was. */
class BrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Settings::flush();
        $this->asAdmin();
    }

    private function upload(string $name = 'logo.png', int $w = 800, int $h = 800): TestResponse
    {
        return $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->image($name, $w, $h)]);
    }

    public function test_the_page_shows_the_built_in_mark_until_a_logo_is_uploaded(): void
    {
        $this->get(route('admin.branding.edit'))
            ->assertOk()
            ->assertSee('Upload a new logo')
            ->assertSee('<span class="brand-mark">LB</span>', false)
            ->assertDontSee('Remove my logo');
    }

    public function test_the_owner_can_upload_a_logo_which_is_resized_and_saved(): void
    {
        $this->upload()->assertRedirect(route('admin.branding.edit'))->assertSessionHas('status', 'The new logo is live.');

        $path = Branding::logoPath();
        $this->assertMatchesRegularExpression('#^branding/[A-Za-z0-9]{40}\.(webp|jpg)$#', $path);
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 512], [$w, $h], 'kept small: it is shown at about 36 px');

        $this->assertSame('Changed logo', AuditLog::latest('id')->first()->action);
        $this->get(route('admin.branding.edit'))->assertSee('Remove my logo');
    }

    public function test_the_logo_replaces_the_badge_on_every_screen_and_the_tab_icon(): void
    {
        $this->upload();
        $url = Branding::logoUrl();
        $img = '<img class="brand-mark brand-logo" src="'.$url.'" alt="LaraBai Baristro">';

        $this->get(route('home'))->assertSee($img, false)->assertSee('<link rel="icon" href="'.$url.'"', false)->assertDontSee('>LB<', false);
        $this->get(route('cashier.pos.index'))->assertSee($img, false);   // the staff sidebar
        $this->get(route('kitchen.display'))->assertSee($img, false);
        $this->get(route('navigator'))->assertSee($img, false);
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))->assertSee($img, false);
        $this->get('/no-such-page')->assertNotFound()->assertSee($img, false); // error pages too
    }

    public function test_without_a_logo_every_screen_keeps_the_lb_badge_and_the_default_icon(): void
    {
        $this->get(route('home'))->assertSee('<span class="brand-mark">LB</span>', false)->assertSee('favicon.svg', false);
    }

    public function test_replacing_the_logo_deletes_the_old_file_and_reset_goes_back_to_the_badge(): void
    {
        $this->upload('one.png');
        $first = Branding::logoPath();

        $this->upload('two.png');
        $second = Branding::logoPath();
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->put(route('admin.branding.update'), ['remove_logo' => 1])->assertSessionHas('status', 'The logo was reset to the built-in mark.');
        $this->assertNull(Branding::logoPath());
        Storage::disk('public')->assertMissing($second);
        $this->assertSame('Reset logo', AuditLog::latest('id')->first()->action);
        $this->get(route('home'))->assertSee('<span class="brand-mark">LB</span>', false);
    }

    public function test_saving_with_nothing_chosen_changes_nothing(): void
    {
        $this->put(route('admin.branding.update'), [])->assertSessionHas('status', 'Nothing changed. Choose a picture first.');

        $this->assertNull(Branding::logoPath());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_only_real_pictures_are_accepted(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf')])->assertSessionHasErrors('logo');
        $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])->assertSessionHasErrors('logo');
        $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;')])->assertSessionHasErrors('logo');
        $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->image('big.png')->size(3000)])->assertSessionHasErrors(['logo' => 'That picture is over 2 MB. Choose a smaller one.']);

        $this->assertNull(Branding::logoPath());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_error_pages_still_draw_when_the_settings_table_is_unreachable(): void
    {
        Schema::drop('settings');
        Settings::flush();

        $this->get('/no-such-page')->assertNotFound()->assertSee('<span class="brand-mark">LB</span>', false);
    }

    public function test_only_the_owner_changes_the_logo(): void
    {
        foreach (['cashier', 'kitchen', 'stock'] as $username) {
            $this->signIn($username)->get(route('admin.branding.edit'))->assertForbidden();
            $this->put(route('admin.branding.update'), ['logo' => UploadedFile::fake()->image('x.png')])->assertForbidden();
        }

        $this->assertNull(Branding::logoPath());
        auth()->logout();
        $this->get(route('admin.branding.edit'))->assertRedirect(route('login', ['next' => '/admin/branding']));
    }

    public function test_the_logo_link_is_in_the_owners_sidebar_only(): void
    {
        $this->get(route('admin.dashboard'))->assertSee(route('admin.branding.edit'), false)->assertSee('Logo');
        $this->asCashier()->get(route('cashier.pos.index'))->assertDontSee(route('admin.branding.edit'), false);
    }
}
