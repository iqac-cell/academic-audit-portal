<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sso\OidcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackchannelLogoutTest extends TestCase
{
    use FakesQSphere, RefreshDatabase;

    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableSso();
        $this->alice = User::factory()->create(['sso_sub' => 'qs-1']);
        $this->bob = User::factory()->create(['sso_sub' => 'qs-2']);
        foreach ([$this->alice, $this->alice, $this->bob] as $u) {
            DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $u->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time()]);
        }
    }

    private function logoutToken(array $overrides = []): string
    {
        return $this->signToken(array_merge([
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'iat' => time(),
            'exp' => time() + 120,
            'jti' => (string) Str::uuid(),
            'sub' => 'qs-1',
            'events' => [OidcClient::BACKCHANNEL_EVENT => new \stdClass],
        ], $overrides));
    }

    private function sessionsOf(User $u): int
    {
        return DB::table('sessions')->where('user_id', $u->id)->count();
    }

    public function test_logout_token_deletes_the_users_sessions(): void
    {
        $this->post('/sso/backchannel-logout', ['logout_token' => $this->logoutToken()])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(0, $this->sessionsOf($this->alice));
        $this->assertSame(1, $this->sessionsOf($this->bob));
    }

    public function test_sid_only_token_uses_the_recorded_login(): void
    {
        DB::table('sso_sessions')->insert(['sid' => 'qs-sess-9', 'user_id' => $this->bob->id, 'created_at' => now()]);
        $token = $this->logoutToken(['sub' => null, 'sid' => 'qs-sess-9']);

        $this->post('/sso/backchannel-logout', ['logout_token' => $token])->assertOk();

        $this->assertSame(0, $this->sessionsOf($this->bob));
        $this->assertSame(2, $this->sessionsOf($this->alice));
    }

    public function test_replayed_token_is_rejected(): void
    {
        $token = $this->logoutToken();
        $this->post('/sso/backchannel-logout', ['logout_token' => $token])->assertOk();
        $this->post('/sso/backchannel-logout', ['logout_token' => $token])->assertStatus(400);
    }

    public static function badTokens(): array
    {
        return [
            'no event' => [['events' => ['other' => []]]],
            'has nonce' => [['nonce' => 'n']],
            'wrong audience' => [['aud' => 'sdg-portal']],
            'wrong issuer' => [['iss' => 'https://evil.example']],
            'no sub or sid' => [['sub' => null]],
            'no jti' => [['jti' => null]],
            'expired' => [['exp' => time() - 3600]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badTokens')]
    public function test_invalid_logout_token_is_rejected(array $overrides): void
    {
        $this->post('/sso/backchannel-logout', ['logout_token' => $this->logoutToken($overrides)])->assertStatus(400);
        $this->assertSame(2, $this->sessionsOf($this->alice));
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $token = $this->signToken([
            'iss' => $this->issuer, 'aud' => $this->clientId, 'iat' => time(), 'jti' => 'j1', 'sub' => 'qs-1',
            'events' => [OidcClient::BACKCHANNEL_EVENT => new \stdClass],
        ], 'sso-other-key');

        $this->post('/sso/backchannel-logout', ['logout_token' => $token])->assertStatus(400);
        $this->assertSame(2, $this->sessionsOf($this->alice));
    }

    public function test_missing_token_is_rejected_without_csrf_error(): void
    {
        $this->post('/sso/backchannel-logout')->assertStatus(400);
    }
}
