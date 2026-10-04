<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = $this->member(Role::Admin);
    }

    public function test_uploads_are_resized_and_saved_as_png(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/branding/logo', ['image' => UploadedFile::fake()->image('logo.jpg', 2000, 1000)])
            ->assertSessionHas('success');

        $path = $this->company()->fresh()->logo;
        $this->assertStringStartsWith("companies/{$this->company()->id}/branding/logo-", $path);
        $this->assertStringEndsWith('.png', $path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([800, 400, IMAGETYPE_PNG], [$width, $height, $type]);
    }

    public function test_replacing_an_image_deletes_the_old_file(): void
    {
        $this->actingAs($this->admin)->post('/settings/branding/stamp', ['image' => UploadedFile::fake()->image('a.png')]);
        $old = $this->company()->fresh()->stamp;

        $this->actingAs($this->admin)->post('/settings/branding/stamp', ['image' => UploadedFile::fake()->image('b.png')]);

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($this->company()->fresh()->stamp);
    }

    public function test_svg_and_other_files_are_refused(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($this->admin)->post('/settings/branding/logo', ['image' => $svg])->assertSessionHasErrors('image');
        $this->actingAs($this->admin)->post('/settings/branding/other', ['image' => UploadedFile::fake()->image('a.png')])->assertNotFound();
    }

    public function test_remove_an_image(): void
    {
        $this->actingAs($this->admin)->post('/settings/branding/signature', ['image' => UploadedFile::fake()->image('s.png')]);
        $path = $this->company()->fresh()->signature;

        $this->actingAs($this->admin)->delete('/settings/branding/signature')->assertSessionHas('success');

        $this->assertNull($this->company()->fresh()->signature);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_logo_is_public_but_stamp_and_signature_are_for_members(): void
    {
        foreach (['logo', 'stamp'] as $kind) {
            $this->actingAs($this->admin)->post("/settings/branding/{$kind}", ['image' => UploadedFile::fake()->image("{$kind}.png")]);
        }
        $company = $this->company()->fresh();
        $this->post('/logout');

        $this->get($company->imageUrl('logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($company->imageUrl('stamp'))->assertNotFound();

        $this->actingAs($this->admin)->get($company->imageUrl('stamp'))->assertOk();
        $this->actingAs(User::factory()->create())->get($company->imageUrl('stamp'))->assertNotFound();
    }

    public function test_brand_colour(): void
    {
        $this->actingAs($this->admin)->put('/settings/branding', ['brand_color' => '#1D4ED8'])->assertSessionHas('success');
        $this->assertSame('#1d4ed8', $this->company()->fresh()->preferences()->get('brand_color'));

        $this->actingAs($this->admin)->put('/settings/branding', ['brand_color' => 'red; background:url(x)'])->assertSessionHasErrors('brand_color');
    }
}
