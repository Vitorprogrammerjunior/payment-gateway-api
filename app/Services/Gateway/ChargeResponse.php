<?php

namespace App\Services\Gateway;

final class ChargeResponse
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $cardLastNumbers,
    ) {}
}
