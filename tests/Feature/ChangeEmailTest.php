<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "modifica email" (prototype 080): POST /auth/me/email parks the new address
 * and mails it a code, POST /auth/me/email/verify redeems the code and writes
 * the column. Nothing in between touches the user row.
 */
class ChangeEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function actor(): array
    {
        $user = User::factory()->create([
            'email' => 'edilcostruzioni@gmail.com',
            'password' => 'Password1',
        ]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /**
     * The six digits out of the mail the array transport collected — the code
     * is hashed in the cache, so the mailbox is the only place to read it.
     */
    private function mailedCode(): string
    {
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(1, $messages, 'no code was mailed');

        $body = $messages->first()->getOriginalMessage()->getTextBody();
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $body);
        preg_match('/\b(\d{6})\b/', $body, $matches);

        return $matches[1];
    }

    public function test_it_mails_a_code_and_leaves_the_address_alone(): void
    {
        [$user, $token] = $this->actor();

        $this->withToken($token)
            ->postJson('/api/auth/me/email', [
                'email' => 'nuova@gmail.com',
                'current_password' => 'Password1',
            ])
            ->assertStatus(202);

        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        // The code goes to the address being claimed, never to the current one:
        // proving the new mailbox is the whole point of the step.
        $this->assertSame('nuova@gmail.com', $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame('edilcostruzioni@gmail.com', $user->fresh()->email);
    }

    public function test_the_code_changes_the_email_and_verifies_it(): void
    {
        [$user, $token] = $this->actor();

        $this->withToken($token)->postJson('/api/auth/me/email', [
            'email' => 'nuova@gmail.com',
            'current_password' => 'Password1',
        ])->assertStatus(202);

        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => $this->mailedCode()])
            ->assertOk()
            ->assertJsonPath('data.email', 'nuova@gmail.com');

        $fresh = $user->fresh();
        $this->assertSame('nuova@gmail.com', $fresh->email);
        // The mailbox just answered, so the stamp belongs on it.
        $this->assertNotNull($fresh->email_verified_at);
    }

    public function test_a_code_is_single_use(): void
    {
        [, $token] = $this->actor();

        $this->withToken($token)->postJson('/api/auth/me/email', [
            'email' => 'nuova@gmail.com',
            'current_password' => 'Password1',
        ])->assertStatus(202);

        $code = $this->mailedCode();
        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => $code])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_it_refuses_the_request_with_the_wrong_current_password(): void
    {
        [$user, $token] = $this->actor();

        $this->withToken($token)
            ->postJson('/api/auth/me/email', [
                'email' => 'nuova@gmail.com',
                'current_password' => 'Sbagliata1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
        $this->assertSame('edilcostruzioni@gmail.com', $user->fresh()->email);
    }

    public function test_an_email_another_account_holds_is_rejected(): void
    {
        [, $token] = $this->actor();
        User::factory()->create(['email' => 'presa@gmail.com']);

        $this->withToken($token)
            ->postJson('/api/auth/me/email', [
                'email' => 'presa@gmail.com',
                'current_password' => 'Password1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
    }

    public function test_resubmitting_its_own_email_is_not_a_collision(): void
    {
        [, $token] = $this->actor();

        $this->withToken($token)
            ->postJson('/api/auth/me/email', [
                'email' => 'edilcostruzioni@gmail.com',
                'current_password' => 'Password1',
            ])
            ->assertStatus(202);
    }

    public function test_a_wrong_code_leaves_the_address_alone(): void
    {
        [$user, $token] = $this->actor();

        $this->withToken($token)->postJson('/api/auth/me/email', [
            'email' => 'nuova@gmail.com',
            'current_password' => 'Password1',
        ])->assertStatus(202);

        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertSame('edilcostruzioni@gmail.com', $user->fresh()->email);
    }

    /**
     * Six digits are guessable inside the ten-minute window, so the fifth wrong
     * guess burns the code even though it was the right one all along.
     */
    public function test_five_wrong_guesses_burn_the_code(): void
    {
        [$user, $token] = $this->actor();

        $this->withToken($token)->postJson('/api/auth/me/email', [
            'email' => 'nuova@gmail.com',
            'current_password' => 'Password1',
        ])->assertStatus(202);

        $code = $this->mailedCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        // The throttle allows five calls a minute and this test makes seven —
        // it is the attempt counter inside the cache entry that is under test,
        // and a 429 would hide whether that counter ever fired.
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($token)
                ->postJson('/api/auth/me/email/verify', ['code' => $wrong])
                ->assertStatus(422);
        }

        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertSame('edilcostruzioni@gmail.com', $user->fresh()->email);
    }

    public function test_verifying_without_a_pending_change_is_rejected(): void
    {
        [, $token] = $this->actor();

        $this->withToken($token)
            ->postJson('/api/auth/me/email/verify', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_both_steps_need_authentication(): void
    {
        $this->postJson('/api/auth/me/email', [
            'email' => 'nuova@gmail.com',
            'current_password' => 'Password1',
        ])->assertStatus(401);

        $this->postJson('/api/auth/me/email/verify', ['code' => '123456'])->assertStatus(401);
    }
}
