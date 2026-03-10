<?php

namespace App\Services\Gateway;

// To add a new gateway: implement this interface and register in GatewayDriverResolver.
interface GatewayDriverInterface
{
    /**
     * Process a payment charge.
     *
     * @throws \App\Services\Gateway\GatewayException on failure
     */
    public function charge(ChargeRequest $request): ChargeResponse;

    /**
     * Refund a previously created transaction.
     *
     * @throws \App\Services\Gateway\GatewayException on failure
     */
    public function refund(string $externalId): void;
}
