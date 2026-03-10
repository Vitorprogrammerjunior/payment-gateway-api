<?php

use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gateway\ChargeResponse;
use App\Services\Gateway\Drivers\Gateway1Driver;
use App\Services\Gateway\Drivers\Gateway2Driver;

describe('Purchase', function () {

    beforeEach(function () {
        Gateway::create(['name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
        Gateway::create(['name' => 'gateway2', 'is_active' => true, 'priority' => 2]);
    });

    it('processes a purchase and returns a transaction', function () {
        $product = Product::factory()->create(['amount' => 5000]);

        $this->mock(Gateway1Driver::class)
            ->shouldReceive('charge')
            ->once()
            ->andReturn(new ChargeResponse('ext-123', '6063'));

        $response = $this->postJson('/api/purchase', [
            'client_name'  => 'John Doe',
            'client_email' => 'john@example.com',
            'card_number'  => '5569000000006063',
            'cvv'          => '010',
            'products'     => [
                ['id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('amount', 10000);
    });

    it('falls back to gateway 2 when gateway 1 fails', function () {
        $product = Product::factory()->create(['amount' => 3000]);

        $this->mock(Gateway1Driver::class)
            ->shouldReceive('charge')
            ->once()
            ->andThrow(new \App\Services\Gateway\GatewayException('Gateway 1 down'));

        $this->mock(Gateway2Driver::class)
            ->shouldReceive('charge')
            ->once()
            ->andReturn(new ChargeResponse('ext-456', '6063'));

        $this->postJson('/api/purchase', [
            'client_name'  => 'Jane Doe',
            'client_email' => 'jane@example.com',
            'card_number'  => '5569000000006063',
            'cvv'          => '010',
            'products'     => [
                ['id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)->assertJsonPath('status', 'paid');
    });

    it('returns 422 when all gateways fail', function () {
        $product = Product::factory()->create();

        $this->mock(Gateway1Driver::class)
            ->shouldReceive('charge')
            ->andThrow(new \App\Services\Gateway\GatewayException('Gateway 1 down'));

        $this->mock(Gateway2Driver::class)
            ->shouldReceive('charge')
            ->andThrow(new \App\Services\Gateway\GatewayException('Gateway 2 down'));

        $this->postJson('/api/purchase', [
            'client_name'  => 'Test',
            'client_email' => 'test@example.com',
            'card_number'  => '5569000000006063',
            'cvv'          => '010',
            'products'     => [['id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422);
    });

    it('validates required purchase fields', function () {
        $this->postJson('/api/purchase', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_name', 'client_email', 'card_number', 'cvv', 'products']);
    });

    it('validates card number must be 16 digits', function () {
        $product = Product::factory()->create();

        $this->postJson('/api/purchase', [
            'client_name'  => 'Test',
            'client_email' => 'test@example.com',
            'card_number'  => '1234',
            'cvv'          => '123',
            'products'     => [['id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['card_number']);
    });

    it('reuses existing client on repeated purchases', function () {
        $product = Product::factory()->create(['amount' => 1000]);

        $this->mock(Gateway1Driver::class)
            ->shouldReceive('charge')
            ->twice()
            ->andReturn(new ChargeResponse('ext-1', '1234'), new ChargeResponse('ext-2', '1234'));

        $payload = [
            'client_name'  => 'Repeat Client',
            'client_email' => 'repeat@example.com',
            'card_number'  => '5569000000006063',
            'cvv'          => '010',
            'products'     => [['id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/purchase', $payload)->assertStatus(201);
        $this->postJson('/api/purchase', $payload)->assertStatus(201);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transactions', 2);
    });
});
