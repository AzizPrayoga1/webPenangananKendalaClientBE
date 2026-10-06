<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PwaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUser(string $role = 'client'): User
    {
        return User::create([
            'name' => 'PWA Test User',
            'email' => 'pwa_user_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * Test /api/health returns 200 with complete PWA and database health metadata.
     */
    public function test_health_check_returns_ok_and_pwa_ready_metadata()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'Penanganan Kendala Client API',
                'pwa_ready' => true,
                'database_connected' => true,
            ])
            ->assertJsonStructure([
                'status',
                'service',
                'version',
                'timestamp',
                'php_version',
                'environment',
                'pwa_ready',
                'database_connected',
                'vapid_configured',
            ]);
    }

    /**
     * Test /api/vapid-public-key returns public key and never exposes private key.
     */
    public function test_vapid_public_key_returns_public_key_without_leaking_private_key()
    {
        $response = $this->getJson('/api/vapid-public-key');

        $response->assertStatus(200)
            ->assertJsonStructure(['publicKey']);

        $data = $response->json();
        $this->assertNotEmpty($data['publicKey']);
        $this->assertArrayNotHasKey('privateKey', $data);
        $this->assertArrayNotHasKey('private_key', $data);
        $this->assertStringNotContainsString('private', json_encode($data));
    }

    /**
     * Test unauthenticated access to /api/push-subscriptions returns 401.
     */
    public function test_push_subscription_requires_authentication()
    {
        $response = $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test payload validation for /api/push-subscriptions.
     */
    public function test_push_subscription_validates_required_fields()
    {
        $user = $this->createTestUser();

        // Missing endpoint
        $response = $this->actingAs($user)->postJson('/api/push-subscriptions', [
            'keys' => [
                'p256dh' => 'sample-p256dh',
                'auth' => 'sample-auth',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint']);
    }

    /**
     * Test authenticated user can store a new push subscription.
     */
    public function test_authenticated_user_can_create_push_subscription()
    {
        $user = $this->createTestUser();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/device-abc-123';
        $payload = [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BHB2nF7...sample_public_key...',
                'auth' => 'sample_auth_token_xyz',
            ],
            'encoding' => 'aesgcm',
        ];

        $response = $this->actingAs($user)->postJson('/api/push-subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Push subscription saved successfully.',
            ])
            ->assertJsonPath('subscription.endpoint', $endpoint)
            ->assertJsonPath('subscription.user_id', $user->id);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'public_key' => 'BHB2nF7...sample_public_key...',
            'auth_token' => 'sample_auth_token_xyz',
            'content_encoding' => 'aesgcm',
        ]);
    }

    /**
     * Test subsequent subscribe calls with the same endpoint update rather than duplicate records.
     */
    public function test_push_subscription_updates_existing_endpoint_without_duplicates()
    {
        $user = $this->createTestUser();
        $endpoint = 'https://updates.push.services.mozilla.com/wpush/v2/firefox-sub-id-456';

        // Initial subscription
        $this->actingAs($user)->postJson('/api/push-subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'initial-key',
                'auth' => 'initial-auth',
            ],
        ])->assertStatus(201);

        $this->assertEquals(1, PushSubscription::where('endpoint', $endpoint)->count());

        // Update key on same endpoint
        $updateResponse = $this->actingAs($user)->postJson('/api/push-subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'renewed-key',
                'auth' => 'renewed-auth',
            ],
            'encoding' => 'aes128gcm',
        ]);

        $updateResponse->assertStatus(201);

        // Ensure no duplicate rows created
        $this->assertEquals(1, PushSubscription::where('endpoint', $endpoint)->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $endpoint,
            'public_key' => 'renewed-key',
            'auth_token' => 'renewed-auth',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    /**
     * Test unauthenticated access to /api/send-test-push returns 401.
     */
    public function test_send_test_push_requires_authentication()
    {
        $response = $this->postJson('/api/send-test-push');

        $response->assertStatus(401);
    }

    /**
     * Test /api/send-test-push returns 404 when user has no active subscription registered.
     */
    public function test_send_test_push_returns_404_when_user_has_no_active_subscription()
    {
        $user = $this->createTestUser();

        $response = $this->actingAs($user)->postJson('/api/send-test-push');

        $response->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    /**
     * Test /api/send-test-push only queries the authenticated user's own subscriptions.
     */
    public function test_send_test_push_targets_only_current_user_subscriptions()
    {
        $userA = $this->createTestUser('client');
        $userB = $this->createTestUser('programmer');

        // Create subscription only for User B
        PushSubscription::create([
            'user_id' => $userB->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user-b-device',
            'public_key' => 'keyB',
            'auth_token' => 'tokenB',
        ]);

        // User A attempts to send test push -> should get 404 because User A has no subscriptions
        $response = $this->actingAs($userA)->postJson('/api/send-test-push');

        $response->assertStatus(404);

        // Now register subscription for User A
        PushSubscription::create([
            'user_id' => $userA->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user-a-device',
            'public_key' => 'keyA',
            'auth_token' => 'tokenA',
        ]);

        // User A triggers test push -> executes (returns 200 with sent_count field)
        $responseA = $this->actingAs($userA)->postJson('/api/send-test-push');

        $responseA->assertStatus(200)
            ->assertJsonStructure(['message', 'sent_count']);
    }

    /**
     * Test that deleting a user cascades and deletes all associated push subscriptions.
     */
    public function test_deleting_user_cascades_and_removes_their_push_subscriptions()
    {
        $user = $this->createTestUser();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/cascade-test-endpoint',
            'public_key' => 'key',
            'auth_token' => 'token',
        ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
        ]);

        // Delete user
        $user->delete();

        // Subscription must be cascade deleted
        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
        ]);
    }
}
