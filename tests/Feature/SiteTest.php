<?php

namespace Tests\Feature;

use App\Helpers\SiteHelper;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_site_registration_page_is_displayed()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/site/register');

        $response->assertOk();
    }

    public function test_site_registration_pincode_can_be_completed()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/site/register')
            ->post('/site/register', [
                'name' => 'Test Site with pincode',
                'longitude' => 4.3415232,
                'latitude' => 50.8949242,
                'altitude' => 93.0,
                'timezone' => 'Europe/Brussels',
                'pincode' => ['1', '2', '3', '4', '5', '6'],
            ]);

        $response->assertRedirect('/dashboard');

        Event::assertDispatched(\App\Events\SiteCreated::class);

        $this->assertDatabaseHas('sites', [
            'name' => 'Test Site with pincode',
            'user_id' => $user->id,
            'auth_key' => '123456',
        ]);
    }

    public function test_site_registration_password_can_be_completed()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/site/register', [
            'name' => 'Test Site with password',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'password' => 'securepassword',
        ]);

        $response->assertRedirect('/dashboard');

        Event::assertDispatched(\App\Events\SiteCreated::class);

        $this->assertDatabaseHas('sites', [
            'name' => 'Test Site with password',
            'user_id' => $user->id,
            'auth_key' => 'securepassword',
        ]);
    }

    public function test_site_registration_noauth()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/site/register', [
            'name' => 'Test Site without auth',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
        ]);

        $response->assertInvalid(['pincode', 'password']);
        $response->assertSessionHasErrors(['pincode', 'password']);
    }

    public function test_site_registration_picture_can_be_completed()
    {
        Storage::fake('media');

        /** @var User $user */
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('site_picture.jpg', 600, 400)->size(3000);

        $response = $this->actingAs($user)->post('/site/register', [
            'name' => 'Test Site with picture',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'password' => 'securepassword',
            'picture_add' => $file,
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('sites', [
            'name' => 'Test Site with picture',
            'user_id' => $user->id,
        ]);

        Storage::disk('media')->assertExists('1/site_picture.jpg');
    }

    public function test_site_update_page_is_displayed()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)->get("/site/{$site->id}/edit");

        $response->assertOk();
    }

    public function test_site_information_can_be_updated()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/edit")
            ->post("/site/{$site->id}/edit", [
                'name' => 'Updated Test Site',
                'longitude' => 4.3415232,
                'latitude' => 50.8949242,
                'altitude' => 100.0,
                'timezone' => 'Europe/Brussels',
            ]);

        $response->assertRedirectBack();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'name' => 'Updated Test Site',
            'altitude' => 100.0,
        ]);
    }

    public function test_site_picture_can_be_added()
    {
        Storage::fake('media');

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $file = UploadedFile::fake()->image('new_picture.jpg', 600, 400)->size(3000);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/edit")
            ->post("/site/{$site->id}/edit", [
                'name' => 'Test Site',
                'longitude' => 4.3415232,
                'latitude' => 50.8949242,
                'altitude' => 93.0,
                'timezone' => 'Europe/Brussels',
                'auth_key' => '123456',
                'picture_add' => $file,
            ]);

        $response->assertRedirectBack();

        Storage::disk('media')->assertExists('2/new_picture.jpg');
    }

    public function test_site_picture_can_be_removed()
    {
        Storage::fake('media');

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $file = UploadedFile::fake()->image('picture_to_remove.jpg', 600, 400)->size(3000);

        $site->addMedia($file)->toMediaCollection('pictures');

        $response = $this->actingAs($user)->post("/site/{$site->id}/edit", [
            'picture_remove' => [$site->getFirstMedia('pictures')->uuid],
        ]);

        $response->assertRedirectBack();

        Storage::disk('media')->assertMissing('1/picture_to_remove.jpg');
    }

    public function test_site_picture_remove_with_non_existent_uuid()
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Site $site */
        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        // Try to remove a picture with a non-existent UUID
        $response = $this->actingAs($user)->post("/site/{$site->id}/edit", [
            'picture_remove' => [Str::uuid()],
        ]);

        $response->assertRedirectBack();
    }

    public function test_site_picture_remove_multiple_existing_media()
    {
        Storage::fake('media');

        /** @var User $user */
        $user = User::factory()->create();

        /** @var Site $site */
        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        // Add two fake files directly to the media collection
        $fakeFile1 = UploadedFile::fake()->image('picture1.jpg');
        $fakeFile2 = UploadedFile::fake()->image('picture2.jpg');

        $media1 = $site->addMedia($fakeFile1)->toMediaCollection('pictures');
        $media2 = $site->addMedia($fakeFile2)->toMediaCollection('pictures');

        // Verify the pictures were added
        $this->assertCount(2, $site->getMedia('pictures'));

        $uuids = [$media1->uuid, $media2->uuid];

        // Remove both pictures using the controller endpoint
        $response = $this->actingAs($user)->post("/site/{$site->id}/edit", [
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'picture_remove' => $uuids,
        ]);

        $response->assertRedirect("/site/{$site->id}/edit");

        // Verify both pictures were removed
        $this->assertCount(0, $site->fresh()->getMedia('pictures'));
    }

    public function test_site_auth_page_is_displayed()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)->get("/site/{$site->id}/auth");

        $response->assertOk();
    }

    public function test_site_auth_can_be_updated_with_pincode()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => 'foobar',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/auth")
            ->patch("/site/{$site->id}/auth", [
                'tab' => 'pincode',
                'pincode' => ['1', '2', '3', '4', '5', '6'],
            ]);

        $response->assertRedirectBack();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'auth_key' => '123456',
        ]);
    }

    public function test_site_auth_can_be_updated_with_password()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => 'foobar',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/auth")
            ->patch("/site/{$site->id}/auth", [
                'tab' => 'password',
                'password' => 'newsecurepassword',
            ]);

        $response->assertRedirectBack();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'auth_key' => 'newsecurepassword',
        ]);
    }

    public function test_site_delete_page_is_displayed()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)->get("/site/{$site->id}/delete");

        $response->assertOk();
    }

    public function test_site_can_be_deleted_with_pincode()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/delete")
            ->delete("/site/{$site->id}/delete", [
                'auth_key' => ['1', '2', '3', '4', '5', '6'],
            ]);

        $response->assertRedirect('/dashboard');

        Event::assertDispatched(\App\Events\SiteDeleted::class);

        $this->assertSoftDeleted('sites', [
            'id' => $site->id,
        ]);
    }

    public function test_site_cant_be_deleted_with_wrong_pincode()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => '123456',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/delete")
            ->delete("/site/{$site->id}/delete", [
                'auth_key' => ['0', '0', '0', '0', '0', '0'],
            ]);

        $response->assertRedirectBack();
        $response->assertSessionHasErrors(['auth_key']);

        Event::assertNotDispatched(\App\Events\SiteDeleted::class);

        $this->assertNotSoftDeleted('sites', [
            'id' => $site->id,
        ]);
    }

    public function test_site_can_be_deleted_with_password()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => 'securepassword',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/delete")
            ->delete("/site/{$site->id}/delete", [
                'auth_key' => 'securepassword',
            ]);

        $response->assertRedirect('/dashboard');

        Event::assertDispatched(\App\Events\SiteDeleted::class);

        $this->assertSoftDeleted('sites', [
            'id' => $site->id,
        ]);
    }

    public function test_site_cant_be_deleted_with_wrong_password()
    {
        Event::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $site = $user->sites()->create([
            'name' => 'Test Site',
            'longitude' => 4.3415232,
            'latitude' => 50.8949242,
            'altitude' => 93.0,
            'timezone' => 'Europe/Brussels',
            'auth_key' => 'securepassword',
        ]);

        $response = $this->actingAs($user)
            ->from("/site/{$site->id}/delete")
            ->delete("/site/{$site->id}/delete", [
                'auth_key' => 'notsecurepassword',
            ]);

        $response->assertRedirectBack();
        $response->assertSessionHasErrors(['auth_key']);

        Event::assertNotDispatched(\App\Events\SiteDeleted::class);

        $this->assertNotSoftDeleted('sites', [
            'id' => $site->id,
        ]);
    }

    public function test_site_short_id_is_generated_on_creation()
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $site = Site::factory()->createOne(['user_id' => $user->id]);

        $this->assertNotEmpty($site->short_id);
        $this->assertEquals(SiteHelper::getShortId($site), $site->short_id);
    }
}
