<?php

use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gateway\ChargeResponse;
use App\Services\Gateway\Drivers\Gateway1Driver;

describe('Refund', function () {

    it('allows finance role to refund a paid transaction', function () {
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        $finance = User::factory()->finance()->create();
        $transaction = Transaction::factory()->create([
            'gateway_id'  => $gateway->id,
            'status'      => 'paid',
            'external_id' => 'ext-123',
        ]);

        $this->mock(Gateway1Driver::class)
            ->shouldReceive('refund')
            ->once()
            ->with('ext-123');

        $this->actingAs($finance)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(200)
            ->assertJsonPath('status', 'refunded');
    });

    it('allows admin role to refund a paid transaction', function () {
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create([
            'gateway_id'  => $gateway->id,
            'status'      => 'paid',
            'external_id' => 'ext-999',
        ]);

        $this->mock(Gateway1Driver::class)->shouldReceive('refund')->once();

        $this->actingAs($admin)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(200);
    });

    it('forbids user role from refunding', function () {
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->create(['gateway_id' => $gateway->id]);

        $this->actingAs($user)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(403);
    });

    it('returns 422 when trying to refund an already refunded transaction', function () {
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        $finance = User::factory()->finance()->create();
        $transaction = Transaction::factory()->create([
            'gateway_id' => $gateway->id,
            'status'     => 'refunded',
        ]);

        $this->actingAs($finance)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(422);
    });
});
