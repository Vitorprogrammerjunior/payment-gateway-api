<?php

namespace App\Services\Gateway;

final class ChargeRequest
{
    public function __construct(
        public readonly int    $amount,
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly string $cardNumber,
        public readonly string $cvv,
    ) {}
}
