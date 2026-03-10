<?php

namespace App\Services\Gateway\Drivers;

use App\Services\Gateway\GatewayDriverInterface;

abstract class AbstractGatewayDriver implements GatewayDriverInterface
{
    protected function extractLastFour(string $cardNumber): string
    {
        return substr(preg_replace('/\D/', '', $cardNumber), -4);
    }
}
