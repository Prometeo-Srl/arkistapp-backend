<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The profile picture of the menu (prototype 078): POST /auth/me/avatar stores
 * it, GET /auth/me/avatar serves it back.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_the_picture_and_returns_its_path(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('io.jpg'),
            ]);

        $response->assertOk();
        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::assertExists($path);
        $this->assertSame($path, $response->json('data.avatar_path'));
    }

    public function test_a_second_upload_replaces_the_first(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk();
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ])->assertOk();

        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
    }

    public function test_the_endpoint_serves_the_bytes_and_404s_without_a_picture(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/auth/me/avatar')->assertNotFound();

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('io.jpg'),
        ])->assertOk();

        $this->actingAs($user)
            ->get('/api/auth/me/avatar')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** Served inline, so an SVG here would run its script on the API origin. */
    public function test_an_svg_is_refused(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->avatar_path);
    }
}
