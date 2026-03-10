<?php

use App\Models\Product;
use App\Models\User;

describe('Product Management', function () {

    it('allows any authenticated user to list products', function () {
        $user = User::factory()->create();
        Product::factory()->count(5)->create();

        $this->actingAs($user)
            ->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    });

    it('allows admin to create a product', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/products', ['name' => 'Widget', 'amount' => 2500])
            ->assertStatus(201)
            ->assertJsonPath('name', 'Widget')
            ->assertJsonPath('amount', 2500);
    });

    it('allows finance to create a product', function () {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->postJson('/api/products', ['name' => 'Gadget', 'amount' => 1000])
            ->assertStatus(201);
    });

    it('forbids user role from creating products', function () {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/api/products', ['name' => 'Forbidden', 'amount' => 500])
            ->assertStatus(403);
    });

    it('allows admin to update a product', function () {
        $admin   = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->putJson("/api/products/{$product->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'New Name');
    });

    it('allows admin to soft delete a product', function () {
        $admin   = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    });

    it('validates amount must be a positive integer', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/products', ['name' => 'Bad', 'amount' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });
});
