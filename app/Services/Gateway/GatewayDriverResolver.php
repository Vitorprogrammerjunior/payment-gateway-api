<?php

namespace App\Services\Gateway;

use App\Models\Gateway;
use App\Services\Gateway\Drivers\Gateway1Driver;
use App\Services\Gateway\Drivers\Gateway2Driver;
use Illuminate\Support\Collection;

class GatewayDriverResolver
{
    private array $driverMap = [
        'gateway1' => Gateway1Driver::class,
        'gateway2' => Gateway2Driver::class,
    ];

    public function resolve(Gateway $gateway): GatewayDriverInterface
    {
        $driverClass = $this->driverMap[$gateway->name] ?? null;

        if ($driverClass === null) {
            throw new GatewayException("No driver registered for gateway [{$gateway->name}].");
        }

        return app($driverClass);
    }

    /**
     * Returns all active gateways ordered by priority, with their resolved drivers.
     *
     * @return Collection<int, array{gateway: Gateway, driver: GatewayDriverInterface}>
     */
    public function resolveActive(): Collection
    {
        return Gateway::active()->get()->map(fn (Gateway $gateway) => [
            'gateway' => $gateway,
            'driver'  => $this->resolve($gateway),
        ]);
    }
}
