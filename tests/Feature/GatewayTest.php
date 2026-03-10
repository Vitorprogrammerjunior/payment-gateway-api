<?php

use App\Models\Gateway;
use App\Models\User;

describe('Gateway Management', function () {

    it('allows admin to list gateways', function () {
        $admin = User::factory()->admin()->create();
        Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        Gateway::create(['name' => 'gateway2', 'is_active' => true, 'priority' => 2]);

        $this->actingAs($admin)
            ->getJson('/api/gateways')
            ->assertStatus(200)
            ->assertJsonCount(2);
    });

    it('allows admin to disable a gateway', function () {
        $admin   = User::factory()->admin()->create();
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('is_active', false);
    });

    it('allows admin to change gateway priority', function () {
        $admin   = User::factory()->admin()->create();
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}", ['priority' => 2])
            ->assertStatus(200)
            ->assertJsonPath('priority', 2);
    });

    it('forbids non-admin from managing gateways', function () {
        $manager = User::factory()->manager()->create();
        $gateway = Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);

        $this->actingAs($manager)
            ->patchJson("/api/gateways/{$gateway->id}", ['is_active' => false])
            ->assertStatus(403);
    });
});
