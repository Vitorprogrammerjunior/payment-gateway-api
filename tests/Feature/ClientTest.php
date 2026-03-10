<?php

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;

describe('Clients', function () {

    it('allows authenticated user to list clients', function () {
        $user = User::factory()->create();
        Client::factory()->count(3)->create();

        $this->actingAs($user)
            ->getJson('/api/clients')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    });

    it('shows client details with all their transactions', function () {
        $user    = User::factory()->create();
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        $client  = Client::factory()->create();

        Transaction::factory()->count(2)->create([
            'client_id'  => $client->id,
            'gateway_id' => $gateway->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/clients/{$client->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $client->id)
            ->assertJsonCount(2, 'transactions');
    });

    it('returns 404 for a non-existent client', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/clients/9999')
            ->assertStatus(404);
    });

    it('forbids unauthenticated access to clients', function () {
        $this->getJson('/api/clients')->assertStatus(401);
    });
});
