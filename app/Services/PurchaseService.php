<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Gateway\ChargeRequest;
use App\Services\Gateway\GatewayDriverResolver;
use App\Services\Gateway\GatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly GatewayDriverResolver $resolver,
    ) {}

    /**
     * Process a purchase attempt.
     * Tries each active gateway in priority order; fails only if all gateways fail.
     *
     * @param  array{
     *     client_name: string,
     *     client_email: string,
     *     card_number: string,
     *     cvv: string,
     *     products: array<int, array{id: int, quantity: int}>
     * } $data
     */
    public function purchase(array $data): Transaction
    {
        $client   = $this->findOrCreateClient($data['client_name'], $data['client_email']);
        $products = $this->resolveProducts($data['products']);
        $amount   = $this->calculateTotal($products);

        $chargeRequest = new ChargeRequest(
            amount:      $amount,
            clientName:  $client->name,
            clientEmail: $client->email,
            cardNumber:  $data['card_number'],
            cvv:         $data['cvv'],
        );

        $activeGateways = $this->resolver->resolveActive();

        if ($activeGateways->isEmpty()) {
            throw new GatewayException('No active payment gateways available.');
        }

        $lastException = null;

        foreach ($activeGateways as ['gateway' => $gatewayModel, 'driver' => $driver]) {
            try {
                $chargeResponse = $driver->charge($chargeRequest);

                return DB::transaction(function () use ($client, $gatewayModel, $chargeResponse, $amount, $products) {
                    $transaction = Transaction::create([
                        'client_id'         => $client->id,
                        'gateway_id'        => $gatewayModel->id,
                        'external_id'       => $chargeResponse->externalId,
                        'status'            => 'paid',
                        'amount'            => $amount,
                        'card_last_numbers' => $chargeResponse->cardLastNumbers,
                    ]);

                    foreach ($products as ['product' => $product, 'quantity' => $quantity]) {
                        $transaction->products()->create([
                            'product_id'  => $product->id,
                            'quantity'    => $quantity,
                            'unit_amount' => $product->amount,
                        ]);
                    }

                    return $transaction->load(['client', 'gateway', 'products.product']);
                });
            } catch (GatewayException $e) {
                $lastException = $e;
            }
        }

        throw new GatewayException('All payment gateways failed. Last error: ' . $lastException->getMessage());
    }

    public function refund(Transaction $transaction): Transaction
    {
        if ($transaction->status === 'refunded') {
            throw ValidationException::withMessages(['transaction' => 'Transaction is already refunded.']);
        }

        if ($transaction->status !== 'paid') {
            throw ValidationException::withMessages(['transaction' => 'Only paid transactions can be refunded.']);
        }

        $gateway = $transaction->gateway;
        $driver  = $this->resolver->resolve($gateway);

        $driver->refund($transaction->external_id);

        $transaction->update(['status' => 'refunded']);

        return $transaction->fresh(['client', 'gateway', 'products.product']);
    }

    private function findOrCreateClient(string $name, string $email): Client
    {
        return Client::firstOrCreate(
            ['email' => $email],
            ['name' => $name],
        );
    }

    /**
     * @param  array<int, array{id: int, quantity: int}> $items
     * @return array<int, array{product: Product, quantity: int}>
     */
    private function resolveProducts(array $items): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['id']);
            $resolved[] = [
                'product'  => $product,
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{product: Product, quantity: int}> $products
     */
    private function calculateTotal(array $products): int
    {
        return array_sum(
            array_map(
                fn ($item) => $item['product']->amount * $item['quantity'],
                $products,
            )
        );
    }
}
