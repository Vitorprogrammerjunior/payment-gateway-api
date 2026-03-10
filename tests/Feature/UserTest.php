<?php

use App\Models\User;

describe('User Management', function () {

    it('allows admin to list users', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    });

    it('allows admin to create a user', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name'                  => 'New User',
                'email'                 => 'newuser@example.com',
                'password'              => 'secret123',
                'password_confirmation' => 'secret123',
                'role'                  => 'finance',
            ])
            ->assertStatus(201)
            ->assertJsonPath('role', 'finance');
    });

    it('allows manager to create a user', function () {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->postJson('/api/users', [
                'name'                  => 'Another User',
                'email'                 => 'another@example.com',
                'password'              => 'secret123',
                'password_confirmation' => 'secret123',
                'role'                  => 'user',
            ])
            ->assertStatus(201);
    });

    it('forbids finance role from managing users', function () {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->getJson('/api/users')
            ->assertStatus(403);
    });

    it('allows admin to delete a user', function () {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$target->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('validates unique email on user creation', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name'                  => 'Duplicate',
                'email'                 => 'taken@example.com',
                'password'              => 'secret123',
                'password_confirmation' => 'secret123',
                'role'                  => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates role must be a valid value', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name'                  => 'Bad Role',
                'email'                 => 'bad@example.com',
                'password'              => 'secret123',
                'password_confirmation' => 'secret123',
                'role'                  => 'superuser',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    });
});
