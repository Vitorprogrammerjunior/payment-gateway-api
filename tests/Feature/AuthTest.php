<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Authentication', function () {

    it('allows a user to login with correct credentials', function () {
        $user = User::factory()->create([
            'email'    => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    });

    it('rejects login with wrong credentials', function () {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    });

    it('validates required fields on login', function () {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('allows an authenticated user to logout', function () {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);
    });

    it('rejects unauthenticated access to protected routes', function () {
        $this->getJson('/api/clients')->assertStatus(401);
    });
});
