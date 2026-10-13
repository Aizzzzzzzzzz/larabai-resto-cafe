<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Menu item photos: upload when adding, replace or remove when editing, shown on every screen that drew the art. */
class MenuPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->asAdmin();
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            '_modal' => 'itemModal', 'mode' => 'add', 'name' => 'Hazelnut Latte', 'category' => 'coffee', 'price' => 175,
            'description' => 'Espresso, milk and hazelnut.', 'large' => 20, 'template' => 'coffee', 'is_new' => 1,
            'ingredients' => [['code' => 'beans', 'qty' => 18], ['code' => 'milk', 'qty' => 180]],
        ], $overrides);
    }

    private function photo(string $name = 'latte.jpg', int $w = 1600, int $h = 1200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    public function test_a_new_item_can_come_with_a_photo(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['image' => $this->photo()]))->assertSessionHasNoErrors();

        $path = MenuItem::where('name', 'Hazelnut Latte')->value('image_path');
        $this->assertMatchesRegularExpression('#^menu/[A-Za-z0-9]{40}\.(webp|jpg)$#', $path);
        Storage::disk('public')->assertExists($path);

        // Re-drawn at no more than 900 px, so the 1600 px original is not kept
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([900, 675], [$w, $h]);
    }

    public function test_the_photo_shows_on_the_menu_the_pos_the_homepage_and_the_admin_list(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['image' => $this->photo(), 'is_best_seller' => 1]));
        $path = MenuItem::where('name', 'Hazelnut Latte')->value('image_path');

        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))->assertSee('class="art-photo" src="'.asset('storage/'.$path).'"', false);
        $this->get(route('cashier.pos.index'))->assertSee('storage/'.$path, false);
        $this->get(route('admin.menu-items.index'))->assertSee('<img class="sw" src="'.asset('storage/'.$path).'"', false);
        $this->get(route('home'))->assertSee('storage/'.$path, false);
    }

    public function test_an_item_without_a_photo_keeps_its_drawn_art(): void
    {
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))
            ->assertSee('art-glass', false)->assertDontSee('art-photo', false);
    }

    public function test_editing_can_replace_keep_or_remove_the_photo(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['image' => $this->photo('a.jpg')]));
        $first = MenuItem::where('name', 'Hazelnut Latte')->value('image_path');
        $form = ['mode' => 'edit'] + $this->item(); // like the browser, it still sends the hidden options choice

        // Saving without a file keeps the photo
        $this->put(route('admin.menu-items.update', 'hazelnut-latte'), $form)->assertSessionHasNoErrors();
        $this->assertSame($first, MenuItem::where('code', 'hazelnut-latte')->value('image_path'));

        // A new file replaces it, and the old file is deleted
        $this->put(route('admin.menu-items.update', 'hazelnut-latte'), ['image' => $this->photo('b.png', 400, 400)] + $form)->assertSessionHasNoErrors();
        $second = MenuItem::where('code', 'hazelnut-latte')->value('image_path');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
        $this->assertStringContainsString('photo changed', AuditLog::latest('id')->first()->detail);

        // The "remove" box takes it away
        $this->put(route('admin.menu-items.update', 'hazelnut-latte'), ['remove_image' => 1] + $form)->assertSessionHasNoErrors();
        $this->assertNull(MenuItem::where('code', 'hazelnut-latte')->value('image_path'));
        Storage::disk('public')->assertMissing($second);
        $this->assertStringContainsString('photo removed', AuditLog::latest('id')->first()->detail);
    }

    public function test_only_real_pictures_of_a_sensible_size_are_accepted(): void
    {
        $url = route('admin.menu-items.store');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->post($url, $this->item(['image' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')]))->assertSessionHasErrors('image');
        $this->post($url, $this->item(['image' => UploadedFile::fake()->createWithContent('x.svg', $svg)]))->assertSessionHasErrors('image');
        $this->post($url, $this->item(['image' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo 1;')]))->assertSessionHasErrors('image');
        $this->post($url, $this->item(['image' => UploadedFile::fake()->image('huge.jpg')->size(4000)]))->assertSessionHasErrors(['image' => 'That picture is over 3 MB. Choose a smaller one.']);

        $this->assertNull(MenuItem::where('name', 'Hazelnut Latte')->first());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_failed_save_leaves_no_stray_file_and_the_editor_form_uploads_files(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['name' => '', 'image' => $this->photo()]))->assertSessionHasErrors('name');
        $this->assertSame([], Storage::disk('public')->allFiles());

        $html = $this->get(route('admin.menu-items.index'))->getContent();
        $this->assertMatchesRegularExpression('/id="itemModal".*?<form[^>]*enctype="multipart\/form-data"/s', $html);
        $this->assertStringContainsString('name="image"', $html);
    }

    public function test_deleting_an_item_that_was_never_sold_deletes_its_photo_but_one_with_sales_keeps_it(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['image' => $this->photo()]));
        $path = MenuItem::where('code', 'hazelnut-latte')->value('image_path');
        $this->delete(route('admin.menu-items.destroy', 'hazelnut-latte'));
        Storage::disk('public')->assertMissing($path);

        // A sold item is only switched off, so receipts and history keep what they show
        $kept = 'menu/'.str_repeat('a', 40).'.webp';
        MenuItem::where('code', 'latte')->update(['image_path' => $kept]);
        Storage::disk('public')->put($kept, 'x');
        $this->delete(route('admin.menu-items.destroy', 'latte'));
        Storage::disk('public')->assertExists($kept);
    }

    public function test_only_the_owner_can_upload(): void
    {
        $this->signIn('cashier')->post(route('admin.menu-items.store'), $this->item(['image' => $this->photo()]))->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
